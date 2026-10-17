"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";
import { Eye, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { WeeklyMenuCalendar, MENU_SLOTS } from "@/components/ncp/WeeklyMenuCalendar";
import { MenuEditorSlots } from "@/components/ncp/MenuEditorSlots";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import SearchInput from "@/components/ui/SearchInput";
import { formatMealPlanAmount as formatTemplateDisplayNumber } from "@/lib/mealPlanNutrition";
import { fetchRecipeById, fetchRecipes, type Recipe } from "@/services/foodLibraryService";
import {
  deleteMealPlanTemplate, fetchMealPlanTemplate, fetchMealPlanTemplates,
  saveLibraryMealPlanTemplate, type LibraryTemplateLine, type MealPlanTemplate,
} from "@/services/mealPlanService";

const MEALS = MENU_SLOTS;

type EditorLine = LibraryTemplateLine & { key: number; itemName: string };
type EditorState = { id?: string; name: string; description: string; lines: EditorLine[]; readOnly?: boolean };
let nextLineKey = 0;

function TemplateEditor({ initial, onClose, onSaved }: {
  initial: EditorState;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [draft, setDraft] = useState(initial);
  const [day, setDay] = useState("Monday");
  const [meal, setMeal] = useState("breakfast");
  const [query, setQuery] = useState("");
  const [pickerOpen, setPickerOpen] = useState(false);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [matches, setMatches] = useState<Recipe[]>([]);
  const [searching, setSearching] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const searchInputId = useId();
  const pickerTrigger = useRef<HTMLButtonElement | null>(null);
  const profileTrigger = useRef<HTMLButtonElement | null>(null);
  const profileRequest = useRef(0);
  const [profile, setProfile] = useState<{ name: string; recipe?: Recipe; error?: string } | null>(null);

  function closeProfile() {
    profileRequest.current++;
    setProfile(null);
    profileTrigger.current?.focus();
  }

  async function openProfile(line: EditorLine, trigger: HTMLButtonElement) {
    if (!line.recipe_id) return;
    profileTrigger.current = trigger;
    const request = ++profileRequest.current;
    setProfile({ name: line.itemName });
    try {
      const recipe = await fetchRecipeById(line.recipe_id);
      if (profileRequest.current === request) setProfile({ name: line.itemName, recipe });
    } catch (cause) {
      if (profileRequest.current === request) setProfile({ name: line.itemName, error: cause instanceof Error ? cause.message : 'Could not load Menu Item. Try again.' });
    }
  }

  useEffect(() => {
    if (pickerOpen) document.getElementById(searchInputId)?.focus();
  }, [pickerOpen, searchInputId]);

  useEffect(() => {
    if (!pickerOpen) return;
    let cancelled = false;
    setSearching(true);
    setSearchError(null);
    const timer = window.setTimeout(async () => {
      try {
        const result = await fetchRecipes(query.trim(), "all", page, 10);
        if (!cancelled) { setMatches(result.data); setMeta(result.meta ?? null); }
      } catch (cause) {
        if (!cancelled) setSearchError(cause instanceof Error ? cause.message : "Search failed. Try again.");
      } finally {
        if (!cancelled) setSearching(false);
      }
    }, 200);
    return () => { window.clearTimeout(timer); cancelled = true; };
  }, [query, page, pickerOpen]);

  function closePicker() {
    setPickerOpen(false);
    pickerTrigger.current?.focus();
  }

  function addItem(item: Recipe) {
    const line: EditorLine = {
      key: ++nextLineKey,
      day_of_week: day,
      meal_type: meal,
      food_item_id: null,
      recipe_id: item.id,
      itemName: item.name,
      quantity: 1,
      unit: "serving",
    };
    setDraft((current) => ({ ...current, lines: [...current.lines, line] }));
    setQuery("");
    setMatches([]);
    closePicker();
  }

  async function save() {
    if (!draft.name.trim() || draft.lines.length === 0) {
      setError("Enter a name and add at least one Menu Item.");
      return;
    }
    if (draft.lines.some((line) => !Number.isFinite(line.quantity) || line.quantity < 0.01 || Number(line.quantity.toFixed(2)) !== line.quantity || line.unit !== "serving" || line.food_item_id || !line.recipe_id)) {
      setError("Each line needs a Menu Item and positive servings with at most two decimals. Replace legacy Ingredient lines before saving.");
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await saveLibraryMealPlanTemplate({
        name: draft.name.trim(),
        description: draft.description.trim() || null,
        lines: draft.lines.map(({ day_of_week, meal_type, food_item_id, recipe_id, quantity, unit }) => ({
          day_of_week, meal_type, food_item_id, recipe_id, quantity, unit: unit.trim(),
        })),
      }, draft.id);
      onSaved();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to save template.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <section aria-label={draft.readOnly ? "View meal template" : draft.id ? "Edit meal template" : "Create meal template"}>
      <div className="w-full rounded-2xl border border-warm-200 bg-white p-4 sm:p-6">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-bold text-warm-900">{draft.readOnly ? "View meal template" : draft.id ? "Edit meal template" : "Create meal template"}</h3>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" onClick={onClose}>Back to templates</Button>
            {!draft.readOnly && <Button variant="primary" onClick={() => void save()} disabled={saving}>{saving ? "Saving…" : "Save template"}</Button>}
          </div>
        </div>
        {error && <p role="alert" className="mt-4 text-sm text-red-700">{error}</p>}
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-semibold text-warm-700">Template name
            <input readOnly={draft.readOnly} value={draft.name} onChange={(event) => setDraft({ ...draft, name: event.target.value })} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2" maxLength={255} />
          </label>
          <label className="text-sm font-semibold text-warm-700">Description
            <input readOnly={draft.readOnly} value={draft.description} onChange={(event) => setDraft({ ...draft, description: event.target.value })} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2" maxLength={2000} />
          </label>
        </div>

        <div className="mt-5 space-y-2">
          <WeeklyMenuCalendar renderCell={(cellDay, cellMeal) => <MenuEditorSlots
            items={draft.lines.filter((line) => line.day_of_week === cellDay && line.meal_type === cellMeal).map((line) => <div key={line.key} className="flex h-full flex-col justify-between gap-1">
              {!draft.readOnly && <div data-menu-editor-actions className="flex shrink-0 justify-start"><button onClick={() => setDraft((current) => ({ ...current, lines: current.lines.filter((row) => row.key !== line.key) }))} aria-label={`Remove ${line.itemName}`} className="flex h-7 w-7 shrink-0 items-center justify-center rounded text-red-600 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button></div>}
              <div className="flex min-h-0 items-start gap-1">
                {line.recipe_id ? <button type="button" title={line.itemName} aria-label={`View ${line.itemName}`} onClick={(event) => void openProfile(line, event.currentTarget)} className="line-clamp-2 min-h-0 flex-1 overflow-hidden break-words text-left text-xs font-semibold leading-4 text-warm-800 hover:text-emerald-700">{line.itemName}</button> : <p className="line-clamp-2 text-xs font-semibold text-warm-800">{line.itemName}</p>}
              </div>
              {draft.readOnly ? <p className="text-xs text-warm-600">{formatTemplateDisplayNumber(line.quantity)} {line.unit === "serving" && Number(line.quantity) !== 1 ? "servings" : line.unit}</p> : <div className="flex items-end gap-1">
                <label className="min-w-0 flex-1 text-xs leading-3 text-warm-600">Qty<input aria-label={`Servings of ${line.itemName}`} type="number" min="0.01" step="0.01" value={line.quantity} onChange={(event) => setDraft((current) => ({ ...current, lines: current.lines.map((row) => row.key === line.key ? { ...row, quantity: Number(event.target.value) } : row) }))} className="mt-0.5 h-7 w-full rounded border border-warm-200 px-1 text-xs" /></label>
                <label className="min-w-0 flex-1 text-xs leading-3 text-warm-600">Unit<select aria-label={`Unit for ${line.itemName}`} value={line.unit} onChange={(event) => setDraft((current) => ({ ...current, lines: current.lines.map((row) => row.key === line.key ? { ...row, unit: event.target.value } : row) }))} className="mt-0.5 h-7 w-full rounded border border-warm-200 px-1 text-xs">{line.unit !== "serving" && <option value={line.unit} disabled>{line.unit} (legacy)</option>}<option value="serving">serving</option></select></label>
              </div>}
            </div>)}
            addLabel="Add item"
            addAriaLabel={`Add item to ${cellDay} ${MEALS.find(([key]) => key === cellMeal)?.[1]}`}
            readOnly={draft.readOnly}
            onAdd={(event) => { pickerTrigger.current = event.currentTarget; setDay(cellDay); setMeal(cellMeal); setQuery(""); setPage(1); setMatches([]); setPickerOpen(true); }}
          />} />
        </div>
        {profile && <div role="dialog" aria-modal="true" aria-label="Menu Item profile" onKeyDown={(event) => { if (event.key === 'Escape') closeProfile(); }} className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
          <div className="flex max-h-[80vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-warm-100 p-4"><h3 className="break-words font-bold text-warm-900">{profile.name}</h3><Button variant="secondary" onClick={closeProfile}>Back to template</Button></div>
            <div className="space-y-3 overflow-y-auto p-4">
              {profile.error ? <p role="alert" className="text-sm text-red-700">{profile.error}</p> : !profile.recipe ? <p role="status" className="text-sm text-warm-500">Loading Menu Item…</p> : <>
                <p className="text-sm text-warm-600">Recipe makes: {formatTemplateDisplayNumber(profile.recipe.servings)} servings</p>
                <table className="w-full text-left text-sm"><thead><tr><th className="p-2">Ingredient</th><th className="p-2">Amount</th></tr></thead><tbody>{profile.recipe.ingredients.map((ingredient) => <tr key={ingredient.id} className="border-t border-warm-100"><td className="break-words p-2">{ingredient.food_item?.name ?? '—'}</td><td className="whitespace-nowrap p-2">{formatTemplateDisplayNumber(ingredient.quantity)} {ingredient.unit}</td></tr>)}</tbody></table>
                {profile.recipe.prep_notes && <p className="whitespace-pre-wrap text-sm text-warm-600">{profile.recipe.prep_notes}</p>}
                <a href={`/food-library/recipes/${profile.recipe.id}`} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center text-sm font-semibold text-emerald-700 underline">Open Menu Item</a>
              </>}
            </div>
          </div>
        </div>}
        {pickerOpen && <div role="dialog" aria-modal="true" aria-label="Add Menu Item" onKeyDown={(event) => { if (event.key === 'Escape') closePicker(); }} className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
          <div className="flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl bg-white shadow-xl">
            <div className="flex items-center justify-between border-b border-warm-100 p-4"><h3 className="font-bold text-warm-900">{day} {MEALS.find(([key]) => key === meal)?.[1]}</h3><Button variant="secondary" onClick={closePicker}>Close</Button></div>
            <div className="space-y-3 overflow-y-auto p-4">
              <SearchInput id={searchInputId} label="Search Menu Items to add" value={query} onChange={(value) => { setQuery(value); setPage(1); setMatches([]); }} loading={searching} />
              {searchError && <p role="alert" className="text-sm text-red-700">{searchError}</p>}
              {!searching && !searchError && matches.length === 0 && <p className="text-sm text-warm-500">No Menu Items found.</p>}
              {matches.map((item) => <button key={item.id} onClick={() => addItem(item)} className="block min-h-11 w-full rounded-lg border border-warm-200 px-3 py-2 text-left text-sm hover:bg-emerald-50"><span className="block break-words font-semibold">{item.name}</span><span className="text-xs capitalize text-warm-500">{item.category} · Recipe makes {formatTemplateDisplayNumber(item.servings)} servings</span></button>)}
              <Pagination meta={meta} page={page} onPageChange={setPage} />
            </div>
          </div>
        </div>}
      </div>
    </section>
  );
}

export function MealTemplatesTab() {
  const [templates, setTemplates] = useState<MealPlanTemplate[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editor, setEditor] = useState<EditorState | null>(null);
  const [opening, setOpening] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<MealPlanTemplate | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await fetchMealPlanTemplates(page);
      setTemplates(result.data);
      setMeta(result.meta);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load templates.");
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => { void load(); }, [load]);

  async function openEdit(template: MealPlanTemplate, readOnly = false) {
    setOpening(template.id);
    setError(null);
    try {
      const detail = await fetchMealPlanTemplate(template.id);
      if (!detail) throw new Error("Template could not be opened.");
      setEditor({
        id: detail.id,
        readOnly,
        name: detail.name,
        description: detail.description ?? "",
        lines: detail.days.flatMap((day) => day.items.map((item) => ({
          key: ++nextLineKey,
          day_of_week: day.day_of_week,
          meal_type: day.meal_type,
          food_item_id: item.food_item_id,
          recipe_id: item.recipe_id,
          itemName: item.food_name ?? "Unavailable item",
          quantity: Number(item.quantity),
          unit: item.unit,
        }))),
      });
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Template could not be opened.");
    } finally {
      setOpening(null);
    }
  }

  async function remove() {
    if (!deleteTarget) return;
    setDeleting(true);
    setError(null);
    try {
      await deleteMealPlanTemplate(deleteTarget.id);
      setDeleteTarget(null);
      await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to delete template.");
    } finally {
      setDeleting(false);
    }
  }

  if (editor) return <TemplateEditor initial={editor} onClose={() => setEditor(null)} onSaved={() => { setEditor(null); void load(); }} />;

  return <div className="space-y-4">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-sm text-warm-600">Create templates here or save one from an Intervention menu plan.</p>
      <Button variant="primary" onClick={() => setEditor({ name: "", description: "", lines: [] })}>New Template</Button>
    </div>
    {error && <p role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    <div className="overflow-x-auto rounded-xl border border-warm-200 bg-white" aria-label="Meal templates table">
      {loading ? <p className="p-5 text-sm text-warm-500">Loading templates…</p> : templates.length === 0 ? <p className="p-5 text-sm text-warm-500">No templates yet.</p> :
        <table className="w-full min-w-[480px] text-left text-sm">
          <thead><tr className="border-b border-warm-200 text-xs font-bold uppercase text-warm-500"><th className="px-4 py-3">Template</th><th className="px-4 py-3">Created</th><th className="sticky right-0 z-10 min-w-[84px] bg-white px-3 py-3 text-right shadow-[-6px_0_8px_-8px_#000]">Actions</th></tr></thead>
          <tbody>{templates.map((template) => <tr key={template.id} className="border-b border-warm-100 last:border-0"><td className="px-4 py-3 font-semibold text-warm-800">{template.name}</td><td className="px-4 py-3 text-warm-500">{new Date(template.created_at).toLocaleDateString()}</td><td className="sticky right-0 z-10 min-w-[84px] bg-white px-3 py-3 shadow-[-6px_0_8px_-8px_#000]"><div className="flex justify-end gap-1"><button onClick={() => void openEdit(template, true)} disabled={opening !== null} aria-label={`View ${template.name}`} className="rounded-lg p-2 text-warm-600 hover:bg-warm-100 disabled:opacity-50"><Eye className="h-4 w-4" /></button><button onClick={() => void openEdit(template)} disabled={opening !== null} aria-label={`Edit ${template.name}`} className="rounded-lg p-2 text-warm-600 hover:bg-warm-100 disabled:opacity-50"><Pencil className="h-4 w-4" /></button><button onClick={() => setDeleteTarget(template)} aria-label={`Delete ${template.name}`} className="rounded-lg p-2 text-red-600 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button></div></td></tr>)}</tbody>
        </table>}
      <Pagination meta={meta} page={page} onPageChange={setPage} />
    </div>
    {deleteTarget && <div className="rounded-xl border border-red-200 bg-red-50 p-4" role="dialog" aria-label="Delete meal template"><p className="text-sm text-red-800">Delete “{deleteTarget.name}”?</p><div className="mt-3 flex gap-2"><Button variant="secondary" onClick={() => setDeleteTarget(null)}>Cancel</Button><Button variant="danger" onClick={() => void remove()} disabled={deleting}>{deleting ? "Deleting…" : "Delete template"}</Button></div></div>}
  </div>;
}
