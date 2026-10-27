"use client";

import { useEffect, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import NumericInput from '@/components/ui/NumericInput';
import { MenuItemPicker } from './MenuItemPicker';
import { MenuItemProfile } from './MenuItemProfile';
import { fetchRecipes, fetchRecipeById } from '@/services/foodLibraryService';
import type { PaginationMeta } from '@/components/ui/Pagination';
import { formulaFromRecipe, formulaNutrients, formatFormulaAmount, type FormulaItem } from '@/lib/formulaPlan';
import { recipeRecallSnapshot } from '@/lib/foodRecall';
import { clinicalRecipeUnits } from '@/lib/clinicalUnits';
import { MenuEditorSlots, MenuEditorActions, MenuEditorAction } from './MenuEditorSlots';
import ButtonFilterGroup from '@/components/ui/ButtonFilterGroup';
import MacroTrackerBar from '@/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar';
import { MicronutrientTotals } from './MicronutrientTotals';
import { useUnsavedChanges } from '@/hooks/useUnsavedChanges';

export function FormulaCollection({ items, onChange, readOnly = false, editPortions = true, selectedMicros, actions, onCreate }: {
  items: FormulaItem[]; onChange: (items: FormulaItem[]) => void; readOnly?: boolean; editPortions?: boolean;
  selectedMicros?: string[]; actions?: ReactNode; onCreate?: () => void;
}) {
  const [picker, setPicker] = useState(false), [query, setQuery] = useState(''), [page, setPage] = useState(1);
  const [matches, setMatches] = useState<{id: string; name: string}[]>([]), [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(false), [error, setError] = useState<string | null>(null), [busy, setBusy] = useState<string | null>(null);
  const [selected, setSelected] = useState<string | null>(null);
  const [item, setEditing] = useState<FormulaItem | null>(null);
  const [original, setOriginal] = useState<FormulaItem | null>(null);
  const editable = !readOnly && editPortions;
  const update = (next: FormulaItem) => setEditing(next);
  const apply = async () => { if (item) onChange(items.map(row => row.id === item.id ? item : row)); setEditing(null); return true; };
  const guard = useUnsavedChanges({ dirty: editable && !!item && JSON.stringify(item) !== JSON.stringify(original), watchNavigation: false,
    onSave: apply });
  useEffect(() => {
    if (!picker) return;
    let active = true; setLoading(true); setError(null);
    const timer = setTimeout(async () => {
      try { const result = await fetchRecipes(query, 'tube_feeding', page, 10, 'tube_feeding'); if (active) { setMatches(result.data); setMeta(result.meta ?? null); } }
      catch (cause) { if (active) setError(cause instanceof Error ? cause.message : 'Could not load formulas.'); }
      finally { if (active) setLoading(false); }
    }, 180);
    return () => { active = false; clearTimeout(timer); };
  }, [picker, query, page]);
  if (item) {
    const snapshot = item.nutrient_snapshot, multiplier = item.portion_multiplier;
    const nutrients = formulaNutrients(item);
    const rows = snapshot.recipe_original_ingredients ?? [];
    const changeIngredient = (index: number, quantity: number, unit = rows[index].unit) => {
      const ingredients = rows.map(row => ({ food_item_id: row.food_item?.id ?? '', quantity: Number(row.quantity), unit: row.unit }));
      ingredients[index] = { ...ingredients[index], quantity: Number((quantity / multiplier).toFixed(6)), unit };
      const next = recipeRecallSnapshot(snapshot, ingredients);
      if (next) { setError(null); update({ ...item, nutrient_snapshot: next, reset_recipe: false }); }
      else setError('Enter a positive measured quantity in a compatible unit.');
    };
    return <>{guard.dialog}<MenuItemProfile name={snapshot.name} prepNotes={snapshot.prep_notes}
      nutritionBasis="Planned portion" onBack={() => guard.requestLeave(() => setEditing(null))} backLabel="Back to formula plan"
      nutrients={{ ...nutrients, micronutrients: Object.fromEntries(Object.entries(nutrients.micronutrients).filter(([key]) => !selectedMicros || selectedMicros.includes(key))) }}
      ingredients={rows.map((row, index) => ({ id: index, name: row.name, quantity: Number(row.quantity) * multiplier, unit: row.unit }))}
      controls={editable ? <div className="flex flex-wrap items-end gap-3"><NumericInput label="Portion multiplier" value={String(multiplier)} min={0.000001} max={100000} step={0.1} unit="×" onChange={value => { if (Number(value) > 0) update({ ...item, portion_multiplier: Number(value), reset_recipe: false }); }} /><Button variant="secondary" onClick={() => update({ ...item, portion_multiplier: 1, nutrient_snapshot: structuredClone(item.original_snapshot), reset_recipe: true })}>Reset recipe</Button></div> : undefined}
      renderIngredientAmount={editable ? ingredient => <div className="flex flex-wrap items-end gap-2"><NumericInput label={`${ingredient.name} quantity`} value={String(ingredient.quantity)} min={0.000001} max={100000 * multiplier} step={0.1} unit="" onChange={value => changeIngredient(ingredient.id, Number(value))} /><select aria-label={`${ingredient.name} unit`} className="min-h-11 rounded-lg border border-warm-200 px-2" value={ingredient.unit} onChange={event => changeIngredient(ingredient.id, Number(ingredient.quantity), event.target.value)}>{clinicalRecipeUnits(rows[ingredient.id].food_item?.serving_unit ?? '').map(unit => <option key={unit}>{unit}</option>)}</select></div> : ingredient => <span className="text-sm text-warm-600">{formatFormulaAmount(Number(ingredient.quantity))} {ingredient.unit}</span>}
      actions={<>{error && <p role="alert" className="text-sm text-red-700">{error}</p>}{editable && <Button onClick={() => void apply()}>Apply portion</Button>}</>} /></>;
  }
  const active = items.find(row => row.id === selected) ?? items[0];
  const selectedIndex = active ? items.indexOf(active) : -1;
  const nutrients = active ? formulaNutrients(active) : null;
  const micros = selectedMicros ?? Object.keys(nutrients?.micronutrients ?? {});
  return <section aria-label="Tube Feeding formula plan" className="min-w-0 space-y-4 rounded-xl border border-warm-200 bg-white p-3">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h3 className="text-base font-bold text-warm-800">Formula recipes</h3>
      <div className="flex flex-wrap gap-2">{actions}</div>
    </div>
    {error && !picker && <p role="alert" className="text-sm text-red-700">{error}</p>}
    {!!items.length && <ButtonFilterGroup size="sm" label="Nutrients" value={active.id} options={items.map((row, index) => ({ value: row.id, label: `Formula ${index + 1}` }))} onChange={setSelected} />}
    {nutrients && <section aria-label={`Formula ${selectedIndex + 1} nutrient profile`} className="space-y-3">
      <p className="break-words text-sm font-semibold text-warm-700">{active.nutrient_snapshot.name}</p>
      <MacroTrackerBar showWithoutTargets targets={[
        { label: 'Energy', current: nutrients.calories, target: 0, unit: 'kcal' },
        { label: 'Protein', current: nutrients.protein, target: 0, unit: 'g' },
        { label: 'Carbs', current: nutrients.carbs, target: 0, unit: 'g' },
        { label: 'Fat', current: nutrients.fat, target: 0, unit: 'g' },
        { label: 'Fluid', current: nutrients.fluid_ml, target: 0, unit: 'mL' },
      ]} />
      <MicronutrientTotals title={`Formula ${selectedIndex + 1} micronutrients`} selected={micros} totals={nutrients.micronutrients} />
    </section>}
    <div role="region" aria-label="Formula slots" tabIndex={0} className="grid min-w-0 auto-cols-[min(14rem,100%)] grid-flow-col items-start gap-3 overflow-x-auto overscroll-x-contain rounded-lg pb-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
      {items.map((row, index) => <article key={row.id} aria-label={`Formula ${index + 1}`} className="min-w-0 rounded-lg border border-warm-200">
        <h4 className="rounded-t-lg border-b border-warm-200 bg-warm-50 px-2 py-2 text-xs font-bold text-warm-600">Formula {index + 1}</h4>
        <div className="p-2"><MenuEditorSlots compact readOnly items={[<div key={row.id}>
          <p className="break-words pr-6 text-xs font-bold text-warm-800">{row.nutrient_snapshot.name}</p>
          {!readOnly && <MenuEditorActions><MenuEditorAction kind="remove" label={`Remove Formula ${index + 1}`} onClick={event => { event.stopPropagation(); onChange(items.filter(entry => entry.id !== row.id)); }} /></MenuEditorActions>}
        </div>]} itemActions={[{ label: `${editable ? 'Edit' : 'View'} ${row.nutrient_snapshot.name}`, onOpen: () => { setOriginal(structuredClone(row)); setEditing(structuredClone(row)); setSelected(row.id); setError(null); } }]} addLabel="Select formula" /></div>
      </article>)}
      {!readOnly && <section aria-label="Add formula" className="flex flex-col items-stretch gap-2 p-2">
        {onCreate && <Button variant="secondary" onClick={onCreate}>Create formula</Button>}
        <Button variant="secondary" onClick={() => { setQuery(''); setPage(1); setMatches([]); setPicker(true); }}>Select formula</Button>
      </section>}
      {readOnly && !items.length && <p className="p-4 text-sm text-warm-500">No formula recipes recorded.</p>}
    </div>
    {picker && <MenuItemPicker title="Select Tube Feeding formula" query={query} onQueryChange={value => { setQuery(value); setPage(1); }} loading={loading} error={error} items={matches} busyId={busy} page={page} meta={meta} onPageChange={setPage} onClose={() => { if (!busy) setPicker(false); }} onSelect={async id => { setBusy(id); try { const recipe = await fetchRecipeById(id); const added = formulaFromRecipe(recipe); onChange([...items, added]); setSelected(added.id); setPicker(false); } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not add formula.'); } finally { setBusy(null); } }} />}
  </section>;
}
