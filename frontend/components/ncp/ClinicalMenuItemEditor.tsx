"use client";
import { useEffect, useRef, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import NumericInput, { numericStepBase } from '@/components/ui/NumericInput';
import { MenuItemPicker } from './MenuItemPicker';
import { MenuItemProfile } from './MenuItemProfile';
import { useEditorChanges } from '@/hooks/useUnsavedChanges';
import { fetchRecipeById, fetchFoodItems, importUsdaFood, type Recipe, type RecipeIngredient, type FoodItem } from '@/services/foodLibraryService';
import type { MealPlanItem, NutrientSnapshot } from '@/services/mealPlanService';
import type { PaginationMeta } from '@/components/ui/Pagination';
import { clinicalIngredientFactor, clinicalRecipeUnits, clinicalRecipeNutrients } from '@/lib/clinicalUnits';
import { quantityInputValue } from '@/lib/foodServiceFormat';

export type MenuItemEdit = { quantity?: number; ingredients?: { food_item_id: string; quantity: number; unit: string }[] };
const ingredientSignature = (rows: { qty: string; ing: RecipeIngredient }[]) => JSON.stringify(rows.map(row => ({ food: row.ing.food_item?.id, quantity: Number(row.qty), unit: row.ing.unit })));
export function ClinicalMenuItemEditor({ item, readOnly = false, allowMixedQuantity = false, scopeLabel, backLabel = 'Back to menu plan', onBack, onSave }: {
  item: MealPlanItem; readOnly?: boolean; allowMixedQuantity?: boolean; scopeLabel?: string; backLabel?: string; onBack: () => void;
  onSave: (payload: MenuItemEdit, snapshot: NutrientSnapshot | null) => Promise<boolean | void>;
}) {
  const editTarget = { item };
  const [savingToLibrary, setSavingToLibrary] = useState<string | null>(null);
  const initialIngredients = useRef('[]');
  // Edit meal item
  const editRequest = useRef(0);
  const [editServings, setEditServings]       = useState<string>('1');
  const [editReset, setEditReset] = useState<{ snapshot: NutrientSnapshot; quantity: number } | null>(null);
  const [editRecipe, setEditRecipe]           = useState<Pick<Recipe, 'name' | 'servings' | 'ingredients'> | null>(null);
  const [editIngredients, setEditIngredients] = useState<{ id: number; qty: string; ing: RecipeIngredient; added?: boolean }[]>([]);
  const [editAmountDrafts, setEditAmountDrafts] = useState<Record<number, string>>({});
  const [editLoadingRecipe, setEditLoadingRecipe] = useState(true);
  const [editSaving, setEditSaving]           = useState(false);
  const [editError, setEditError] = useState<string | null>(null);
  const [editTab, setEditTab]                 = useState<'scale' | 'ingredients'>('scale');
  const [ingredientQuery, setIngredientQuery] = useState('');
  const [ingredientPickerOpen, setIngredientPickerOpen] = useState(false);
  const [ingredientLoading, setIngredientLoading] = useState(false);
  const [ingredientPage, setIngredientPage] = useState(1);
  const [ingredientMeta, setIngredientMeta] = useState<PaginationMeta | null>(null);
  const [ingredientResults, setIngredientResults] = useState<FoodItem[]>([]);
  const [ingredientSearchError, setIngredientSearchError] = useState<string | null>(null);
  useEffect(() => {
    if (editTab !== 'ingredients' || !ingredientPickerOpen) { setIngredientResults([]); return; }
    let cancelled = false;
    setIngredientLoading(true);
    setIngredientSearchError(null);
    const timer = setTimeout(() => { fetchFoodItems(ingredientQuery, undefined, ingredientPage).then((result) => { if (!cancelled) { setIngredientResults(result.data); setIngredientMeta(result.meta ?? null); } }).catch(() => { if (!cancelled) setIngredientSearchError('Could not load Ingredients. Try again.'); }).finally(() => { if (!cancelled) setIngredientLoading(false); }); }, 200);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [ingredientQuery, item, editTab, ingredientPickerOpen, ingredientPage]);


  useEffect(() => {
    function initializeIngredients(rows: typeof editIngredients) {
      initialIngredients.current = ingredientSignature(rows); setEditIngredients(rows);
    }
    setEditReset(item.nutrient_snapshot ? { snapshot: structuredClone(item.nutrient_snapshot.recipe_reset_baseline ?? item.nutrient_snapshot), quantity: item.nutrient_snapshot.recipe_reset_quantity ?? Number(item.quantity) } : null);
    const request = ++editRequest.current;
    setEditError(null);
    setEditServings(item.quantity ?? '1');
    setEditTab('scale');
    setEditRecipe(null);
    setEditIngredients([]);
    setEditAmountDrafts({});
    setIngredientQuery(''); setIngredientResults([]); setIngredientSearchError(null); setIngredientPickerOpen(false);
    const snapshot = item.nutrient_snapshot;
    if (snapshot?.recipe_yield && snapshot.recipe_original_ingredients?.length && snapshot.recipe_original_ingredients.every((ingredient) => ingredient.id && ingredient.food_item)) {
      const ingredients = snapshot.recipe_original_ingredients.map((ingredient) => ({
        ...ingredient, quantity: String(snapshot.recipe_ingredients?.find((planned) => planned.id === ingredient.id)?.quantity ?? ingredient.quantity),
      }));
      setEditRecipe({ name: snapshot.name, servings: snapshot.recipe_yield, ingredients });
      initializeIngredients(ingredients.map((ingredient) => ({ id: ingredient.id, qty: ingredient.quantity, ing: ingredient })));
      setEditLoadingRecipe(false);
      return;
    }
    if (readOnly) {
      setEditLoadingRecipe(false);
      return;
    }
    if (item.recipe_id) {
      setEditLoadingRecipe(true);
      fetchRecipeById(item.recipe_id).then((recipe) => {
        if (request !== editRequest.current) return;
        setEditRecipe(recipe);
        initializeIngredients(recipe.ingredients.map((ingredient) => ({
          id: ingredient.id, qty: String(item.ingredient_overrides?.find((row) => row.id === ingredient.id)?.quantity ?? ingredient.quantity), ing: ingredient,
        })));
      }).catch((cause) => { if (request === editRequest.current) setEditError(cause instanceof Error ? cause.message : 'Could not load ingredients. Try again.'); }).finally(() => { if (request === editRequest.current) setEditLoadingRecipe(false); });
    }
    return () => { editRequest.current = request + 1; };
  }, [item, readOnly]);
  // Every row stores whole-recipe amounts; the portion applies uniformly.
  const ingredientBatchQuantity = (row: (typeof editIngredients)[number]) => Number(row.qty);

  const recalcFromIngredients = (): Partial<NutrientSnapshot> => {
    if (!editRecipe) return {};
    const servings = editRecipe.servings ?? 1;
    const totals = clinicalRecipeNutrients(editIngredients.map(row => ({ quantity: ingredientBatchQuantity(row), unit: row.ing.unit, food: row.ing.food_item })), servings);
    if (!totals) return {};
    return {
      ...totals,
      recipe_reset_baseline: editReset?.snapshot,
      recipe_reset_quantity: editReset?.quantity,
      name: editTarget?.item.nutrient_snapshot?.name ?? editRecipe.name,
      serving_size: 1,
      serving_unit: 'serving',
      recipe_yield: servings,
      recipe_original_ingredients: editIngredients.map((row) => ({ ...row.ing, id: row.id, quantity: ingredientBatchQuantity(row), name: row.ing.food_item?.name ?? 'Ingredient' })),
      recipe_ingredients: editIngredients.map((row) => ({ id: row.id, name: row.ing.food_item?.name ?? 'Ingredient', quantity: ingredientBatchQuantity(row), unit: row.ing.unit })),
    };
  };

  const editValid = ((allowMixedQuantity && editServings === "") || (Number(editServings) >= 0.01 && Number(editServings) <= 99999.99 && Math.abs(Number(editServings) * 100 - Math.round(Number(editServings) * 100)) < 0.000001))
    && (editTab !== 'ingredients' || editIngredients.length > 0)
    && (!editRecipe?.ingredients.length || editIngredients.length > 0)
    && editIngredients.every((row) => Number(row.qty) > 0 && Number(row.qty) <= 100000
      && clinicalIngredientFactor(Number(row.qty), row.ing.unit, Number(row.ing.food_item?.serving_size), row.ing.food_item?.serving_unit ?? '') !== null);


  const handleSaveEdit = async () => {
    if (!editValid || editLoadingRecipe) { setEditError('Enter a valid planned portion and positive ingredient quantities in compatible units.'); return false; }
    setEditSaving(true); setEditError(null);
    try {
      const payload: MenuItemEdit = {};
      if (editServings !== '') payload.quantity = Number(editServings);
      const changedIngredients = ingredientSignature(editIngredients) !== initialIngredients.current;
      if (changedIngredients) payload.ingredients = editIngredients.map(row => ({ food_item_id: row.ing.food_item!.id, quantity: ingredientBatchQuantity(row), unit: row.ing.unit }));
      const snapshot = changedIngredients ? { ...item.nutrient_snapshot!, ...recalcFromIngredients() } as NutrientSnapshot : item.nutrient_snapshot;
      if (await onSave(payload, snapshot) === false) return false;
      onBack(); return true;
    } catch (cause) { setEditError(cause instanceof Error ? cause.message : 'Could not save this portion. Try again.'); return false; }
    finally { setEditSaving(false); }
  };
  const editGuard = useEditorChanges(JSON.stringify({ editServings, ingredients: editIngredients.map(({ id, qty, ing }) => ({ id, qty, unit: ing.unit })) }), !editLoadingRecipe && !readOnly, handleSaveEdit);
    const snapshot = editRecipe && editIngredients.length > 0 ? recalcFromIngredients() : editTarget.item.nutrient_snapshot;
    const portion = editServings === "" && allowMixedQuantity ? 1 : Number(editServings);
    const factor = portion / (Number(editRecipe?.servings) || 1);
    const nutrientFactor = portion / (Number(snapshot?.serving_size) || 1);
    return <MenuItemProfile
      name={editTarget.item.nutrient_snapshot?.name ?? editRecipe?.name ?? 'Menu Item'}
      servings={editRecipe?.servings}
      prepNotes={editTarget.item.nutrient_snapshot?.prep_notes ?? (editRecipe && 'prep_notes' in editRecipe ? String(editRecipe.prep_notes ?? '') : null)}
      onBack={() => editGuard.requestLeave(onBack)} backLabel={backLabel} nutritionBasis="Planned portion"
      ingredients={editIngredients.map((row) => ({ id: row.id, name: row.ing.food_item?.name ?? 'Ingredient', quantity: row.qty === '' ? '' : Number(row.qty) * factor, unit: row.ing.unit }))}
      nutrients={{
        calories: Number(snapshot?.calories ?? 0) * nutrientFactor,
        protein: Number(snapshot?.protein ?? 0) * nutrientFactor,
        carbs: Number(snapshot?.carbs ?? 0) * nutrientFactor,
        fat: Number(snapshot?.fat ?? 0) * nutrientFactor,
        water_g: Number(snapshot?.water_g ?? 0) * nutrientFactor,
        micronutrients: Object.fromEntries(Object.entries(snapshot?.micronutrients ?? {}).map(([key, value]) => [key, value * nutrientFactor])),
      }}
      controls={<div className="space-y-3">
        <p className="text-xs text-warm-500">{scopeLabel ?? "Changes affect this plan only. Ingredient amounts and nutrients below are for the planned portion."}</p>
        <div className="max-w-xs"><NumericInput label="Planned portion" min={0} max={100000} step={0.1} value={editServings} onChange={(value) => { setEditServings(value); setEditAmountDrafts({}); }} disabled={readOnly} unit={editTarget.item.unit} quantity /></div>
        {!readOnly && editReset && <Button variant="secondary" onClick={() => {
          const captured = editReset;
          const rows = (captured.snapshot.recipe_original_ingredients ?? []).map(row => ({ ...row, quantity: captured.snapshot.recipe_ingredients?.find(planned => planned.id === row.id)?.quantity ?? row.quantity }));
          setEditRecipe({ name: captured.snapshot.name, servings: captured.snapshot.recipe_yield ?? null, ingredients: rows.map(row => ({ ...row, quantity: String(row.quantity) })) });
          setEditIngredients(rows.map((row, index) => ({ id: row.id ?? index + 1, qty: String(row.quantity), ing: { ...row, quantity: String(row.quantity) } })));
          setEditServings(String(captured.quantity)); setEditAmountDrafts({});
        }}>Reset recipe</Button>}
        {!readOnly && <div className="flex gap-2"><Button variant={editTab === 'scale' ? 'secondary' : 'ghost'} size="sm" onClick={() => setEditTab('scale')}>Portion</Button><Button variant={editTab === 'ingredients' ? 'secondary' : 'ghost'} size="sm" onClick={() => setEditTab('ingredients')}>Ingredients</Button></div>}
        {editLoadingRecipe && <p role="status" className="text-sm text-warm-500">Loading ingredients…</p>}
        {editError && <p role="alert" className="text-sm text-red-700">{editError}</p>}
      </div>}
      ingredientControls={!readOnly && editTab === 'ingredients' ? <div className="space-y-2">
        <Button type="button" variant="secondary" size="sm" onClick={() => { setIngredientQuery(''); setIngredientPage(1); setIngredientPickerOpen(true); }}><Plus className="h-3 w-3" />Add Ingredient</Button>
        {ingredientPickerOpen && <MenuItemPicker title="Add Ingredient" itemLabel="Ingredient" query={ingredientQuery} onQueryChange={(query) => { setIngredientQuery(query); setIngredientPage(1); }} loading={ingredientLoading} error={ingredientSearchError} meta={ingredientMeta} page={ingredientPage} onPageChange={setIngredientPage} items={ingredientResults.map((food) => ({ id: food.id, name: food.name }))} onClose={() => setIngredientPickerOpen(false)} onSelect={(foodId) => {
          const food = ingredientResults.find((item) => item.id === foodId);
          if (!food) return;
          const id = Math.min(0, ...editIngredients.map((row) => row.id)) - 1;
          setEditIngredients((previous) => [...previous, { id, qty: '', added: true, ing: { id, food_item_id: 0, quantity: '', unit: food.serving_unit ?? '', food_item: food } }]);
          setIngredientQuery(''); setIngredientResults([]); setIngredientPickerOpen(false);
        }} />}
      </div> : undefined}
      renderIngredientAmount={!readOnly && editTab === 'ingredients' ? (ingredient) => <div className="flex flex-wrap items-center gap-2 text-sm text-warm-600"><input aria-label={`Amount of ${ingredient.name}`} type="number" min={numericStepBase(quantityInputValue(editAmountDrafts[ingredient.id] ?? ingredient.quantity))} step="0.1" value={quantityInputValue(editAmountDrafts[ingredient.id] ?? ingredient.quantity)} onChange={(event) => {
        if (!(factor > 0)) return;
        setEditAmountDrafts((previous) => ({ ...previous, [ingredient.id]: event.target.value }));
        const amount = event.target.value === '' ? '' : String(Number((Number(event.target.value) / factor).toFixed(6)));
        setEditIngredients((previous) => previous.map((row) => row.id === ingredient.id ? { ...row, qty: amount } : row));
      }} className="w-28 rounded-lg border border-warm-200 px-2 py-2 text-right text-base" />
        <select aria-label={`Unit of ${ingredient.name}`} value={ingredient.unit} onChange={(event) => { setEditIngredients((previous) => previous.map((row) => row.id === ingredient.id ? { ...row, ing: { ...row.ing, unit: event.target.value } } : row)); setEditAmountDrafts({}); }} className="rounded-lg border border-warm-200 px-2 py-2 text-base">{clinicalRecipeUnits(editIngredients.find((row) => row.id === ingredient.id)?.ing.food_item?.serving_unit ?? ingredient.unit).map((unit) => <option key={unit}>{unit}</option>)}</select>
        <Button variant="icon" aria-label={`Remove ${ingredient.name}`} onClick={() => setEditIngredients((previous) => previous.filter((row) => row.id !== ingredient.id))}><Trash2 className="h-4 w-4" /></Button>
      </div> : undefined}
      actions={!readOnly ? <>
        {editGuard.dialog}
        {editTarget.item.source === 'usda' && editTarget.item.fdc_id && <Button variant="secondary" loading={savingToLibrary === editTarget.item.fdc_id} onClick={() => { const fdcId = editTarget.item.fdc_id!; setSavingToLibrary(fdcId); importUsdaFood(Number(fdcId)).catch((cause) => setEditError(cause instanceof Error ? cause.message : 'Could not save to library.')).finally(() => setSavingToLibrary(null)); }}>Save to Library</Button>}
        <Button variant="secondary" onClick={() => editGuard.requestLeave(onBack)}>Cancel</Button><Button variant="primary" loading={editSaving} disabled={editLoadingRecipe || !editValid} onClick={() => void handleSaveEdit()}>Save Changes</Button>
      </> : undefined}
    />;
}
