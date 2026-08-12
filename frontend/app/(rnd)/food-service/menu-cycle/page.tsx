"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  CalendarDays, Plus, Search, X, Trash2, Save, Zap, Copy, BookmarkPlus,
  LayoutTemplate, ChevronLeft, AlertTriangle, RefreshCw, Pencil,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { useAuth } from "@/contexts/AuthContext";
import {
  DAYS, MEALS, MEAL_LABELS, Day, Meal,
  CycleListItem, MenuCycle, RecipeOption, FsItemOption, TemplateListItem, MenuSnapshot,
  listCycles, getCycle, saveCycle, deleteCycle, activateCycle,
  saveCycleAsTemplate, listRecipeOptions, listFsItemOptions, listTemplates, instantiateTemplate, deleteTemplate,
} from "@/services/menuCycleService";
import { setServedPopulation, listServiceLogs } from "@/services/consumptionService";

const cellKey = (d: Day, m: Meal) => `${d}|${m}`;
// Actual calendar date for a weekday column of a Monday-anchored cycle week.
const isoAddDays = (start: string, n: number) => {
  const d = new Date(`${start}T00:00:00`);
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
};

// Week label + Past/Current/Upcoming tag for a cycle, from its week_start_date.
const weekRange = (start: string | null, days = 7) => {
  if (!start) return "—";
  const s = new Date(`${start}T00:00:00`);
  const e = new Date(s); e.setDate(e.getDate() + days - 1);
  const fmt = (d: Date) => d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
  return `${fmt(s)} – ${fmt(e)}`;
};
const temporal = (start: string | null, days = 7): { label: string; cls: string } => {
  if (!start) return { label: "Unscheduled", cls: "bg-warm-100 text-warm-500 border-warm-200" };
  const s = new Date(`${start}T00:00:00`);
  const e = new Date(s); e.setDate(e.getDate() + days - 1);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  if (today < s) return { label: "Upcoming", cls: "bg-sky-50 text-sky-700 border-sky-200" };
  if (today > e) return { label: "Past", cls: "bg-warm-100 text-warm-500 border-warm-200" };
  return { label: "Current week", cls: "bg-emerald-50 text-emerald-700 border-emerald-200" };
};

// A cell holds EITHER a recipe or a single fs_item. recipe_name is the
// display label for whichever one is set. servings_override is the ACTUAL servings
// for this menu-cycle slot (set via the food panel) — overrides the day's headcount
// for this dish only, and never touches the baseline recipe.
interface Cell {
  recipe_id: number | null;
  fs_item_id: number | null;
  recipe_name: string;
  servings: number;
  servings_override: number | null;
  quantity: number;
  estimate_population: number | null;
  po_snapshot: MenuSnapshot | null;
  hasRecipeOverride: boolean;
}
type Grid = Record<string, Cell>;
// Per-day headcount (drives scaling). Keyed by Day.
type DayPop = Record<string, string>;


// ─── Breadcrumb + header shell ──────────────────────────────────────────────────
function Shell({ children }: { children: React.ReactNode }) {
  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span><span>Food Service</span><span>/</span>
        <span className="font-bold text-warm-600">Menu Cycle</span>
      </div>
      {children}
    </div>
  );
}

