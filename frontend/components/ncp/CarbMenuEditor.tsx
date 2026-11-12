"use client";

import { useEffect, useMemo, useRef, useState } from 'react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { DeleteActionDialog } from '@/components/ui/DeleteActionDialog';
import type { PaginationMeta } from '@/components/ui/Pagination';
import { fetchRecipes, fetchRecipeById, type Recipe } from '@/services/foodLibraryService';
import type { MealPlanItem, NutrientSnapshot } from '@/services/mealPlanService';
import { recipeMenuSnapshot } from '@/lib/mealPlanNutrition';
import { ClinicalMenuItemEditor } from './ClinicalMenuItemEditor';
import { MenuItemPicker } from './MenuItemPicker';
import type { CarbEntry, MealPlanOptions } from './MealPlanOptionsDialog';

export function CarbMenuEditor({ mode, items, onBack, onApply }: {
  mode: 'add' | 'edit'; items: CarbEntry[]; onBack: () => void;
  onApply: (options: MealPlanOptions, recipe: Recipe | null, snapshot: NutrientSnapshot | null) => Promise<boolean | void>;
}) {
  const groups = useMemo(() => Array.from(items.filter(item => item.category === 'carb' && item.recipeId).reduce((result, item) => {
    const group = result.get(item.recipeId!) ?? { ...item, count: 0, quantities: new Set<number>() };
    group.count++; group.quantities.add(item.quantity); result.set(item.recipeId!, group); return result;
  }, new Map<string, CarbEntry & { count: number; quantities: Set<number> }>()).values()), [items]);
  const [editing, setEditing] = useState<{ item: MealPlanItem; source?: string; recipe: Recipe | null; count: number; mixed: boolean } | null>(null);
  const [deleting, setDeleting] = useState<(typeof groups)[number] | null>(null);
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [choices, setChoices] = useState<Recipe[]>([]);
  const [loading, setLoading] = useState(mode === 'add');
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const heading = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    if (mode === 'edit' && !editing) { heading.current?.focus(); heading.current?.scrollIntoView?.({ block: 'start' }); }
  }, [mode, editing, groups.length]);
  useEffect(() => {
    if (mode !== 'add' || editing) return;
    let cancelled = false;
    setLoading(true); setError(null);
    const timer = setTimeout(() => {
      fetchRecipes(query.trim(), 'carb', page, 10).then(result => { if (!cancelled) { setChoices(result.data); setMeta(result.meta ?? null); } })
        .catch(cause => { if (!cancelled) setError(cause instanceof Error ? cause.message : 'Could not load carbs.'); })
        .finally(() => { if (!cancelled) setLoading(false); });
    }, 200);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [mode, query, page, editing]);
  function asItem(entry: CarbEntry, quantity: string): MealPlanItem {
    return entry.item ? { ...entry.item, quantity } : {
      id: entry.recipeId!, meal_plan_day_id: '', recipe_id: entry.recipeId, recipe_category: 'carb',
      nutrient_snapshot: entry.snapshot ?? { name: entry.name, calories: 0, protein: 0, carbs: 0, fat: 0, micronutrients: {}, serving_size: 1, serving_unit: 'serving' },
      quantity, unit: 'serving', food_item_id: null, fdc_id: null, source: 'recipe', ai_suggested: false,
    };
  }
  async function selectRecipe(id: string) {
    setBusy(id); setError(null);
    try {
      const recipe = await fetchRecipeById(id);
      const item = asItem({ recipeId: id, name: recipe.name, snapshot: recipeMenuSnapshot(recipe), quantity: 1 }, '1');
      setEditing({ item, recipe, count: 21, mixed: false });
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not load this Carb Menu Item.'); }
    finally { setBusy(null); }
  }
  if (editing) return <ClinicalMenuItemEditor key={editing.item.id} item={editing.item} allowMixedQuantity={editing.mixed}
    backLabel={mode === 'add' ? 'Back to carbs' : 'Back to Edit carbs'} onBack={() => setEditing(null)}
    scopeLabel={mode === 'add' ? 'Add to all 21 main meal slots. Slots already containing this item are kept.' : `Applies to all ${editing.count} matching active items, including manual additions. Preview uses the first preparation; servings-only edits preserve each slot’s ingredients.${editing.mixed ? ' Servings vary: leave the portion blank to keep them, or enter servings for all. The preview shows one serving while blank.' : ''}`}
    onSave={async (payload, snapshot) => {
      const result = await onApply({ ...payload, ...(editing.source ? { source_recipe_id: editing.source } : { recipe_id: editing.recipe!.id }) }, editing.recipe, snapshot);
      if (result !== false && mode === 'add') onBack();
      return result;
    }} />;
  if (mode === 'add') return <MenuItemPicker title="Add carbs" query={query} onQueryChange={value => { setQuery(value); setPage(1); }} loading={loading} error={error} items={choices.map(choice => ({ id: choice.id, name: choice.name }))} onSelect={id => void selectRecipe(id)} busyId={busy} onClose={onBack} meta={meta} page={page} onPageChange={setPage} />;
  return <section aria-label="Edit carbs" className="space-y-4">
    <div className="flex items-center gap-3"><Button variant="icon" className="min-h-11 min-w-11" aria-label="Back to menu" onClick={onBack}><ArrowLeft className="h-4 w-4" /></Button><h3 ref={heading} tabIndex={-1} className="text-lg font-bold text-warm-900 outline-none">Edit carbs</h3></div>
    {error && !deleting && <p role="alert" className="text-sm text-red-700">{error}</p>}
    {groups.length === 0 && <p className="text-sm text-warm-500">No Carb Menu Items in the active menu.</p>}
    <div className="grid gap-3 sm:grid-cols-2">{groups.map(group => <article key={group.recipeId} className="rounded-xl border border-warm-200 bg-white p-4">
      <h4 className="break-words font-semibold text-warm-900">{group.name}</h4>
      <p className="mt-1 text-sm text-warm-500">{group.count} {group.count === 1 ? 'item' : 'items'} · {group.quantities.size > 1 ? 'Mixed servings' : `${group.quantity} ${group.quantity === 1 ? 'serving' : 'servings'}`}</p>
      <div className="mt-3 flex justify-end gap-2 border-t border-warm-100 pt-2">
        <Button variant="icon" className="min-h-11 min-w-11" aria-label={`Edit ${group.name}`} onClick={() => setEditing({ item: asItem(group, group.quantities.size > 1 ? '' : String(group.quantity)), source: group.recipeId!, recipe: null, count: group.count, mixed: group.quantities.size > 1 })}><Pencil className="h-4 w-4" /></Button>
        <Button variant="icon" className="min-h-11 min-w-11" aria-label={`Delete ${group.name}`} onClick={() => { setError(null); setDeleting(group); }}><Trash2 className="h-4 w-4" /></Button>
      </div>
    </article>)}</div>
    {deleting && <DeleteActionDialog title={`Delete ${deleting.name}?`} message={`Delete all ${deleting.count} matching active menu items?`} related="Other items and hidden snacks are preserved." busy={Boolean(busy)} error={error} onClose={() => setDeleting(null)} onConfirm={async () => {
      setBusy(deleting.recipeId); setError(null);
      try { if (await onApply({ source_recipe_id: deleting.recipeId!, remove: true }, null, null) !== false) setDeleting(null); }
      catch (cause) { setError(cause instanceof Error ? cause.message : 'Could not delete these items.'); }
      finally { setBusy(null); }
    }} />}
  </section>;
}
