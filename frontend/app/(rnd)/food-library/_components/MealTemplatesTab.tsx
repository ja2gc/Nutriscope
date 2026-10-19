"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/Button";
import { WeeklyMenuCalendar, MENU_SLOTS } from "@/components/ncp/WeeklyMenuCalendar";
import { MenuEditorSlots, MenuEditorActions, MenuEditorAction } from "@/components/ncp/MenuEditorSlots";
import { RecipeMenuItemProfile } from "@/components/ncp/MenuItemProfile";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { MenuItemPicker } from "@/components/ncp/MenuItemPicker";
import { useUnsavedChanges } from "@/hooks/useUnsavedChanges";
import { ArrowLeft } from "lucide-react";
import { formatMealPlanAmount as formatTemplateDisplayNumber } from "@/lib/mealPlanNutrition";
import { fetchRecipes, type Recipe } from "@/services/foodLibraryService";
import {
  deleteMealPlanTemplate, fetchMealPlanTemplate, fetchMealPlanTemplates,
  saveLibraryMealPlanTemplate, type LibraryTemplateLine, type MealPlanTemplate, type NutrientSnapshot,
} from "@/services/mealPlanService";

const MEALS = MENU_SLOTS;

type EditorLine = LibraryTemplateLine & { key: number; itemName: string; snapshot?: NutrientSnapshot | null };
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
  const pickerTrigger = useRef<HTMLButtonElement | null>(null);
  const profileTrigger = useRef<HTMLButtonElement | null>(null);
  const [profile, setProfile] = useState<{ id: string; name: string; snapshot?: NutrientSnapshot | null } | null>(null);

  function closeProfile() {
    setProfile(null);
    profileTrigger.current?.focus();
  }

  useEffect(() => {
    if (!profile && profileTrigger.current) {
      const label = profileTrigger.current.getAttribute('aria-label');
      Array.from(document.querySelectorAll<HTMLButtonElement>('button')).find((button) => button.getAttribute('aria-label') === label)?.focus();
    }
  }, [profile]);

  function openProfile(line: EditorLine, trigger: HTMLButtonElement) {
    if (!line.recipe_id) return;
    profileTrigger.current = trigger;
    setProfile({ id: line.recipe_id, name: line.itemName, snapshot: line.snapshot });
  }

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
      return false;
    }
    if (draft.lines.some((line) => !Number.isFinite(line.quantity) || line.quantity < 0.01 || Number(line.quantity.toFixed(2)) !== line.quantity || line.unit !== "serving" || line.food_item_id || !line.recipe_id)) {
      setError("Each line needs a Menu Item and positive servings with at most two decimals. Replace legacy Ingredient lines before saving.");
      return false;
    }
    setSaving(true);
    setError(null);
    try {
      await saveLibraryMealPlanTemplate({
        name: draft.name.trim(),
        description: draft.description.trim() || null,
        lines: draft.lines.map(({ source_template_item_id, day_of_week, meal_type, food_item_id, recipe_id, quantity, unit }) => ({
          ...(source_template_item_id ? { source_template_item_id } : {}), day_of_week, meal_type, food_item_id, recipe_id, quantity, unit: unit.trim(),
        })),
      }, draft.id);
      guard.allowNavigation();
      onSaved();
      return true;
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to save template.");
      return false;
    } finally {
      setSaving(false);
    }
  }

  const guard = useUnsavedChanges({ dirty: !draft.readOnly && JSON.stringify(draft) !== JSON.stringify(initial), onSave: save });

  if (profile) return <RecipeMenuItemProfile id={profile.id} name={profile.name} snapshot={profile.snapshot} onBack={closeProfile} />;

  return (
    <section aria-label={draft.readOnly ? "View meal template" : draft.id ? "Edit meal template" : "Create meal template"}>
      {guard.dialog}
      <div className="w-full rounded-2xl border border-warm-200 bg-white p-4 sm:p-6">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-3"><button type="button" aria-label="Back to templates" onClick={() => guard.requestLeave(onClose)} className="rounded-lg border border-warm-200 p-2 text-warm-500 hover:bg-warm-50"><ArrowLeft className="h-4 w-4" /></button><h3 className="text-lg font-bold text-warm-900">{draft.readOnly ? "View meal template" : draft.id ? "Edit meal template" : "Create meal template"}</h3></div>
          <div className="flex flex-wrap gap-2">
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
          <WeeklyMenuCalendar renderCell={(cellDay, cellMeal) => {
            const lines = draft.lines.filter((line) => line.day_of_week === cellDay && line.meal_type === cellMeal);
            return <MenuEditorSlots
            itemActions={lines.map((line) => line.recipe_id ? { label: `View ${line.itemName}`, onOpen: (trigger) => void openProfile(line, trigger) } : undefined)}
            items={lines.map((line) => <div key={line.key} className="flex h-full flex-col justify-between gap-1">
              <MenuEditorActions>
                {!draft.readOnly && <MenuEditorAction kind="remove" label={`Remove ${line.itemName}`} onClick={() => setDraft((current) => ({ ...current, lines: current.lines.filter((row) => row.key !== line.key) }))} />}
              </MenuEditorActions>
              <div className="flex shrink-0 items-start gap-1">
                <p className="line-clamp-2 w-full text-center text-xs font-semibold leading-4 text-warm-800">{line.itemName}</p>
              </div>
              <p className="text-xs text-warm-600">{formatTemplateDisplayNumber(line.quantity)} {line.unit === "serving" && Number(line.quantity) !== 1 ? "servings" : line.unit}</p>
            </div>)}
            addLabel="Add item"
            addAriaLabel={`Add item to ${cellDay} ${MEALS.find(([key]) => key === cellMeal)?.[1]}`}
            readOnly={draft.readOnly}
            onAdd={(event) => { pickerTrigger.current = event.currentTarget; setDay(cellDay); setMeal(cellMeal); setQuery(""); setPage(1); setMatches([]); setPickerOpen(true); }}
          />; }} />
        </div>
        {pickerOpen && <MenuItemPicker title={`${day} ${MEALS.find(([key]) => key === meal)?.[1]}`} query={query} onQueryChange={(value) => { setQuery(value); setPage(1); setMatches([]); }} loading={searching} error={searchError} items={matches.map((item) => ({ id: item.id, name: item.name, detail: `${item.category} · Recipe makes ${formatTemplateDisplayNumber(item.servings)} ${item.servings === 1 ? "serving" : "servings"}` }))} onSelect={(id) => { const item = matches.find((entry) => entry.id === id); if (item) addItem(item); }} onClose={closePicker} meta={meta} page={page} onPageChange={setPage} />}
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
          source_template_item_id: item.id,
          day_of_week: day.day_of_week,
          meal_type: day.meal_type,
          food_item_id: item.food_item_id,
          recipe_id: item.recipe_id,
          itemName: item.nutrient_snapshot?.name ?? item.food_name ?? "Unavailable item",
          snapshot: item.nutrient_snapshot,
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
          <tbody>{templates.map((template) => <tr key={template.id} className="border-b border-warm-100 last:border-0"><td className="px-4 py-3 font-semibold text-warm-800">{template.name}</td><td className="px-4 py-3 text-warm-500">{new Date(template.created_at).toLocaleDateString()}</td><td className="sticky right-0 z-10 min-w-[84px] bg-white px-3 py-3 shadow-[-6px_0_8px_-8px_#000]"><div className="flex justify-end gap-0.5"><MenuEditorAction kind="view" label={`Edit ${template.name}`} disabled={opening !== null} onClick={() => void openEdit(template)} /><MenuEditorAction kind="remove" label={`Delete ${template.name}`} onClick={() => setDeleteTarget(template)} /></div></td></tr>)}</tbody>
        </table>}
      <Pagination meta={meta} page={page} onPageChange={setPage} />
    </div>
    {deleteTarget && <div className="rounded-xl border border-red-200 bg-red-50 p-4" role="dialog" aria-label="Delete meal template"><p className="text-sm text-red-800">Delete “{deleteTarget.name}”?</p><div className="mt-3 flex gap-2"><Button variant="secondary" onClick={() => setDeleteTarget(null)}>Cancel</Button><Button variant="danger" onClick={() => void remove()} disabled={deleting}>{deleting ? "Deleting…" : "Delete template"}</Button></div></div>}
  </div>;
}
