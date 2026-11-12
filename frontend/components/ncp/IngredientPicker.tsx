"use client";

import { useEffect, useState } from 'react';
import ButtonFilterGroup from '@/components/ui/ButtonFilterGroup';
import type { PaginationMeta } from '@/components/ui/Pagination';
import { fetchFoodItems, searchUsda, importUsdaFood, type FoodItem, type UsdaSearchResult } from '@/services/foodLibraryService';
import { MenuItemPicker } from './MenuItemPicker';

export function IngredientPicker({ onSelect, onClose }: { onSelect: (food: FoodItem) => void; onClose: () => void }) {
  const [source, setSource] = useState('library');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [foods, setFoods] = useState<FoodItem[]>([]);
  const [usda, setUsda] = useState<UsdaSearchResult[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [importError, setImportError] = useState<string | null>(null);
  useEffect(() => {
    let cancelled = false;
    setLoading(true); setSearchError(null); setImportError(null);
    setFoods([]); setUsda([]); setMeta(null);
    if (source === 'usda' && !query.trim()) { setLoading(false); return; }
    const timer = setTimeout(async () => {
      try {
        if (source === 'usda') {
          const result = await searchUsda(query.trim());
          if (!cancelled) setUsda(result);
        } else {
          const result = await fetchFoodItems(query.trim(), 'all', page);
          if (!cancelled) { setFoods(result.data); setMeta(result.meta ?? null); }
        }
      } catch (cause) { if (!cancelled) setSearchError(cause instanceof Error ? cause.message : 'Could not search Ingredients. Try again.'); }
      finally { if (!cancelled) setLoading(false); }
    }, 180);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [source, query, page]);
  async function select(id: string) {
    if (busy) return;
    if (source === 'library') { const food = foods.find(food => food.id === id); if (food) onSelect(food); return; }
    const saved = usda.find(food => String(food.fdc_id) === id)?.library_food;
    if (saved) { onSelect(saved); return; }
    setBusy(id); setImportError(null);
    try { onSelect(await importUsdaFood(Number(id))); }
    catch (cause) { setImportError(cause instanceof Error ? cause.message : 'Could not import this Ingredient. Try again.'); }
    finally { setBusy(null); }
  }
  return <MenuItemPicker title="Add Ingredient" itemLabel="Ingredient" query={query} searchLabel={source === 'usda' ? 'Search USDA foods' : 'Search Ingredients'}
    onQueryChange={value => { setLoading(true); setQuery(value); setPage(1); }} loading={loading} error={searchError}
    items={source === 'usda' ? usda.map(food => ({ id: String(food.fdc_id), name: food.name, detail: `${food.library_food ? 'Already in Ingredients' : food.data_type ?? 'USDA'} · ${food.calories} kcal` })) : foods.map(food => ({ id: food.id, name: food.name, detail: `${food.calories} kcal · P ${food.protein ?? 0}g · C ${food.carbs ?? 0}g · F ${food.fat ?? 0}g` }))}
    onSelect={id => void select(id)} onClose={onClose} busyId={busy} meta={meta} page={page} onPageChange={source === 'library' ? setPage : undefined}
    filters={<fieldset disabled={Boolean(busy)} className="space-y-2 [&_button]:min-h-11">
      <ButtonFilterGroup label="Source" value={source} onChange={value => { setLoading(true); setSource(value); setPage(1); }} options={[{ value: 'library', label: 'Ingredients' }, { value: 'usda', label: 'Import from USDA' }]} />
      {source === 'usda' && <p className="text-xs text-warm-500">Search USDA foods. Selecting a result saves it to Ingredients and adds it to this menu item. Review its suggested category and allergens in Food Library.</p>}
      {importError && <p role="alert" className="text-sm text-red-700">{importError}</p>}
    </fieldset>} />;
}
