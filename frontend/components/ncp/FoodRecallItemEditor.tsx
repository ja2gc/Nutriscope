"use client";

import { useRef, useState } from 'react';
import { MenuItemProfile } from './MenuItemProfile';
import { Button } from '@/components/ui/Button';
import NumericInput from '@/components/ui/NumericInput';
import { useUnsavedChanges } from '@/hooks/useUnsavedChanges';
import { clinicalRecipeUnits } from '@/lib/clinicalUnits';
import { formatMealPlanAmount } from '@/lib/mealPlanNutrition';
import { foodRecallItemFactor, foodRecallItemNutrients, recipeRecallSnapshot, type FoodRecallItem, type RecallIngredient } from '@/lib/foodRecall';

export function FoodRecallItemEditor({ item, readOnly, onChange, onBack, onRemove }: {
  item: FoodRecallItem; readOnly: boolean; onChange: (item: FoodRecallItem) => void; onBack: () => void; onRemove: () => void;
}) {
  const original = useRef(item);
  const [baseline, setBaseline] = useState(item);
  const [error, setError] = useState<string | null>(null);
  const snapshot = item.nutrient_snapshot;
  const factor = foodRecallItemFactor(item), nutrients = foodRecallItemNutrients(item);
  const rows = snapshot.recipe_original_ingredients ?? [];
  const units = item.recipe_id ? ['serving', ...(Number(snapshot.prepared_portion_amount) > 0 ? clinicalRecipeUnits(snapshot.prepared_portion_unit ?? '') : [])] : clinicalRecipeUnits(snapshot.serving_unit);
  const valid = nutrients !== null && item.quantity <= 100000 && rows.every(row => Number(row.quantity) > 0 && Number(row.quantity) <= 100000);
  async function apply() {
    if (!valid) { setError('Enter a positive measured quantity and a compatible unit.'); return false; }
    original.current = item;
    setBaseline(item);
    return true;
  }
  const guard = useUnsavedChanges({ dirty: !readOnly && JSON.stringify(item) !== JSON.stringify(baseline), watchNavigation: false, onSave: apply });
  const leave = () => guard.requestLeave(() => { onChange(original.current); onBack(); });
  function changeIngredient(index: number, patch: Partial<RecallIngredient>) {
    const ingredients = rows.map(row => ({ food_item_id: row.food_item?.id ?? '', quantity: Number(row.quantity), unit: row.unit }));
    ingredients[index] = { ...ingredients[index], ...patch };
    const updated = recipeRecallSnapshot(snapshot, ingredients);
    if (updated) { setError(null); onChange({ ...item, ingredients, nutrient_snapshot: updated }); }
    else setError('Enter a positive measured quantity in the Ingredient’s unit family.');
  }
  return <>{guard.dialog}<MenuItemProfile name={snapshot.name} profileType={item.recipe_id ? 'Menu Item' : 'Ingredient'}
    servings={snapshot.recipe_yield} prepNotes={snapshot.prep_notes} nutrients={nutrients ?? undefined}
    nutritionBasis="Consumed portion" onBack={leave} backLabel="Back to calculation"
    controls={<div className="space-y-3">{readOnly ? <p className="text-sm text-warm-700">Consumed amount: {formatMealPlanAmount(item.quantity)} {item.unit}</p> : <div className="grid grid-cols-2 gap-3">
      <NumericInput label="Quantity" value={String(item.quantity)} onChange={value => onChange({ ...item, quantity: Number(value) })} unit="" min={0.000001} max={100000} step={0.1} quantity />
      <label className="text-xs font-bold uppercase tracking-widest text-warm-500">Unit<select aria-label="Unit" value={item.unit} onChange={event => onChange({ ...item, unit: event.target.value })} className="mt-1 block min-h-10 w-full rounded-lg border border-warm-200 bg-white px-2 py-2 text-sm text-warm-900">{units.map(unit => <option key={unit}>{unit}</option>)}</select></label>
    </div>}<p className="text-xs text-warm-500">Saved baseline: {formatMealPlanAmount(snapshot.calories)} kcal per {snapshot.serving_size} {snapshot.serving_unit}</p>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}</div>}
    ingredients={rows.map((row, index) => ({ id: index, name: row.name, quantity: Number(row.quantity) / (snapshot.recipe_yield || 1) * (factor ?? 0), unit: row.unit }))}
    renderIngredientAmount={readOnly ? undefined : ingredient => <div className="flex w-full flex-wrap items-end gap-2 sm:w-auto">
      <div className="w-28"><NumericInput label={`${ingredient.name} quantity`} value={String(ingredient.quantity)} onChange={value => changeIngredient(ingredient.id, { quantity: Number((Number(value) * (snapshot.recipe_yield || 1) / (factor || 1)).toFixed(6)) })} unit="" min={0.000001} max={100000} quantity /></div>
      <label className="min-w-20 text-xs text-warm-500">Unit<select aria-label={`${ingredient.name} unit`} value={ingredient.unit} onChange={event => changeIngredient(ingredient.id, { unit: event.target.value })} className="mt-1 block min-h-10 w-full rounded-lg border border-warm-200 bg-white px-2 py-2 text-sm text-warm-900">{clinicalRecipeUnits(rows[ingredient.id].food_item?.serving_unit ?? '').map(unit => <option key={unit}>{unit}</option>)}</select></label>
    </div>}
    actions={readOnly ? undefined : <><Button type="button" variant="ghost" onClick={onRemove}>Remove item</Button><Button type="button" variant="secondary" onClick={leave}>Cancel</Button><Button type="button" onClick={async () => { if (await apply()) onBack(); }}>Apply item</Button></>} />
  </>;
}
