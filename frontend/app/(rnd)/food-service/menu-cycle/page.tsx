"use client";

import React, { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import {
  CalendarDays, Plus, Search, X, Trash2, Save, Zap, Copy, BookmarkPlus,
  LayoutTemplate, ChevronLeft, AlertTriangle, CheckCircle2, RefreshCw, Pencil,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import ServiceLogPanel from "./_components/ServiceLogPanel";
import {
  DAYS, MEALS, MEAL_LABELS, Day, Meal,
  CycleListItem, MenuCycle, ComputeResult, RecipeOption, TemplateListItem, RecipeProfile,
  listCycles, getCycle, saveCycle, deleteCycle, computeCycle, activateCycle,
  saveCycleAsTemplate, listRecipeOptions, listTemplates, instantiateTemplate, deleteTemplate,
  getRecipeProfile,
} from "@/services/menuCycleService";

const peso = (n: number) => `₱${n.toFixed(2)}`;
const cellKey = (d: Day, m: Meal) => `${d}|${m}`;

interface Cell { recipe_id: number; recipe_name: string; servings: number }
type Grid = Record<string, Cell>;
// Per-day headcount (drives scaling). Keyed by Day.
type DayPop = Record<string, string>;

const BUDGET_CHIP: Record<string, string> = {
  ok:      "bg-emerald-50 text-emerald-700 border-emerald-200",
  warning: "bg-amber-50 text-amber-700 border-amber-200",
  over:    "bg-red-50 text-red-700 border-red-200",
};

// ─── Recipe profile panel (ingredients + cost scaled to a day's headcount) ──────
function RecipeProfilePanel(
  { recipeId, day, population, name, onClose }:
  { recipeId: number; day: Day; population: number; name: string; onClose: () => void },
) {
  const [data, setData] = useState<RecipeProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  useEffect(() => {
    let live = true;
    setLoading(true); setErr("");
    getRecipeProfile(recipeId, population)
      .then((d) => { if (live) setData(d); })
      .catch(() => { if (live) setErr("Failed to load recipe profile."); })
      .finally(() => { if (live) setLoading(false); });
    return () => { live = false; };
  }, [recipeId, population]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-start justify-between gap-3 p-5 border-b border-zinc-100">
          <div>
            <div className="text-sm font-extrabold text-zinc-900">{name}</div>
            <div className="text-[11px] text-zinc-500 mt-0.5">{day} · scaled to {population} heads</div>
          </div>
          <div className="flex items-center gap-2">
            <Link href={`/food-service/foods/${recipeId}`}
              className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-emerald-200 text-[10px] font-bold uppercase tracking-wider text-emerald-700 hover:bg-emerald-50">
              <Pencil className="h-3 w-3" /> Edit
            </Link>
            <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 cursor-pointer"><X className="h-4 w-4" /></button>
          </div>
        </div>

        {loading ? (
          <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>
        ) : err ? (
          <div className="py-16 text-center text-xs text-red-500">{err}</div>
        ) : data ? (
          <div className="p-5 space-y-4">
            <div className="flex flex-wrap gap-5">
              <div>
                <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Total (this day)</div>
                <div className="text-xl font-extrabold text-emerald-600">{peso(data.total_cost)}</div>
              </div>
              <div>
                <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Cost / head</div>
                <div className="text-xl font-extrabold text-zinc-800">{peso(data.cost_per_head)}</div>
              </div>
              <div>
                <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Baseline</div>
                <div className="text-xl font-extrabold text-zinc-400">serves {data.servings}</div>
              </div>
            </div>

            <div>
              <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-2">Ingredients (scaled)</div>
              <table className="w-full text-xs">
                <thead>
                  <tr className="text-[10px] text-zinc-400 uppercase">
                    <th className="text-left font-bold py-1">Item</th>
                    <th className="text-right font-bold py-1">Qty</th>
                    <th className="text-right font-bold py-1">Cost</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-zinc-50">
                  {data.ingredient_usage.map((u) => (
                    <tr key={u.fs_item_id}>
                      <td className="py-1.5 text-zinc-700 font-medium">{u.name}</td>
                      <td className="py-1.5 text-right text-zinc-500 font-mono">{u.quantity.toFixed(2)} {u.unit}</td>
                      <td className="py-1.5 text-right text-zinc-700 font-mono">{peso(u.cost)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {data.ingredient_usage.length === 0 && (
                <div className="text-[11px] text-zinc-400 py-4 text-center">No costable ingredients.</div>
              )}
            </div>
            <p className="text-[10px] text-zinc-400">Quantities and cost scale live with this day&apos;s estimated population ({population}).</p>
          </div>
        ) : null}
      </div>
    </div>
  );
}

// ─── Breadcrumb + header shell ──────────────────────────────────────────────────
function Shell({ children }: { children: React.ReactNode }) {
  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span>/</span><span>Food Service</span><span>/</span>
        <span className="font-bold text-zinc-600">Menu Cycle</span>
      </div>
      {children}
    </div>
  );
}

// ═══ LIST VIEW ═══════════════════════════════════════════════════════════════════
function CycleList({ onOpen, onNew }: { onOpen: (id: number) => void; onNew: () => void }) {
  const [cycles, setCycles] = useState<CycleListItem[]>([]);
  const [templates, setTemplates] = useState<TemplateListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");

  const load = useCallback(async () => {
    setLoading(true); setErr("");
    try {
      const [c, t] = await Promise.all([listCycles(), listTemplates()]);
      setCycles(c); setTemplates(t);
    } catch { setErr("Failed to load menu cycles."); } finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  async function remove(id: number) { await deleteCycle(id); load(); }
  async function useTemplate(t: TemplateListItem) {
    const res = await instantiateTemplate(t.id, {});
    onOpen(res.id);
  }
  async function removeTemplate(id: number) { await deleteTemplate(id); load(); }

  return (
    <Shell>
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <CalendarDays className="h-5 w-5 text-emerald-600" /> Menu Cycles
          </h2>
          <p className="text-xs text-zinc-500 mt-1">Plan a weekly menu from food-service recipes. Costs scale to ward population and check against your budget per head.</p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700">
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
          </button>
          <Button variant="primary" onClick={onNew} className="px-4 py-2.5 flex items-center gap-2">
            <Plus className="h-4 w-4" /> New Cycle
          </Button>
        </div>
      </div>

      {err && <div className="text-xs text-red-500">{err}</div>}

      {/* Cycles */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        {loading ? <div className="py-16 text-center text-xs text-zinc-400">Loading…</div>
          : cycles.length === 0 ? (
            <div className="py-16 text-center">
              <CalendarDays className="h-8 w-8 text-zinc-300 mx-auto mb-3" />
              <p className="text-xs text-zinc-400 font-medium">No menu cycles yet. Create your first.</p>
            </div>
          ) : (
            <table className="w-full text-xs">
              <thead className="bg-zinc-50 border-b border-zinc-100">
                <tr>{["Cycle", "Status", "Actions"].map((h) => (
                  <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
                ))}</tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {cycles.map((c) => (
                  <tr key={c.id} className="hover:bg-zinc-50/60 transition-colors">
                    <td className="px-4 py-3">
                      <button onClick={() => onOpen(c.id)} className="font-semibold text-emerald-700 hover:underline cursor-pointer">{c.name}</button>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${c.is_active ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-zinc-100 text-zinc-500 border-zinc-200"}`}>
                        {c.is_active ? "Active" : c.status}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1">
                        <button onClick={() => onOpen(c.id)} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 cursor-pointer" title="Open">
                          <CalendarDays className="h-3.5 w-3.5" />
                        </button>
                        <button onClick={() => remove(c.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer" title="Delete">
                          <Trash2 className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
      </div>

      {/* Templates */}
      {templates.length > 0 && (
        <div>
          <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider flex items-center gap-2 mb-3">
            <LayoutTemplate className="h-4 w-4 text-zinc-400" /> Templates
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            {templates.map((t) => (
              <div key={t.id} className="bg-white border border-zinc-200 rounded-xl p-4 shadow-sm">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="text-sm font-bold text-zinc-800 truncate">{t.name}</div>
                    <div className="text-[10px] text-zinc-400">{t.days_count} slots · {t.cycle_days} days</div>
                  </div>
                  <button onClick={() => removeTemplate(t.id)} className="p-1 rounded text-zinc-400 hover:text-red-500 cursor-pointer"><Trash2 className="h-3.5 w-3.5" /></button>
                </div>
                <button onClick={() => useTemplate(t)} className="mt-3 w-full text-[10px] font-bold uppercase tracking-wider text-emerald-700 border border-emerald-200 rounded-lg py-1.5 hover:bg-emerald-50 cursor-pointer">
                  Create cycle from this
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
    </Shell>
  );
}

// ═══ EDITOR VIEW ═══════════════════════════════════════════════════════════════════
function CycleEditor({ cycleId, onBack }: { cycleId: number | "new"; onBack: () => void }) {
  const [name, setName] = useState("New Menu Cycle");
  const [dayPop, setDayPop] = useState<DayPop>({});
  const [weekStart, setWeekStart] = useState("");
  const [cycleDays, setCycleDays] = useState(7);
  const [isActive, setIsActive] = useState(false);

  const [grid, setGrid] = useState<Grid>({});
  const [recipes, setRecipes] = useState<RecipeOption[]>([]);
  const [savedId, setSavedId] = useState<number | null>(cycleId === "new" ? null : cycleId);

  const [compute, setCompute] = useState<ComputeResult | null>(null);
  const [activeCell, setActiveCell] = useState<string | null>(null);
  const [profileFor, setProfileFor] = useState<{ recipeId: number; day: Day; name: string } | null>(null);
  const [pickerSearch, setPickerSearch] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(cycleId !== "new");

  // Load recipes (picker) + existing cycle
  useEffect(() => { listRecipeOptions().then(setRecipes); }, []);
  useEffect(() => {
    if (cycleId === "new") return;
    getCycle(cycleId).then((c: MenuCycle) => {
      setName(c.name);
      setWeekStart(c.week_start_date ?? ""); setCycleDays(c.cycle_days || 7); setIsActive(c.is_active);
      const g: Grid = {};
      const dp: DayPop = {};
      (c.days ?? []).forEach((d) => {
        if (d.recipe_id && d.recipe) g[cellKey(d.day_of_week, d.meal_type)] = {
          recipe_id: d.recipe_id, recipe_name: d.recipe.name, servings: d.recipe.servings,
        };
        // Each day carries one population; first seen wins (they're equal across meals).
        if (d.estimate_population != null && dp[d.day_of_week] == null) dp[d.day_of_week] = String(d.estimate_population);
      });
      setGrid(g); setDayPop(dp);
    }).catch(() => setErr("Failed to load cycle.")).finally(() => setLoading(false));
  }, [cycleId]);

  const visibleDays = useMemo(() => DAYS.slice(0, cycleDays), [cycleDays]);
  const dayPops = visibleDays.map((d) => parseInt(dayPop[d]) || 0);
  const cyclePop = dayPops.length ? Math.round(dayPops.reduce((a, b) => a + b, 0) / dayPops.length) : 0;

  function assign(key: string, r: RecipeOption) {
    setGrid((g) => ({ ...g, [key]: { recipe_id: r.id, recipe_name: r.name, servings: r.servings } }));
    setActiveCell(null); setPickerSearch("");
  }
  function clearCell(key: string) { setGrid((g) => { const n = { ...g }; delete n[key]; return n; }); }
  function setPop(day: Day, v: string) { setDayPop((p) => ({ ...p, [day]: v })); }
  function duplicateWeek(from: Day) {
    setGrid((g) => {
      const n = { ...g };
      visibleDays.filter((d) => d !== from).forEach((d) => {
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
        day_of_week, meal_type, recipe_id: c.recipe_id, fs_item_id: null, quantity: 1,
        estimate_population: parseInt(dayPop[day_of_week]) || 0,
        is_event: false, event_allocation: null,
      };
    });
  }

  async function handleSave(thenCompute = true) {
    if (!name.trim()) { setErr("Name is required."); return null; }
    setBusy(true); setErr("");
    try {
      const saved = await saveCycle(savedId, {
        name: name.trim(),
        cycle_days: cycleDays,
        week_start_date: weekStart || null,
        days: daysPayload(),
      });
      setSavedId(saved.id);
      if (thenCompute) setCompute(await computeCycle(saved.id));
      return saved.id;
    } catch (e: unknown) {
      setErr(e instanceof Error ? e.message : "Save failed."); return null;
    } finally { setBusy(false); }
  }

  async function handleActivate() {
    const id = savedId ?? (await handleSave(false));
    if (!id) return;
    await activateCycle(id); setIsActive(true);
  }
  async function handleSaveTemplate() {
    const id = savedId ?? (await handleSave(false));
    if (!id) return;
    const tName = prompt("Template name?", `${name} template`);
    if (!tName) return;
    await saveCycleAsTemplate(id, tName);
  }

  const filteredRecipes = recipes.filter((r) => !pickerSearch || r.name.toLowerCase().includes(pickerSearch.toLowerCase()));

  if (loading) return <Shell><div className="py-16 text-center text-xs text-zinc-400">Loading…</div></Shell>;

  return (
    <Shell>
      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div className="flex items-start gap-3">
          <button onClick={onBack} className="p-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 text-zinc-500 cursor-pointer mt-0.5"><ChevronLeft className="h-4 w-4" /></button>
          <div>
            <input value={name} onChange={(e) => setName(e.target.value)}
              className="text-xl font-extrabold text-zinc-950 tracking-tight bg-transparent border-b border-dashed border-zinc-200 focus:border-emerald-500 focus:outline-none" />
            <div className="flex items-center gap-2 mt-1">
              {isActive && <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>}
              <span className="text-[10px] text-zinc-400">{savedId ? `Cycle #${savedId}` : "Unsaved draft"}</span>
            </div>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2 shrink-0">
          <button onClick={handleSaveTemplate} className="flex items-center gap-1.5 text-xs font-semibold text-zinc-600 border border-zinc-200 rounded-lg px-3 py-2 hover:bg-zinc-50 cursor-pointer"><BookmarkPlus className="h-3.5 w-3.5" /> Save as Template</button>
          <button onClick={handleActivate} className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 border border-emerald-200 rounded-lg px-3 py-2 hover:bg-emerald-50 cursor-pointer"><Zap className="h-3.5 w-3.5" /> Activate</button>
          <Button variant="primary" onClick={() => handleSave(true)} loading={busy} className="px-4 py-2 flex items-center gap-2"><Save className="h-4 w-4" /> Save &amp; Cost</Button>
        </div>
      </div>

      {err && <div className="bg-red-50 border border-red-100 p-3 rounded-xl text-xs text-red-700 font-bold flex items-center gap-2"><AlertTriangle className="h-3.5 w-3.5" /> {err}</div>}

      {/* Settings */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4">
        {[
          { label: "Week start (Mon)", value: weekStart, set: setWeekStart, type: "date", ph: "" },
        ].map((f) => (
          <div key={f.label}>
            <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">{f.label}</label>
            <input type={f.type} value={f.value} placeholder={f.ph} onChange={(e) => f.set(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
        ))}
        <div>
          <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">Cycle length</label>
          <select value={cycleDays} onChange={(e) => setCycleDays(parseInt(e.target.value))}
            className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            {[5, 6, 7].map((n) => <option key={n} value={n}>{n} days</option>)}
          </select>
        </div>
      </div>

      {/* Service log (consumption) — only for a saved, active cycle */}
      {savedId && isActive && <ServiceLogPanel cycleId={savedId} population={cyclePop} />}

      {/* Summary */}
      {compute && (
        <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm flex flex-wrap items-center gap-6">
          <div>
            <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Weekly total</div>
            <div className="text-2xl font-extrabold text-emerald-600">{peso(compute.total_cost)}</div>
          </div>
          <div>
            <div className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Cost / head (period)</div>
            <div className="text-2xl font-extrabold text-zinc-800">{peso(compute.cost_per_head)}</div>
          </div>
          {compute.budget_per_head_day != null && (
            <div className={`px-3 py-2 rounded-xl border text-xs font-bold flex items-center gap-1.5 ${compute.within_budget ? BUDGET_CHIP.ok : BUDGET_CHIP.over}`}>
              {compute.within_budget ? <CheckCircle2 className="h-3.5 w-3.5" /> : <AlertTriangle className="h-3.5 w-3.5" />}
              {compute.within_budget ? "Within budget" : "Over budget"} · cap {peso(compute.budget_per_head_day)}/head/day
            </div>
          )}
        </div>
      )}

      {/* Grid */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
        <table className="w-full text-xs border-collapse">
          <thead>
            <tr className="bg-zinc-50 border-b border-zinc-100">
              <th className="px-3 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase sticky left-0 bg-zinc-50">Meal</th>
              {visibleDays.map((d) => {
                const dc = compute?.days?.[d];
                return (
                  <th key={d} className="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase min-w-[140px]">
                    <div className="flex items-center justify-between gap-1">
                      <span>{d.slice(0, 3)}</span>
                      <button onClick={() => duplicateWeek(d)} title={`Copy ${d} to all days`} className="text-zinc-300 hover:text-emerald-600 cursor-pointer"><Copy className="h-3 w-3" /></button>
                    </div>
                    {/* Per-day headcount — drives scaling for this day's recipes. */}
                    <div className="mt-1 flex items-center gap-1">
                      <input type="number" min={0} value={dayPop[d] ?? ""} placeholder="pop"
                        onChange={(e) => setPop(d, e.target.value)} title={`${d} estimated population`}
                        className="w-14 px-1.5 py-0.5 text-[10px] font-semibold border border-zinc-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400 normal-case" />
                      <span className="text-[8px] text-zinc-400 normal-case">heads</span>
                    </div>
                    {dc && (
                      <div className={`mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold border ${BUDGET_CHIP[dc.budget_status ?? "ok"] ?? ""}`}>
                        {peso(dc.cost_per_head)}/head
                      </div>
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {MEALS.map((m) => (
              <tr key={m} className="border-b border-zinc-100 last:border-0">
                <td className="px-3 py-3 font-bold text-zinc-600 sticky left-0 bg-white whitespace-nowrap">{MEAL_LABELS[m]}</td>
                {visibleDays.map((d) => {
                  const key = cellKey(d, m);
                  const cell = grid[key];
                  const isPicking = activeCell === key;
                  return (
                    <td key={key} className="px-2 py-2 align-top relative">
                      {cell ? (
                        <div className="bg-emerald-50/60 border border-emerald-100 rounded-lg p-2 group">
                          <div className="flex items-start justify-between gap-1">
                            <button
                              onClick={() => setProfileFor({ recipeId: cell.recipe_id, day: d, name: cell.recipe_name })}
                              title="View ingredients & cost for this day"
                              className="text-[11px] font-semibold text-emerald-800 leading-tight text-left hover:underline cursor-pointer">
                              {cell.recipe_name}
                            </button>
                            <button onClick={() => clearCell(key)} className="text-emerald-400 hover:text-red-500 cursor-pointer shrink-0"><X className="h-3 w-3" /></button>
                          </div>
                          <div className="text-[9px] text-emerald-500 mt-1">click to see cost · scales to day pop</div>
                        </div>
                      ) : (
                        <button onClick={() => { setActiveCell(isPicking ? null : key); setPickerSearch(""); }}
                          className="w-full border border-dashed border-zinc-200 rounded-lg py-2 text-[10px] text-zinc-400 hover:border-emerald-300 hover:text-emerald-600 cursor-pointer flex items-center justify-center gap-1">
                          <Plus className="h-3 w-3" /> add
                        </button>
                      )}

                      {isPicking && (
                        <div className="absolute z-30 top-full left-0 mt-1 w-56 bg-white border border-zinc-200 rounded-xl shadow-lg p-2">
                          <div className="relative mb-1">
                            <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-zinc-400" />
                            <input autoFocus value={pickerSearch} onChange={(e) => setPickerSearch(e.target.value)} placeholder="Search recipes…"
                              className="w-full pl-7 pr-2 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-400" />
                          </div>
                          <div className="max-h-48 overflow-y-auto">
                            {filteredRecipes.length === 0 ? (
                              <div className="text-[10px] text-zinc-400 px-2 py-3 text-center">No recipes. Build some under FSS Recipes.</div>
                            ) : filteredRecipes.map((r) => (
                              <button key={r.id} onClick={() => assign(key, r)}
                                className="w-full text-left px-2 py-1.5 rounded-lg hover:bg-emerald-50 cursor-pointer">
                                <div className="text-[11px] font-semibold text-zinc-800 truncate">{r.name}</div>
                                <div className="text-[9px] text-zinc-400">serves {r.servings}{r.category ? ` · ${r.category}` : ""}</div>
                              </button>
                            ))}
                          </div>
                          <button onClick={() => setActiveCell(null)} className="w-full mt-1 text-[10px] text-zinc-400 hover:text-zinc-600 cursor-pointer">close</button>
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

      {/* Ingredient usage (procurement preview) */}
      {compute && compute.ingredient_usage.length > 0 && (
        <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
          <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-3">Ingredient usage (whole cycle)</h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1.5">
            {compute.ingredient_usage.map((u) => (
              <div key={u.fs_item_id} className="flex items-center justify-between text-[11px] border-b border-zinc-50 py-1">
                <span className="text-zinc-700 font-medium truncate">{u.name}</span>
                <span className="text-zinc-500 shrink-0">{u.quantity.toFixed(0)} {u.unit} · {peso(u.cost)}</span>
              </div>
            ))}
          </div>
          <p className="text-[10px] text-zinc-400 mt-3">This feeds the procurement suggested shopping list (Phase 3).</p>
        </div>
      )}

      {profileFor && (
        <RecipeProfilePanel
          recipeId={profileFor.recipeId}
          day={profileFor.day}
          name={profileFor.name}
          population={parseInt(dayPop[profileFor.day]) || 0}
          onClose={() => setProfileFor(null)}
        />
      )}
    </Shell>
  );
}

// ═══ ROOT ═══════════════════════════════════════════════════════════════════════
export default function MenuCyclePage() {
  const [view, setView] = useState<{ mode: "list" } | { mode: "edit"; id: number | "new" }>({ mode: "list" });

  if (view.mode === "edit") {
    return <CycleEditor cycleId={view.id} onBack={() => setView({ mode: "list" })} />;
  }
  return <CycleList onOpen={(id) => setView({ mode: "edit", id })} onNew={() => setView({ mode: "edit", id: "new" })} />;
}
