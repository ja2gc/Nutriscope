"use client";

import { useEffect, useState } from 'react';
import { UnsavedChangesDialog } from '@/components/ui/UnsavedChangesDialog';
import { Pagination, type PaginationMeta } from '@/components/ui/Pagination';
import type { MealPlanItem, NutrientSnapshot } from '@/services/mealPlanService';
import { fetchRecipes, type Recipe } from '@/services/foodLibraryService';

export type CarbEntry = { recipeId: string | null; name: string; category?: string | null; quantity: number; item?: MealPlanItem; snapshot?: NutrientSnapshot | null };
export type MealPlanOptions = { source_recipe_id?: string; recipe_id?: string; quantity?: number; ingredients?: { food_item_id: string; quantity: number; unit: string }[]; remove?: boolean; exclude_carbs?: boolean; exclude_snacks?: boolean };

export function MealPlanOptionsDialog({ snacksHidden = false, replacing = false, error: externalError, allergens = [], foodDislikes = [], onClose, onConfirm }: {
  snacksHidden?: boolean; replacing?: boolean; error?: string | null;
  allergens?: string[]; foodDislikes?: string[];
  onClose: () => void; onConfirm: (options: MealPlanOptions, recipe: Recipe | null) => Promise<boolean | void> | boolean | void;
}) {
  const [excludeCarbs, setExcludeCarbs] = useState(false);
  const [excludeSnacks, setExcludeSnacks] = useState(snacksHidden);
  const [selected, setSelected] = useState<Recipe | null>(null);
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [choices, setChoices] = useState<Recipe[]>([]);
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const chooseRecipe = !excludeCarbs;
  useEffect(() => {
    if (!chooseRecipe) return;
    let cancelled = false;
    const timer = setTimeout(async () => {
      setLoading(true);
      try { const result = await fetchRecipes(query, 'carb', page, 10); if (!cancelled) { setChoices(result.data); setMeta(result.meta ?? null); } }
      catch (cause) { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load Carbs.'); }
      finally { if (!cancelled) setLoading(false); }
    }, 160);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [chooseRecipe, query, page]);

  async function submit() {
    setError(null);
    if (chooseRecipe && !selected) { setError('Choose a Carb Menu Item, or exclude carbs when generating.'); return; }
    const options: MealPlanOptions = { exclude_carbs: excludeCarbs, exclude_snacks: excludeSnacks };
    if (chooseRecipe && selected) options.recipe_id = selected.id;
    setBusy(true);
    try { if (await onConfirm(options, chooseRecipe ? selected : null) !== false) onClose(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not apply changes.'); }
    finally { setBusy(false); }
  }
  return <UnsavedChangesDialog title="Generate meal plan" closeLabel="Close Generate meal plan" discardLabel="Cancel" saveLabel={replacing ? 'Replace and generate' : 'Generate'} busy={busy} error={error ?? externalError} onStay={onClose} onDiscard={onClose} onSave={() => void submit()} message={<fieldset disabled={busy} className="space-y-4">
      {replacing && <p>This replaces the current weekly menu.</p>}
      {allergens.length > 0 && <p>Recorded allergens excluded: {allergens.join(', ')}.</p>}
      {foodDislikes.length > 0 && <p>Food dislikes flagged for review: {foodDislikes.join(', ')}.</p>}
      <fieldset className="flex flex-wrap gap-4"><legend className="mb-2 font-semibold">Carb side for all 21 main meal slots</legend>
        <label className="flex min-h-11 items-center gap-2"><input type="radio" aria-label="Add carbs" name="carb-choice" checked={!excludeCarbs} onChange={() => setExcludeCarbs(false)} />Add carbs</label>
        <label className="flex min-h-11 items-center gap-2"><input type="radio" aria-label="Exclude carbs" name="carb-choice" checked={excludeCarbs} onChange={() => setExcludeCarbs(true)} />Exclude carbs</label>
      </fieldset>
      <label className="flex min-h-11 items-center gap-2"><input type="checkbox" aria-label="Exclude snacks" checked={excludeSnacks} onChange={event => setExcludeSnacks(event.target.checked)} />Exclude snacks</label>
      <p className="text-xs">Exclude snacks skips snack generation. Show snack slots controls whether saved snacks are active.</p>
    {chooseRecipe && <div className="space-y-2"><label className="block font-semibold">Search Carb Menu Items<input type="search" value={query} onChange={event => { setQuery(event.target.value); setPage(1); }} className="mt-1 min-h-11 w-full rounded-lg border border-warm-200 px-3" /></label>
      {selected && <p className="font-semibold">Selected: {selected.name}</p>}
      {loading ? <p role="status">Loading Carb Menu Items…</p> : choices.length === 0 ? <p>No Carb Menu Items found. Add one in Food Library.</p> : choices.map(choice => <label key={choice.id} className="flex min-h-11 items-center gap-3 rounded-lg border border-warm-200 p-3"><input type="radio" name="selected-carb" checked={selected?.id === choice.id} onChange={() => { setSelected(choice); setError(null); }} /><span>{choice.name}</span></label>)}
      <Pagination meta={meta} page={page} onPageChange={setPage} />
    </div>}
  </fieldset>} />;
}
