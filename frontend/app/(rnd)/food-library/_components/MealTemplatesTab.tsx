"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import SearchInput from "@/components/ui/SearchInput";
import { fetchFoodItems, fetchRecipes, type FoodItem, type Recipe } from "@/services/foodLibraryService";
import {
  deleteMealPlanTemplate, fetchMealPlanTemplate, fetchMealPlanTemplates,
  saveLibraryMealPlanTemplate, type LibraryTemplateLine, type MealPlanTemplate,
} from "@/services/mealPlanService";

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
const MEALS = [
  ["breakfast", "Breakfast"], ["am_snack", "AM snack"], ["lunch", "Lunch"],
  ["pm_snack", "PM snack"], ["dinner", "Dinner"],
];

type EditorLine = LibraryTemplateLine & { key: number; itemName: string };
type EditorState = { id?: string; name: string; description: string; lines: EditorLine[] };
let nextLineKey = 0;

function TemplateEditor({ initial, onClose, onSaved }: {
  initial: EditorState;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [draft, setDraft] = useState(initial);
  const [day, setDay] = useState("Monday");
  const [meal, setMeal] = useState("breakfast");
  const [kind, setKind] = useState<"food" | "recipe">("food");
  const [query, setQuery] = useState("");
  const [matches, setMatches] = useState<Array<FoodItem | Recipe>>([]);
  const [searching, setSearching] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const searchId = useRef(0);

  useEffect(() => {
    const id = ++searchId.current;
    if (!query.trim()) {
      setMatches([]);
      setSearching(false);
      return;
    }
    setSearching(true);
    const timer = window.setTimeout(async () => {
      try {
        const result = kind === "food"
          ? await fetchFoodItems(query.trim(), "all", 1, 10)
          : await fetchRecipes(query.trim(), "all", 1, 10);
        if (id === searchId.current) setMatches(result.data);
      } catch (cause) {
        if (id === searchId.current) setError(cause instanceof Error ? cause.message : "Search failed.");
      } finally {
        if (id === searchId.current) setSearching(false);
      }
    }, 200);
    return () => window.clearTimeout(timer);
  }, [kind, query]);

  function addItem(item: FoodItem | Recipe) {
    const food = kind === "food" ? item as FoodItem : null;
    const line: EditorLine = {
      key: ++nextLineKey,
      day_of_week: day,
      meal_type: meal,
      food_item_id: food?.id ?? null,
      recipe_id: food ? null : item.id,
      itemName: item.name,
      quantity: 1,
      unit: food?.serving_unit || "serving",
    };
    setDraft((current) => ({ ...current, lines: [...current.lines, line] }));
    setQuery("");
    setMatches([]);
  }

  async function save() {
    if (!draft.name.trim() || draft.lines.length === 0) {
      setError("Enter a name and add at least one food or recipe.");
      return;
    }
    if (draft.lines.some((line) => !Number.isFinite(line.quantity) || line.quantity <= 0 || !line.unit.trim() || (!line.food_item_id && !line.recipe_id))) {
      setError("Each line needs an item, positive quantity, and unit.");
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
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-3 sm:p-6" role="dialog" aria-modal="true" aria-label={draft.id ? "Edit meal template" : "Create meal template"}>
      <div className="mx-auto my-2 w-full max-w-3xl rounded-2xl bg-white p-4 shadow-xl sm:p-6">
        <div className="flex items-center justify-between gap-3">
          <h3 className="text-lg font-bold text-warm-900">{draft.id ? "Edit meal template" : "Create meal template"}</h3>
          <button onClick={onClose} className="rounded-lg px-2 py-1 text-sm text-warm-500 hover:bg-warm-100">Close</button>
        </div>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="text-sm font-semibold text-warm-700">Template name
            <input value={draft.name} onChange={(event) => setDraft({ ...draft, name: event.target.value })} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2" maxLength={255} />
          </label>
          <label className="text-sm font-semibold text-warm-700">Description
            <input value={draft.description} onChange={(event) => setDraft({ ...draft, description: event.target.value })} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2" maxLength={2000} />
          </label>
        </div>

        <div className="mt-5 border-t border-warm-100 pt-4">
          <h4 className="text-sm font-bold text-warm-800">Add foods or recipes</h4>
          <div className="mt-2 grid gap-2 sm:grid-cols-[1fr_1fr_1fr]">
            <label className="text-xs font-semibold text-warm-600">Day
              <select value={day} onChange={(event) => setDay(event.target.value)} className="mt-1 w-full rounded-lg border border-warm-200 px-2 py-2">{DAYS.map((value) => <option key={value}>{value}</option>)}</select>
            </label>
            <label className="text-xs font-semibold text-warm-600">Meal
              <select value={meal} onChange={(event) => setMeal(event.target.value)} className="mt-1 w-full rounded-lg border border-warm-200 px-2 py-2">{MEALS.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
            </label>
            <label className="text-xs font-semibold text-warm-600">Item type
              <select value={kind} onChange={(event) => { setKind(event.target.value as "food" | "recipe"); setMatches([]); }} className="mt-1 w-full rounded-lg border border-warm-200 px-2 py-2"><option value="food">Food</option><option value="recipe">Recipe</option></select>
            </label>
          </div>
          <div className="mt-3"><SearchInput label={`Search ${kind}s to add`} value={query} onChange={setQuery} loading={searching} /></div>
          {matches.length > 0 && <div className="mt-2 max-h-40 overflow-y-auto rounded-lg border border-warm-200">
            {matches.map((item) => <button key={item.id} onClick={() => addItem(item)} className="block w-full border-b border-warm-100 px-3 py-2 text-left text-sm hover:bg-emerald-50">{item.name}</button>)}
          </div>}
        </div>

        <div className="mt-5 space-y-2">
          <h4 className="text-sm font-bold text-warm-800">Template items</h4>
          {draft.lines.length === 0 && <p className="text-sm text-warm-500">Add at least one item.</p>}
          {draft.lines.map((line) => <div key={line.key} className="grid gap-2 rounded-lg border border-warm-200 p-3 sm:grid-cols-[1fr_5rem_7rem_2rem] sm:items-center">
            <div className="min-w-0"><p className="truncate text-sm font-semibold text-warm-800">{line.itemName}</p><p className="text-xs text-warm-500">{line.day_of_week} · {MEALS.find(([key]) => key === line.meal_type)?.[1]}</p></div>
            <label className="text-xs text-warm-600">Quantity<input type="number" min="0.01" step="0.01" value={line.quantity} onChange={(event) => setDraft((current) => ({ ...current, lines: current.lines.map((row) => row.key === line.key ? { ...row, quantity: Number(event.target.value) } : row) }))} className="mt-1 w-full rounded-lg border border-warm-200 px-2 py-1.5" /></label>
            <label className="text-xs text-warm-600">Unit<input value={line.unit} onChange={(event) => setDraft((current) => ({ ...current, lines: current.lines.map((row) => row.key === line.key ? { ...row, unit: event.target.value } : row) }))} className="mt-1 w-full rounded-lg border border-warm-200 px-2 py-1.5" /></label>
            <button onClick={() => setDraft((current) => ({ ...current, lines: current.lines.filter((row) => row.key !== line.key) }))} aria-label={`Remove ${line.itemName}`} className="rounded-lg p-1.5 text-red-600 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button>
          </div>)}
        </div>
        {error && <p role="alert" className="mt-4 text-sm text-red-700">{error}</p>}
        <div className="mt-5 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => void save()} disabled={saving}>{saving ? "Saving…" : "Save template"}</Button>
        </div>
      </div>
    </div>
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

  async function openEdit(template: MealPlanTemplate) {
    setOpening(template.id);
    setError(null);
    try {
      const detail = await fetchMealPlanTemplate(template.id);
      if (!detail) throw new Error("Template could not be opened.");
      setEditor({
        id: detail.id,
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
          <tbody>{templates.map((template) => <tr key={template.id} className="border-b border-warm-100 last:border-0"><td className="px-4 py-3 font-semibold text-warm-800">{template.name}</td><td className="px-4 py-3 text-warm-500">{new Date(template.created_at).toLocaleDateString()}</td><td className="sticky right-0 z-10 min-w-[84px] bg-white px-3 py-3 shadow-[-6px_0_8px_-8px_#000]"><div className="flex justify-end gap-1"><button onClick={() => void openEdit(template)} disabled={opening !== null} aria-label={`Edit ${template.name}`} className="rounded-lg p-2 text-warm-600 hover:bg-warm-100 disabled:opacity-50"><Pencil className="h-4 w-4" /></button><button onClick={() => setDeleteTarget(template)} aria-label={`Delete ${template.name}`} className="rounded-lg p-2 text-red-600 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button></div></td></tr>)}</tbody>
        </table>}
      <Pagination meta={meta} page={page} onPageChange={setPage} />
    </div>
    {deleteTarget && <div className="rounded-xl border border-red-200 bg-red-50 p-4" role="dialog" aria-label="Delete meal template"><p className="text-sm text-red-800">Delete “{deleteTarget.name}”?</p><div className="mt-3 flex gap-2"><Button variant="secondary" onClick={() => setDeleteTarget(null)}>Cancel</Button><Button variant="danger" onClick={() => void remove()} disabled={deleting}>{deleting ? "Deleting…" : "Delete template"}</Button></div></div>}
    {editor && <TemplateEditor initial={editor} onClose={() => setEditor(null)} onSaved={() => { setEditor(null); void load(); }} />}
  </div>;
}