// ═══ LIST VIEW ═══════════════════════════════════════════════════════════════════
function CycleList({ readOnly, onOpen, onNew }: { readOnly: boolean; onOpen: (id: number) => void; onNew: () => void }) {
  const [cycles, setCycles] = useState<CycleListItem[]>([]);
  const [templates, setTemplates] = useState<TemplateListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [cyclePage, setCyclePage] = useState(1);
  const [templatePage, setTemplatePage] = useState(1);
  const [cycleMeta, setCycleMeta] = useState<PaginationMeta | null>(null);
  const [templateMeta, setTemplateMeta] = useState<PaginationMeta | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setErr("");
    try {
      const [c, t] = await Promise.all([listCycles(cyclePage), listTemplates(templatePage)]);
      setCycles(c.data); setCycleMeta(c.meta); setTemplates(t.data); setTemplateMeta(t.meta);
    } catch { setErr("Failed to load menu cycles."); } finally { setLoading(false); }
  }, [cyclePage, templatePage]);
  useEffect(() => { load(); }, [load]);

  async function remove(id: number) { await deleteCycle(id); load(); }
  async function applyTemplate(t: TemplateListItem) {
    const res = await instantiateTemplate(t.id, {});
    onOpen(res.id);
  }
  async function removeTemplate(id: number) { await deleteTemplate(id); load(); }

  return (
    <Shell>
      <div className="border-b border-warm-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <CalendarDays className="h-5 w-5 text-emerald-600" /> Menu Cycles
          </h2>
          <p className="text-sm text-warm-500 mt-1">Plan a fixed Monday-Sunday menu from food-service recipes and single items.</p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-sm text-warm-500 hover:text-warm-700">
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
          </button>
          {!readOnly && (
            <Button variant="primary" onClick={onNew} className="px-4 py-2.5 flex items-center gap-2">
              <Plus className="h-4 w-4" /> New Cycle
            </Button>
          )}
        </div>
      </div>

      {err && <div className="text-sm text-red-500">{err}</div>}

      {/* Cycles */}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? <div className="py-16 text-center text-sm text-warm-400">Loading…</div>
          : cycles.length === 0 ? (
            <div className="py-16 text-center">
              <CalendarDays className="h-8 w-8 text-warm-300 mx-auto mb-3" />
              <p className="text-sm text-warm-400 font-medium">No menu cycles yet. Create your first.</p>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-warm-50 border-b border-warm-100">
                <tr>{["Cycle", "Week", "When", "Status", "Per-day plan", "Actions"].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
                ))}</tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {cycles.map((c) => {
                  const when = temporal(c.week_start_date);
                  return (
                  <tr key={c.id} className={`hover:bg-warm-50/60 transition-colors ${c.is_active ? "border-l-4 border-l-emerald-500" : "border-l-4 border-l-transparent"}`}>
                    <td className="px-4 py-3">
                      <span className="font-semibold text-warm-800">{c.name}</span>
                      {c.is_active && <div className="text-xs text-emerald-700 font-semibold mt-0.5">Active cycle</div>}
                    </td>
                    <td className="px-4 py-3 text-warm-500 tabular-nums">{weekRange(c.week_start_date)}</td>
                    <td className="px-4 py-3 text-warm-500">{when.label}</td>
                    <td className="px-4 py-3 text-warm-500">{c.is_active ? "Active" : c.status}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-x-2 gap-y-1 text-xs text-warm-500">
                        {DAYS.map((day) => (
                          <span key={day} className={c.plan_days?.[day] ? "text-warm-800 font-semibold" : "text-warm-400"}>
                            {day.slice(0, 3)} {c.plan_days?.[day] ? "planned" : "empty"}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1">
                        <button onClick={() => onOpen(c.id)} className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 cursor-pointer" title={readOnly ? "View" : "Edit"}>
                          {readOnly ? <CalendarDays className="h-3.5 w-3.5" /> : <Pencil className="h-3.5 w-3.5" />}
                        </button>
                        {!readOnly && (
                          <button onClick={() => remove(c.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 cursor-pointer" title="Delete">
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                  );
                })}
              </tbody>
            </table>
          )}
          <Pagination meta={cycleMeta} page={cyclePage} onPageChange={setCyclePage} />
      </div>

      {/* Templates */}
      {templates.length > 0 && (
        <div>
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2 mb-3">
            <LayoutTemplate className="h-4 w-4 text-warm-400" /> Templates
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {templates.map((t) => (
              <div key={t.id} className="bg-white border border-warm-200 rounded-xl p-4 shadow-sm">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="text-base font-bold text-warm-800 truncate">{t.name}</div>
                    <div className="text-xs text-warm-400">{t.days_count} slots · {t.cycle_days} days</div>
                  </div>
                  {!readOnly && (
                    <button onClick={() => removeTemplate(t.id)} className="p-1 rounded text-warm-400 hover:text-red-500 cursor-pointer"><Trash2 className="h-3.5 w-3.5" /></button>
                  )}
                </div>
                {!readOnly && (
                  <button onClick={() => applyTemplate(t)} className="mt-3 w-full text-xs font-bold uppercase tracking-wider text-emerald-700 border border-emerald-200 rounded-lg py-1.5 hover:bg-emerald-50 cursor-pointer">
                    Create cycle from this
                  </button>
                )}
              </div>
            ))}
          </div>
          <Pagination meta={templateMeta} page={templatePage} onPageChange={setTemplatePage} />
        </div>
      )}
    </Shell>
  );
}

// ═══ EDITOR VIEW ═══════════════════════════════════════════════════════════════════
function CycleEditor({ cycleId, readOnly, onBack }: { cycleId: number | "new"; readOnly: boolean; onBack: () => void }) {
  const router = useRouter();
  const [name, setName] = useState("New Menu Cycle");
  const [weekStart, setWeekStart] = useState("");
  const [isActive, setIsActive] = useState(false);

  const [grid, setGrid] = useState<Grid>({});
  // Served (actual) population per weekday for the active week — logged by FSS/RND,
  // summed across the span to complete the food PO + compute its actual budget/head.
  const [served, setServed] = useState<DayPop>({});
  const [servedDraft, setServedDraft] = useState<DayPop>({});
  const [editingServed, setEditingServed] = useState<Day | null>(null);
  const [savingServed, setSavingServed] = useState<string | null>(null);
  const [recipes, setRecipes] = useState<RecipeOption[]>([]);
  const [items, setItems] = useState<FsItemOption[]>([]);
  const [savedId, setSavedId] = useState<number | null>(cycleId === "new" ? null : cycleId);

  const [activeCell, setActiveCell] = useState<string | null>(null);
  const [pickerSearch, setPickerSearch] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(cycleId !== "new");

  // Load recipes + single catalog items (picker) + existing cycle
  useEffect(() => { listRecipeOptions().then(setRecipes); }, []);
  useEffect(() => { listFsItemOptions().then(setItems); }, []);
  useEffect(() => {
    if (cycleId === "new") return;
    getCycle(cycleId).then((c: MenuCycle) => {
      setName(c.name);
      setWeekStart(c.week_start_date ?? ""); setIsActive(c.is_active);
      const g: Grid = {};
      (c.days ?? []).forEach((d) => {
        const k = cellKey(d.day_of_week, d.meal_type);
        if (d.recipe_id && d.recipe) g[k] = {
          recipe_id: d.recipe_id, fs_item_id: null, recipe_name: d.recipe.name, servings: d.recipe.servings,
          servings_override: d.servings_override ?? null,
          quantity: d.quantity ?? 1, estimate_population: d.estimate_population ?? null,
          po_snapshot: d.po_snapshot ?? null,
          hasRecipeOverride: d.has_recipe_override ?? false,
        };
        else if (d.fs_item_id && d.fs_item) g[k] = {
          recipe_id: null, fs_item_id: d.fs_item_id, recipe_name: d.fs_item.name, servings: 0,
          servings_override: d.servings_override ?? null,
          quantity: d.quantity ?? 1, estimate_population: d.estimate_population ?? null,
          po_snapshot: d.po_snapshot ?? null,
          hasRecipeOverride: false,
        };
      });
      setGrid(g);
    }).catch(() => setErr("Failed to load cycle.")).finally(() => setLoading(false));
  }, [cycleId]);

  // Load served population for the active week's dates (keyed back to weekday).
  const loadServed = useCallback(() => {
    if (!savedId || !weekStart) { setServed({}); return; }
    listServiceLogs({ menu_cycle_id: savedId }).then((logs) => {
      const map: DayPop = {};
      DAYS.forEach((d, i) => {
        const date = isoAddDays(weekStart, i);
        const log = logs.find((l) => l.service_date === date);
        if (log?.served_population != null) map[d] = String(log.served_population);
      });
      setServed(map);
    }).catch(() => { /* keep prior */ });
  }, [savedId, weekStart]);
  useEffect(() => { loadServed(); }, [loadServed]);

  // Backfill served population for a weekday (FSS + RND, before the food PO completes).
  async function saveServed(day: Day, value: string) {
    if (!savedId || !weekStart) return;
    const n = parseInt(value, 10);
    if (!Number.isFinite(n) || n < 0) return;
    setSavingServed(day); setErr("");
    try {
      await setServedPopulation(savedId, isoAddDays(weekStart, DAYS.indexOf(day)), n);
      setServed((p) => ({ ...p, [day]: String(n) }));
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to save served population.");
    } finally {
      setEditingServed(null);
      setSavingServed(null);
    }
  }

  function beginServedEdit(day: Day) {
    setServedDraft((p) => ({ ...p, [day]: served[day] ?? "" }));
    setEditingServed(day);
  }

  function assign(key: string, r: RecipeOption) {
    setGrid((g) => ({ ...g, [key]: {
      recipe_id: r.id, fs_item_id: null, recipe_name: r.name, servings: r.servings,
      servings_override: null, quantity: 1, estimate_population: null, po_snapshot: null,
      hasRecipeOverride: false,
    } }));
    setActiveCell(null); setPickerSearch("");
  }
  function assignItem(key: string, it: FsItemOption) {
    setGrid((g) => ({ ...g, [key]: {
      recipe_id: null, fs_item_id: it.id, recipe_name: it.name, servings: 0,
      servings_override: null, quantity: 1, estimate_population: null, po_snapshot: null,
      hasRecipeOverride: false,
    } }));
    setActiveCell(null); setPickerSearch("");
  }
  function clearCell(key: string) { setGrid((g) => { const n = { ...g }; delete n[key]; return n; }); }
  function duplicateWeek(from: Day) {
    setGrid((g) => {
      const n = { ...g };
      DAYS.filter((d) => d !== from).forEach((d) => {
        MEALS.forEach((m) => {
          const src = g[cellKey(from, m)];
          if (src) n[cellKey(d, m)] = { ...src };
        });
      });
      return n;
    });
  }

  function daysPayload() {
    return Object.entries(grid).map(([key, c]) => {
      const [day_of_week, meal_type] = key.split("|") as [Day, Meal];
      return {
        day_of_week, meal_type, recipe_id: c.recipe_id, fs_item_id: c.fs_item_id, quantity: c.quantity,
        servings_override: c.servings_override,
        estimate_population: c.estimate_population,
        is_event: false, event_allocation: null,
      };
    });
  }

  async function handleSave() {
    if (!name.trim()) { setErr("Name is required."); return null; }
    setBusy(true); setErr("");
    try {
      const saved = await saveCycle(savedId, {
        name: name.trim(),
        cycle_days: 7,
        week_start_date: weekStart || null,
        days: daysPayload(),
      });
      setSavedId(saved.id);
      return saved.id;
    } catch (e: unknown) {
      setErr(e instanceof Error ? e.message : "Save failed."); return null;
    } finally { setBusy(false); }
  }

  async function handleActivate() {
    const id = savedId ?? (await handleSave());
    if (!id) return;
    await activateCycle(id); setIsActive(true);
  }
  async function handleSaveTemplate() {
    const id = savedId ?? (await handleSave());
    if (!id) return;
    const tName = prompt("Template name?", `${name} template`);
    if (!tName) return;
    await saveCycleAsTemplate(id, tName);
  }

  async function openSlot(day: Day, meal: Meal, cell: Cell) {
    const id = readOnly || isActive || cell.po_snapshot ? savedId : await handleSave();
    if (!id) return;
    const base = readOnly ? "/fss/menu" : "/food-service/menu-cycle";
    router.push(`${base}/${id}/slots/${day}/${meal}`);
  }

  const filteredRecipes = recipes.filter((r) => !pickerSearch || r.name.toLowerCase().includes(pickerSearch.toLowerCase()));
  const filteredItems = items.filter((i) => !pickerSearch || i.name.toLowerCase().includes(pickerSearch.toLowerCase()));

  if (loading) return <Shell><div className="py-16 text-center text-sm text-warm-400">Loading…</div></Shell>;

  return (
    <Shell>
      {/* Header */}
      <div className="border-b border-warm-200 pb-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div className="flex items-start gap-3">
          <button onClick={onBack} className="p-2 rounded-lg border border-warm-200 hover:bg-warm-50 text-warm-500 cursor-pointer mt-0.5"><ChevronLeft className="h-4 w-4" /></button>
          <div>
            <input value={name} onChange={(e) => setName(e.target.value)} readOnly={readOnly}
              className="text-xl font-extrabold text-warm-900 tracking-tight bg-transparent border-b border-dashed border-warm-200 focus:border-emerald-500 focus:outline-none read-only:border-transparent" />
            <div className="flex items-center gap-2 mt-1">
              {isActive && <span className="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>}
              <span className="text-xs text-warm-400">{savedId ? `Cycle #${savedId}` : "Unsaved draft"}</span>
            </div>
          </div>
        </div>
        {readOnly ? (
          <span className="text-xs font-bold uppercase tracking-wider text-warm-400 border border-warm-200 rounded-lg px-3 py-2 shrink-0">View only</span>
        ) : (
          <div className="flex flex-wrap items-center gap-2 shrink-0">
            <button onClick={handleSaveTemplate} className="flex items-center gap-1.5 text-sm font-semibold text-warm-600 border border-warm-200 rounded-lg px-3 py-2 hover:bg-warm-50 cursor-pointer"><BookmarkPlus className="h-3.5 w-3.5" /> Save as Template</button>
            <button onClick={handleActivate} className="flex items-center gap-1.5 text-sm font-semibold text-emerald-700 border border-emerald-200 rounded-lg px-3 py-2 hover:bg-emerald-50 cursor-pointer"><Zap className="h-3.5 w-3.5" /> Activate</button>
            <Button variant="primary" onClick={() => handleSave()} loading={busy} className="px-4 py-2 flex items-center gap-2"><Save className="h-4 w-4" /> Save</Button>
          </div>
        )}
      </div>

      {err && <div className="bg-red-50 border border-red-100 p-3 rounded-xl text-sm text-red-700 font-bold flex items-center gap-2"><AlertTriangle className="h-3.5 w-3.5" /> {err}</div>}

      {/* Settings */}
      <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-4">
        {[
          { label: "Week start (Mon)", value: weekStart, set: setWeekStart, type: "date", ph: "" },
        ].map((f) => (
          <div key={f.label}>
            <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">{f.label}</label>
            <input type={f.type} value={f.value} placeholder={f.ph} onChange={(e) => f.set(e.target.value)}
              className="w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
        ))}
      </div>

      {/* Grid */}
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-sm border-collapse">
          <thead>
            <tr className="bg-warm-50 border-b border-warm-100">
              <th className="px-3 py-3 text-left text-xs font-bold text-warm-500 uppercase sticky left-0 bg-warm-50">Meal</th>
              {DAYS.map((d) => {
                return (
                  <th key={d} className="px-3 py-2 text-left text-xs font-bold text-warm-500 uppercase min-w-[140px]">
                    <div className="flex items-center justify-between gap-1">
                      <span>{d.slice(0, 3)}</span>
                      {!readOnly && (
                        <button onClick={() => duplicateWeek(d)} title={`Copy ${d} to all days`} className="text-warm-300 hover:text-emerald-600 cursor-pointer"><Copy className="h-3 w-3" /></button>
                      )}
                    </div>
                    {/* Served (actual) population for this day — the real headcount FSS/RND
                        log on/after the day. Summed across the span it completes the food PO
                        and yields its actual budget/head. Editable by both roles. */}
                    {savedId && weekStart && (
                      <div className="mt-1 normal-case">
                        {editingServed === d ? (
                          <div className="flex items-center gap-1">
                            <input
                              autoFocus
                              type="number"
                              min={0}
                              value={servedDraft[d] ?? ""}
                              placeholder="served"
                              onChange={(e) => setServedDraft((p) => ({ ...p, [d]: e.target.value }))}
                              onKeyDown={(e) => {
                                if (e.key === "Enter") saveServed(d, servedDraft[d] ?? "");
                                if (e.key === "Escape") setEditingServed(null);
                              }}
                              title={`${d} served population (${isoAddDays(weekStart, DAYS.indexOf(d))})`}
                              className="w-14 px-1.5 py-0.5 text-xs font-semibold border border-emerald-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                            <button
                              type="button"
                              onClick={() => saveServed(d, servedDraft[d] ?? "")}
                              disabled={savingServed === d}
                              className="p-0.5 rounded text-emerald-600 hover:bg-emerald-50 disabled:opacity-50 cursor-pointer"
                              title="Save served population">
                              <Save className="h-3 w-3" />
                            </button>
                            <button
                              type="button"
                              onClick={() => setEditingServed(null)}
                              className="p-0.5 rounded text-warm-400 hover:bg-warm-100 cursor-pointer"
                              title="Cancel">
                              <X className="h-3 w-3" />
                            </button>
                          </div>
                        ) : (
                          <div className="flex items-center gap-1">
                            <span className="text-xs font-extrabold text-warm-700 tabular-nums">
                              {served[d] != null ? served[d] : "Not set"}
                            </span>
                            <span className="text-xs text-warm-400">served</span>
                            <button
                              type="button"
                              onClick={() => beginServedEdit(d)}
                              className="p-0.5 rounded text-warm-400 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer"
                              title={`Edit ${d} served population`}>
                              <Pencil className="h-3 w-3" />
                            </button>
                            {savingServed === d && <span className="text-xs text-warm-400">saving…</span>}
                          </div>
                        )}
                      </div>
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {MEALS.map((m) => (
              <tr key={m} className="border-b border-warm-100 last:border-0">
                <td className="px-3 py-3 font-bold text-warm-600 sticky left-0 bg-white whitespace-nowrap">{MEAL_LABELS[m]}</td>
                {DAYS.map((d) => {
                  const key = cellKey(d, m);
                  const cell = grid[key];
                  const isPicking = activeCell === key;
                  return (
                    <td key={key} className="px-2 py-2 align-top relative">
                      {cell ? (
                        <div className="bg-emerald-50/60 border border-emerald-100 rounded-lg p-2 group">
                          <div className="flex items-start justify-between gap-1">
                            <button
                              onClick={() => openSlot(d, m, cell)}
                              title="Open menu item details"
                              className="min-h-11 flex-1 text-xs font-semibold text-emerald-800 leading-tight text-left hover:underline cursor-pointer">
                              {cell.recipe_name}
                            </button>
                            {!readOnly && (
                              <button onClick={() => clearCell(key)} className="text-emerald-400 hover:text-red-500 cursor-pointer shrink-0"><X className="h-3 w-3" /></button>
                            )}
                          </div>
                          <div className="text-xs text-emerald-500 mt-1">
                            {cell.po_snapshot ? "Locked to PO · open details" : "Open menu item details"}
                          </div>
                          {cell.hasRecipeOverride && <span className="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Customized slot</span>}
                        </div>
                      ) : readOnly ? (
                        <div className="w-full text-center py-2 text-xs text-warm-300">—</div>
                      ) : (
                        <button onClick={() => { setActiveCell(isPicking ? null : key); setPickerSearch(""); }}
                          className="w-full border border-dashed border-warm-200 rounded-lg py-2 text-xs text-warm-400 hover:border-emerald-300 hover:text-emerald-600 cursor-pointer flex items-center justify-center gap-1">
                          <Plus className="h-3 w-3" /> add
                        </button>
                      )}

                      {isPicking && !readOnly && (
                        <div className="absolute z-30 top-full left-0 mt-1 w-56 bg-white border border-warm-200 rounded-xl shadow-lg p-2">
                          <div className="relative mb-1">
                            <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-warm-400" />
                            <input autoFocus value={pickerSearch} onChange={(e) => setPickerSearch(e.target.value)} placeholder="Search recipes & items…"
                              className="w-full pl-7 pr-2 py-1.5 text-sm border border-warm-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                          </div>
                          <div className="max-h-48 overflow-y-auto">
                            {filteredRecipes.length === 0 && filteredItems.length === 0 ? (
                              <div className="text-xs text-warm-400 px-2 py-3 text-center">No matches. Build recipes/items under FSS.</div>
                            ) : (
                              <>
                                {filteredRecipes.map((r) => (
                                  <button key={`r-${r.id}`} onClick={() => assign(key, r)}
                                    className="w-full text-left px-2 py-1.5 rounded-lg hover:bg-emerald-50 cursor-pointer">
                                    <div className="text-xs font-semibold text-warm-800 truncate">{r.name}</div>
                                    <div className="text-xs text-warm-400">serves {r.servings}{r.category ? ` · ${r.category}` : ""}</div>
                                  </button>
                                ))}
                                {filteredItems.length > 0 && (
                                  <div className="px-2 pt-2 pb-1 text-xs font-bold uppercase tracking-wider text-warm-400">Single items</div>
                                )}
                                {filteredItems.map((it) => (
                                  <button key={`i-${it.id}`} onClick={() => assignItem(key, it)}
                                    className="w-full text-left px-2 py-1.5 rounded-lg hover:bg-amber-50 cursor-pointer">
                                    <div className="text-xs font-semibold text-warm-800 truncate">{it.name}</div>
                                    <div className="text-xs text-warm-400">item{it.category ? ` · ${it.category}` : ""}</div>
                                  </button>
                                ))}
                              </>
                            )}
                          </div>
                          <button onClick={() => setActiveCell(null)} className="w-full mt-1 text-xs text-warm-400 hover:text-warm-600 cursor-pointer">close</button>
                        </div>
                      )}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

    </Shell>
  );
}

// ═══ ROOT ═══════════════════════════════════════════════════════════════════════
export default function MenuCyclePage() {
  const searchParams = useSearchParams();
  const [view, setView] = useState<{ mode: "list" } | { mode: "edit"; id: number | "new" } | { mode: "loading" }>({ mode: "loading" });
  const { user } = useAuth();
  // FSS may VIEW menu cycles but never author them (RND owns writes). Backend already
  // enforces this; here we render a read-only editor so FSS sees no edit affordances.
  const readOnly = user?.role === "FSS";

  // Landing page is the current ACTIVE menu cycle, not the list. The list stays
  // reachable via "View all cycles". Falls back to the list when none is active.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const requestedCycle = searchParams.get("cycle");
      if (requestedCycle) {
        setView({ mode: "edit", id: requestedCycle as unknown as number });
        return;
      }
      try {
        const cycles = await listCycles(1);
        const active = cycles.data.find((c) => c.is_active);
        if (!cancelled) setView(active ? { mode: "edit", id: active.id } : { mode: "list" });
      } catch {
        if (!cancelled) setView({ mode: "list" });
      }
    })();
    return () => { cancelled = true; };
  }, [searchParams]);

  if (view.mode === "loading") {
    return <Shell><div className="py-16 text-center text-sm text-warm-400">Loading…</div></Shell>;
  }
  if (view.mode === "edit") {
    return <CycleEditor cycleId={view.id} readOnly={readOnly} onBack={() => setView({ mode: "list" })} />;
  }
  return <CycleList readOnly={readOnly} onOpen={(id) => setView({ mode: "edit", id })} onNew={() => setView({ mode: "edit", id: "new" })} />;
}
