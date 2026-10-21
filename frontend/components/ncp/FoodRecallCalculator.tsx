"use client";

import { useEffect, useId, useImperativeHandle, useRef, useState, type Ref } from 'react';
import { ArrowLeft, ChevronDown, ChevronUp, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import ButtonFilterGroup from '@/components/ui/ButtonFilterGroup';
import type { PaginationMeta } from '@/components/ui/Pagination';
import { useUnsavedChanges, type EditorLeaveHandle } from '@/hooks/useUnsavedChanges';
import { fetchFoodItems, fetchRecipes, fetchRecipeById, type FoodItem } from '@/services/foodLibraryService';
import { addFoodRecallDay, foodRecallCoverage, foodRecallDayLabel, emptyFoodRecall, foodRecallAverage, foodRecallDayTotals, foodRecallItemNutrients, recallItemFromFood, recallItemFromRecipe, type FoodRecallItem, type FoodRecallPlan } from '@/lib/foodRecall';
import { formatMealPlanAmount } from '@/lib/mealPlanNutrition';
import { MenuItemPicker } from './MenuItemPicker';
import { MenuEditorSlots } from './MenuEditorSlots';
import { FoodRecallItemEditor } from './FoodRecallItemEditor';
import MicronutrientToggle from './MicronutrientToggle';
import { MicronutrientTotals, type MicronutrientLimits } from './MicronutrientTotals';
import MacroTrackerBar from '@/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar';

export type FoodRecallHandle = EditorLeaveHandle & { savePlan: () => Promise<boolean>; isDirty: () => boolean };
type Props = {
  value?: FoodRecallPlan | null;
  onSave?: (plan: FoodRecallPlan) => void;
  readOnly?: boolean;
  compact?: boolean;
  disabled?: boolean;
  defaultMicros?: string[];
  limits?: MicronutrientLimits;
  onDirtyChange?: (dirty: boolean) => void;
  leaveRef?: Ref<FoodRecallHandle>;
};
const clone = <T,>(value: T): T => structuredClone(value);

export function FoodRecallCalculator({ value, onSave, readOnly = false, compact = false, disabled = false, defaultMicros = [], limits, onDirtyChange, leaveRef }: Props) {
  const id = useId();
  const [initial] = useState(() => emptyFoodRecall(0, defaultMicros));
  const [draft, setDraft] = useState(() => clone(value ?? initial));
  const accepted = useRef(value ?? initial);
  useEffect(() => { accepted.current = value ?? initial; }, [value, initial]);
  const [open, setOpen] = useState(false);
  const [day, setDay] = useState(0);
  const [dayPage, setDayPage] = useState(0);
  const [average, setAverage] = useState(false);
  const [edit, setEdit] = useState<{ day: number; index: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [picker, setPicker] = useState<number | null>(null);
  const [source, setSource] = useState('recipe');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [results, setResults] = useState<{ id: string; name: string; detail?: string }[]>([]);
  const foods = useRef<FoodItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [searchError, setSearchError] = useState<string | null>(null);
  const dirty = !readOnly && JSON.stringify(draft) !== JSON.stringify(value ?? initial);
  useEffect(() => { onDirtyChange?.(dirty); }, [dirty, onDirtyChange]);

  async function savePlan() {
    if (readOnly) return true;
    if (!foodRecallAverage(draft)) { setError('Add at least one item to every selected day, using valid quantities and units.'); setOpen(true); setEdit(null); return false; }
    const plan = clone(draft);
    accepted.current = plan;
    onSave?.(plan);
    setError(null); setNotice('Plan updated. Save the entry to keep your changes.');
    return true;
  }
  const guard = useUnsavedChanges({ dirty, onSave: savePlan, watchNavigation: false });
  useImperativeHandle(leaveRef, () => ({ requestLeave: guard.requestLeave, savePlan, isDirty: () => dirty }));
  function close() {
    guard.requestLeave(() => { setDraft(clone(accepted.current)); setOpen(false); setEdit(null); setError(null); setNotice(null); setDay(0); setDayPage(0); });
  }

  useEffect(() => {
    if (picker === null) return;
    let active = true;
    const timer = setTimeout(async () => {
      setLoading(true); setSearchError(null);
      try {
        const response = source === 'food' ? await fetchFoodItems(query, undefined, page) : await fetchRecipes(query, 'all', page);
        if (!active) return;
        if (source === 'food') foods.current = response.data as FoodItem[];
        setResults(response.data.map(item => ({ id: item.id, name: item.name })));
        setMeta(response.meta ?? null);
      } catch (cause) { if (active) { setResults([]); setSearchError(cause instanceof Error ? cause.message : 'Could not load Food Library. Try again.'); } }
      finally { if (active) setLoading(false); }
    }, 200);
    return () => { active = false; clearTimeout(timer); };
  }, [picker, source, query, page]);

  async function add(sourceId: string) {
    if (picker === null || busy || readOnly) return;
    setBusy(sourceId); setSearchError(null);
    try {
      const item = source === 'food' ? recallItemFromFood(foods.current.find(food => food.id === sourceId)!) : recallItemFromRecipe(await fetchRecipeById(sourceId));
      if (!item || !foodRecallItemNutrients(item)) throw new Error('This item needs a valid measured nutrient baseline before it can be used.');
      setDraft(current => ({ ...current, days: current.days.map((entry, index) => index === picker ? { ...entry, items: [...entry.items, item] } : entry) }));
      setPicker(null); setError(null); setNotice(null);
    } catch (cause) { setSearchError(cause instanceof Error ? cause.message : 'Could not add this item.'); }
    finally { setBusy(null); }
  }
  function updateItem(item: FoodRecallItem) {
    if (!edit || readOnly) return;
    setDraft(current => ({ ...current, days: current.days.map((entry, index) => index === edit.day ? { ...entry, items: entry.items.map((row, itemIndex) => itemIndex === edit.index ? item : row) } : entry) }));
  }
  const allItems = average ? draft.days.flatMap(entry => entry.items) : draft.days[day]?.items ?? [];
  const totals = average ? foodRecallAverage(draft) : foodRecallDayTotals(allItems);
  const microTotals = Object.fromEntries(draft.displayed_micros.map(key => [key, allItems.length && allItems.every(item => item.nutrient_snapshot.micronutrients?.[key] != null) ? totals?.micronutrients[key] : undefined]));
  const firstDay = dayPage * 7;
  const dayList = Array.from({ length: 7 }, (_, index) => firstDay + index);
  const canAddOnPage = !readOnly && !disabled && draft.days.length >= firstDay && draft.days.length < firstDay + 7;
  function addDay(kind: 'weekday' | 'weekend') {
    const next = addFoodRecallDay(draft, kind);
    setDraft(next.plan); setDay(next.index); setDayPage(Math.floor(next.index / 7));
    setAverage(false); setNotice(null); setError(null);
  }
  function removeDay(index: number) {
    const days = draft.days.filter((_, dayIndex) => dayIndex !== index);
    const selected = Math.max(0, Math.min(day > index ? day - 1 : day, days.length - 1));
    setDraft({ ...draft, days_count: days.length, days }); setDay(selected);
    setDayPage(Math.min(dayPage, Math.floor(Math.max(0, days.length - 1) / 7)));
    setNotice(null); setError(null);
  }
  const addDayButtons = <div className="flex flex-col items-start gap-2"><Button type="button" variant="secondary" onClick={() => addDay('weekday')}>Add Weekday</Button><Button type="button" variant="secondary" onClick={() => addDay('weekend')}>Add Weekend</Button></div>;
  function renderItems(index: number) {
    const items = draft.days[index].items;
    return <MenuEditorSlots compact readOnly={readOnly || disabled} emptyLabel="No items" addLabel="Add item" addAriaLabel={`Add item to ${foodRecallDayLabel(draft, index)}`}
      onAdd={() => { setPicker(index); setQuery(''); setPage(1); setResults([]); setLoading(true); }}
      itemActions={items.map((item, itemIndex) => ({ label: `${readOnly ? 'View' : 'Edit'} ${item.nutrient_snapshot.name}`, onOpen: () => { setEdit({ day: index, index: itemIndex }); setNotice(null); } }))}
      items={items.map(item => <div key={item.id} className="space-y-1">
        <p className="break-words text-xs font-bold text-warm-800">{item.nutrient_snapshot.name}</p>
        <p className="text-xs text-warm-500">{formatMealPlanAmount(item.quantity)} {item.unit} · {formatMealPlanAmount(foodRecallItemNutrients(item)?.calories)} kcal</p>
      </div>)} />;
  }

  if (readOnly && !value) return <p className="text-xs text-warm-500">No Food Recall calculation saved for this entry.</p>;

  return <section className="min-w-0 space-y-3" aria-label="Food Recall Calculator">
    {guard.dialog}
    {foodRecallCoverage(value) && <dl className="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-xs"><dt className="font-bold text-warm-600">Recall Coverage</dt><dd className="text-warm-800">{foodRecallCoverage(value)}</dd></dl>}
    {!open ? <div className="flex flex-wrap items-center justify-between gap-2">
      <button type="button" onClick={() => { setOpen(true); setNotice(null); }} aria-expanded={false} aria-controls={id} className="inline-flex min-h-10 items-center gap-1.5 text-xs font-bold text-emerald-700 hover:text-emerald-800 focus-visible:ring-2 focus-visible:ring-emerald-600"><ChevronDown className="h-4 w-4" />Show Food Recall Calculator</button>
    </div> : <div id={id} className="min-w-0 space-y-3 rounded-xl border border-warm-200 bg-white p-3">
      {edit ? <FoodRecallItemEditor key={draft.days[edit.day].items[edit.index].id} item={draft.days[edit.day].items[edit.index]} readOnly={readOnly || disabled} onChange={updateItem} onBack={() => setEdit(null)} onRemove={() => { setDraft(current => ({ ...current, days: current.days.map((entry, index) => index === edit.day ? { ...entry, items: entry.items.filter((_, index) => index !== edit.index) } : entry) })); setEdit(null); }} /> : <>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex min-w-0 items-center gap-2"><button type="button" aria-label="Back to food recall" title="Back to food recall" onClick={close} className="inline-flex min-h-10 min-w-10 shrink-0 items-center justify-center rounded-lg border border-warm-200 text-warm-500 hover:bg-warm-50 focus-visible:ring-2 focus-visible:ring-emerald-600"><ArrowLeft className="h-4 w-4" /></button><h3 className="text-sm font-bold text-warm-800">Food Recall Calculator</h3></div>
          <button type="button" onClick={close} aria-expanded={true} aria-controls={id} aria-label="Hide Food Recall Calculator" className="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg text-warm-500 hover:bg-warm-50 focus-visible:ring-2 focus-visible:ring-emerald-600"><ChevronUp className="h-4 w-4" /></button>
        </div>
        <div className="flex flex-wrap items-end justify-between gap-2">
          <p className="text-xs text-warm-500">{draft.days.length} {draft.days.length === 1 ? 'day' : 'days'}{readOnly ? ' · View only' : ''}</p>
          <MicronutrientToggle selected={draft.displayed_micros} onChange={keys => setDraft(current => ({ ...current, displayed_micros: keys }))} />
        </div>
        {!!draft.days.length && <ButtonFilterGroup size="sm" label="Nutrients" value={average ? 'average' : String(day)} options={[...draft.days.slice(firstDay, firstDay + 7).map((_, offset) => ({ value: String(firstDay + offset), label: foodRecallDayLabel(draft, firstDay + offset) })), { value: 'average', label: 'Daily average' }]} onChange={value => { setAverage(value === 'average'); if (value !== 'average') setDay(Number(value)); }} />}
        {totals ? <MacroTrackerBar showWithoutTargets targets={[
          { label: 'Energy', current: totals.calories, target: 0, unit: 'kcal' }, { label: 'Protein', current: totals.protein, target: 0, unit: 'g' },
          { label: 'Carbs', current: totals.carbs, target: 0, unit: 'g' }, { label: 'Fat', current: totals.fat, target: 0, unit: 'g' },
        ]} /> : <p className="text-xs text-warm-600">Add at least one item to every selected day to calculate the daily average.</p>}
        <MicronutrientTotals title={average ? `Daily average · ${draft.days_count} days` : draft.days.length ? `${foodRecallDayLabel(draft, day)} micronutrients` : 'Micronutrients'} selected={draft.displayed_micros} totals={microTotals} limits={limits} />
        <div className="flex min-w-0 items-stretch gap-1">
          {draft.days.length >= 7 && <button type="button" aria-label="Previous days" disabled={dayPage === 0} onClick={() => { setDayPage(dayPage - 1); setDay((dayPage - 1) * 7); }} className="shrink-0 rounded-lg text-warm-600 disabled:opacity-30 focus-visible:ring-2 focus-visible:ring-emerald-600"><ChevronLeft className="h-5 w-5" /></button>}
          <div className="min-w-0 flex-1 overflow-x-auto rounded-lg border border-warm-200"><table aria-label="Food recall days" className="w-full table-fixed border-collapse text-left" style={{ minWidth: compact ? 980 : 1092 }}>
            <thead><tr>{dayList.map(index => <th key={index} scope="col" aria-label={draft.days[index] ? foodRecallDayLabel(draft, index) : undefined} className="h-9 border-r border-warm-200 bg-warm-50 px-2 py-2 text-xs font-bold text-warm-600 last:border-r-0">{draft.days[index] && <div className="flex items-center justify-between gap-1"><span>{foodRecallDayLabel(draft, index)}</span>{!readOnly && <button type="button" disabled={disabled} aria-label={`Remove ${foodRecallDayLabel(draft, index)}`} onClick={() => removeDay(index)} className="inline-flex min-h-8 min-w-8 items-center justify-center rounded text-warm-500 hover:bg-warm-100 focus-visible:ring-2 focus-visible:ring-emerald-600"><X className="h-3 w-3" /></button>}</div>}</th>)}</tr></thead>
            <tbody><tr>{dayList.map(index => <td key={index} className="border-r border-t border-warm-200 p-2 align-top last:border-r-0">{draft.days[index] ? renderItems(index) : canAddOnPage && index === draft.days.length ? addDayButtons : null}</td>)}</tr></tbody>
          </table></div>
          {draft.days.length >= 7 && <button type="button" aria-label="Next days" disabled={dayPage >= Math.floor((draft.days.length - (readOnly ? 1 : 0)) / 7)} onClick={() => { setDayPage(dayPage + 1); setDay(Math.min((dayPage + 1) * 7, draft.days.length - 1)); }} className="shrink-0 rounded-lg text-warm-600 disabled:opacity-30 focus-visible:ring-2 focus-visible:ring-emerald-600"><ChevronRight className="h-5 w-5" /></button>}
        </div>
        {!readOnly && !disabled && !canAddOnPage && <div className="flex justify-end">{addDayButtons}</div>}
        {error && <p role="alert" className="text-xs text-red-700">{error}</p>}
        {notice && <p role="status" className="text-xs text-emerald-700">{notice}</p>}
        {!readOnly && <div className="flex flex-wrap items-center justify-between gap-2 border-t border-warm-100 pt-3">
          <Button type="button" variant="ghost" disabled={disabled} onClick={() => guard.requestLeave(() => { setDraft(emptyFoodRecall(0, draft.displayed_micros)); setDay(0); setDayPage(0); setAverage(false); setError(null); setNotice(null); })}>New calculation</Button>
          <div className="flex gap-2"><Button type="button" variant="secondary" onClick={close}>Cancel</Button><Button type="button" disabled={disabled} onClick={savePlan}>Save Plan</Button></div>
        </div>}
      </>}
    </div>}
    {picker !== null && <MenuItemPicker title={`Add to ${foodRecallDayLabel(draft, picker)}`} itemLabel={source === 'food' ? 'Ingredient' : 'Menu Item'} query={query} onQueryChange={value => { setQuery(value); setPage(1); }}
      loading={loading} error={searchError} items={results} onSelect={add} onClose={() => { if (!busy) setPicker(null); }} busyId={busy} meta={meta} page={page} onPageChange={setPage}
      filters={<ButtonFilterGroup label="Source" size="sm" value={source} onChange={value => { setSource(value); setQuery(''); setPage(1); setResults([]); setLoading(true); }} options={[{ value: 'recipe', label: 'Menu Items' }, { value: 'food', label: 'Ingredients' }]} />} />}
  </section>;
}
