"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  TrendingUp, Plus, Trash2, RefreshCw, Pencil, X, Wallet, AlertTriangle, CheckCircle2,
} from "lucide-react";
import {
  ResponsiveContainer, LineChart, Line, BarChart, Bar, XAxis, YAxis,
  CartesianGrid, Tooltip, Legend, Cell,
} from "recharts";
import { Button } from "@/components/ui/Button";
import {
  Budget, BudgetPayload, BudgetSummary, BudgetScope,
  listBudgets, saveBudget, deleteBudget, getBudgetSummary,
} from "@/services/budgetService";

const peso = (n: number) => `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const num = (s: string | null) => (s ? parseFloat(s) : 0);
const todayISO = () => new Date().toISOString().slice(0, 10);
const monthStartISO = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); };

function Crumbs() {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
      <span>Food Service</span><span>/</span><span className="font-bold text-zinc-600">Budget</span>
    </div>
  );
}

// ═══ Records (budget CRUD) ════════════════════════════════════════════════════════
const EMPTY: BudgetPayload = { scope: "monthly", name: "", allocated_amount: 0 };

function RecordForm({ initial, editingId, onSaved, onCancel }: {
  initial: BudgetPayload; editingId: number | null; onSaved: () => void; onCancel: () => void;
}) {
  const [f, setF] = useState<BudgetPayload>(initial);
  const [saving, setSaving] = useState(false);
  const set = (p: Partial<BudgetPayload>) => setF((x) => ({ ...x, ...p }));
  const numField = (v: string) => (v === "" ? null : parseFloat(v));

  async function save() {
    setSaving(true);
    try { await saveBudget(editingId, f); onSaved(); } finally { setSaving(false); }
  }

  const Field = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">{label}</label>{children}</div>
  );
  const inp = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500";

  return (
    <div className="bg-emerald-50/40 border border-emerald-100 rounded-2xl p-6 space-y-4 shadow-sm">
      <h3 className="text-xs font-extrabold text-emerald-700 uppercase tracking-wider">{editingId ? "Edit Budget" : "New Budget"}</h3>
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <Field label="Name"><input value={f.name ?? ""} onChange={(e) => set({ name: e.target.value })} placeholder="e.g. 2026 Subsistence" className={inp} /></Field>
        <Field label="Scope">
          <select value={f.scope} onChange={(e) => set({ scope: e.target.value as BudgetScope })} className={`${inp} bg-white`}>
            {(["monthly", "quarterly", "yearly", "custom"] as const).map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </Field>
        <Field label="Allocated ₱"><input type="number" value={f.allocated_amount ?? 0} onChange={(e) => set({ allocated_amount: parseFloat(e.target.value) })} className={inp} /></Field>
        <Field label="Period start"><input type="date" value={f.period_start ?? ""} onChange={(e) => set({ period_start: e.target.value || null })} className={inp} /></Field>
        <Field label="Period end"><input type="date" value={f.period_end ?? ""} onChange={(e) => set({ period_end: e.target.value || null })} className={inp} /></Field>
        <Field label="Population"><input type="number" value={f.population ?? ""} onChange={(e) => set({ population: numField(e.target.value) })} className={inp} /></Field>
        <Field label="₱/head/day"><input type="number" value={f.budget_per_head_day ?? ""} onChange={(e) => set({ budget_per_head_day: numField(e.target.value) })} className={inp} /></Field>
        <Field label="₱/head/month"><input type="number" value={f.budget_per_head_month ?? ""} onChange={(e) => set({ budget_per_head_month: numField(e.target.value) })} className={inp} /></Field>
        <Field label="₱/head/year"><input type="number" value={f.budget_per_head_year ?? ""} onChange={(e) => set({ budget_per_head_year: numField(e.target.value) })} className={inp} /></Field>
      </div>
      <div className="flex gap-2">
        <Button variant="primary" onClick={save} disabled={saving} className="!py-1.5 !px-4 text-xs">{saving ? "Saving…" : "Save"}</Button>
        <button onClick={onCancel} className="text-xs text-zinc-500 hover:text-zinc-700 flex items-center gap-1"><X className="h-3 w-3" /> Cancel</button>
      </div>
    </div>
  );
}

// ═══ Dashboard ═════════════════════════════════════════════════════════════════════
function KpiCard({ label, value, tone = "zinc" }: { label: string; value: string; tone?: "zinc" | "emerald" | "red" | "amber" }) {
  const cls = {
    zinc: "bg-zinc-50 border-zinc-200 text-zinc-700", emerald: "bg-emerald-50 border-emerald-200 text-emerald-700",
    red: "bg-red-50 border-red-200 text-red-700", amber: "bg-amber-50 border-amber-200 text-amber-700",
  }[tone];
  return (
    <div className={`px-4 py-3 rounded-2xl border ${cls}`}>
      <div className="text-[10px] font-extrabold uppercase tracking-wider opacity-70">{label}</div>
      <div className="text-xl font-extrabold mt-0.5">{value}</div>
    </div>
  );
}

export default function BudgetPage() {
  const [tab, setTab] = useState<"dashboard" | "records">("dashboard");
  const [budgets, setBudgets] = useState<Budget[]>([]);
  const [loading, setLoading] = useState(true);

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [start, setStart] = useState(monthStartISO());
  const [end, setEnd] = useState(todayISO());
  const [gran, setGran] = useState("day");
  const [summary, setSummary] = useState<BudgetSummary | null>(null);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Budget | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const b = await listBudgets();
      setBudgets(b);
      setSelectedId((cur) => cur ?? b[0]?.id ?? null);
    } finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  const loadSummary = useCallback(async () => {
    if (!selectedId) { setSummary(null); return; }
    setSummary(await getBudgetSummary(selectedId, { start, end, granularity: gran }));
  }, [selectedId, start, end, gran]);
  useEffect(() => { loadSummary(); }, [loadSummary]);

  function openNew() { setEditing(null); setFormOpen(true); }
  function openEdit(b: Budget) { setEditing(b); setFormOpen(true); }
  function onSaved() { setFormOpen(false); setEditing(null); load(); }

  const selected = budgets.find((b) => b.id === selectedId) ?? null;
  const overBudget = summary ? summary.variance > 0 : false;

  return (
    <div className="space-y-6 font-sans">
      <Crumbs />
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5"><TrendingUp className="h-5 w-5 text-emerald-600" /> Budget</h2>
          <p className="text-xs text-zinc-500 mt-1">Set yearly / per-head budgets and track real spend (from received purchase orders) against them over any range.</p>
        </div>
        <button onClick={() => { load(); loadSummary(); }} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 shrink-0"><RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh</button>
      </div>

      <div className="flex border-b border-zinc-200">
        {([["dashboard", "Dashboard"], ["records", "Budget Records"]] as const).map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)} className={`px-5 py-3 text-sm font-semibold border-b-2 cursor-pointer ${tab === k ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-500 hover:text-zinc-800"}`}>{label}</button>
        ))}
      </div>

      {tab === "dashboard" ? (
        budgets.length === 0 ? (
          <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-xl mx-auto shadow-sm">
            <Wallet className="h-8 w-8 text-emerald-600 mx-auto mb-3" />
            <p className="text-xs text-zinc-500">No budgets yet. Create one in the <button onClick={() => setTab("records")} className="text-emerald-700 font-semibold hover:underline cursor-pointer">Budget Records</button> tab.</p>
          </div>
        ) : (
          <div className="space-y-5">
            {/* Controls */}
            <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Budget</label>
                <select value={selectedId ?? ""} onChange={(e) => setSelectedId(parseInt(e.target.value))} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                  {budgets.map((b) => <option key={b.id} value={b.id}>{b.name || `Budget #${b.id}`} ({b.scope})</option>)}
                </select>
              </div>
              <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">From</label><input type="date" value={start} onChange={(e) => setStart(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" /></div>
              <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">To</label><input type="date" value={end} onChange={(e) => setEnd(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" /></div>
              <div>
                <label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">Group by</label>
                <select value={gran} onChange={(e) => setGran(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                  {["day", "week", "month"].map((g) => <option key={g} value={g}>{g}</option>)}
                </select>
              </div>
            </div>

            {summary && (
              <>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <KpiCard label="Budget (range)" value={peso(summary.planned)} tone="emerald" />
                  <KpiCard label="Actual spend" value={peso(summary.actual)} />
                  <KpiCard label="Variance" value={`${overBudget ? "+" : ""}${peso(summary.variance)}`} tone={overBudget ? "red" : "emerald"} />
                  <KpiCard label="Variance %" value={`${summary.variance_pct}%`} tone={overBudget ? "amber" : "zinc"} />
                </div>

                <div className={`flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-xl border w-fit ${overBudget ? "bg-red-50 text-red-700 border-red-200" : "bg-emerald-50 text-emerald-700 border-emerald-200"}`}>
                  {overBudget ? <AlertTriangle className="h-3.5 w-3.5" /> : <CheckCircle2 className="h-3.5 w-3.5" />}
                  {overBudget ? "Over budget for this range" : "Within budget for this range"}
                </div>

                {/* Trend */}
                <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
                  <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">Budget vs Actual</h3>
                  <ResponsiveContainer width="100%" height={260}>
                    <LineChart data={summary.trend} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                      <XAxis dataKey="bucket" tick={{ fontSize: 10, fill: "#94a3b8" }} />
                      <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
                      <Tooltip formatter={(value) => peso(Number(value))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                      <Legend wrapperStyle={{ fontSize: 11 }} />
                      <Line type="monotone" dataKey="planned" name="Budget" stroke="#059669" strokeWidth={2} dot={false} />
                      <Line type="monotone" dataKey="actual" name="Actual" stroke="#dc2626" strokeWidth={2} dot={{ r: 2 }} />
                    </LineChart>
                  </ResponsiveContainer>
                </div>

                {/* Variance bars */}
                <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
                  <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">Variance per {gran}</h3>
                  <ResponsiveContainer width="100%" height={200}>
                    <BarChart data={summary.trend} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                      <XAxis dataKey="bucket" tick={{ fontSize: 10, fill: "#94a3b8" }} />
                      <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
                      <Tooltip formatter={(value) => peso(Number(value))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                      <Bar dataKey="variance" name="Variance" radius={[3, 3, 0, 0]}>
                        {summary.trend.map((t, i) => <Cell key={i} fill={t.variance > 0 ? "#dc2626" : "#059669"} />)}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                  <p className="text-[10px] text-zinc-400 mt-2">Positive (red) = spent over the daily budget cap{selected?.budget_per_head_day ? ` (₱${num(selected.budget_per_head_day)}/head/day × ${selected.population ?? 0} heads)` : ""}.</p>
                </div>
              </>
            )}
          </div>
        )
      ) : (
        // ═══ Records ═══
        <div className="space-y-5">
          <div className="flex justify-end">
            <Button variant="primary" onClick={openNew} className="px-4 py-2.5 flex items-center gap-2"><Plus className="h-4 w-4" /> New Budget</Button>
          </div>
          {formOpen && (
            <RecordForm
              initial={editing ? {
                scope: editing.scope, name: editing.name, allocated_amount: num(editing.allocated_amount),
                period_start: editing.period_start, period_end: editing.period_end, population: editing.population,
                budget_per_head_day: editing.budget_per_head_day ? num(editing.budget_per_head_day) : null,
                budget_per_head_month: editing.budget_per_head_month ? num(editing.budget_per_head_month) : null,
                budget_per_head_year: editing.budget_per_head_year ? num(editing.budget_per_head_year) : null,
              } : EMPTY}
              editingId={editing?.id ?? null} onSaved={onSaved} onCancel={() => { setFormOpen(false); setEditing(null); }}
            />
          )}
          <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-x-auto">
            {budgets.length === 0 ? <div className="py-16 text-center text-xs text-zinc-400">No budgets yet.</div> : (
              <table className="w-full text-xs">
                <thead className="bg-zinc-50 border-b border-zinc-100"><tr>{["Name", "Scope", "Allocated", "Period", "₱/head/day", "Population", ""].map((h) => <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>)}</tr></thead>
                <tbody className="divide-y divide-zinc-100">
                  {budgets.map((b) => (
                    <tr key={b.id} className="hover:bg-zinc-50/60">
                      <td className="px-4 py-3 font-semibold text-zinc-800">{b.name || `Budget #${b.id}`}</td>
                      <td className="px-4 py-3 text-zinc-500">{b.scope}</td>
                      <td className="px-4 py-3 font-mono text-zinc-700">{peso(num(b.allocated_amount))}</td>
                      <td className="px-4 py-3 text-zinc-500">{b.period_start ?? "—"} → {b.period_end ?? "—"}</td>
                      <td className="px-4 py-3 text-zinc-500">{b.budget_per_head_day ? peso(num(b.budget_per_head_day)) : "—"}</td>
                      <td className="px-4 py-3 text-zinc-500">{b.population ?? "—"}</td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1">
                          <button onClick={() => openEdit(b)} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 cursor-pointer"><Pencil className="h-3.5 w-3.5" /></button>
                          <button onClick={() => deleteBudget(b.id).then(load)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer"><Trash2 className="h-3.5 w-3.5" /></button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
