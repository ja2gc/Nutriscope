"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import {
  ResponsiveContainer, AreaChart, Area, BarChart, Bar,
  LineChart, Line, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend, ReferenceLine,
} from "recharts";
import {
  getBudgetBurn, getPerHeadActualVsLimit, getProcurementDeductionTimeline, getSpendBySupplier,
  BudgetBurnData, PerHeadData, DeductionTimelineData, SpendBySupplierData,
} from "@/services/insightsService";

const COLORS = ["#059669", "#0ea5e9", "#f59e0b", "#dc2626", "#8b5cf6", "#ec4899"];
const peso = (n: number) =>
  `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const currentYear = new Date().getFullYear();

function Crumbs() {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
      <span>Food Service</span><span>/</span>
      <span className="font-bold text-zinc-600">Insights</span>
    </div>
  );
}

const card = "bg-white border border-zinc-100 rounded-2xl shadow-sm p-6";

// ── Fiscal year selector ─────────────────────────────────────────────────────
function YearSelector({ year, onChange }: { year: number; onChange: (y: number) => void }) {
  const options = [currentYear - 1, currentYear, currentYear + 1];
  return (
    <div className="flex items-center gap-2">
      <span className="text-xs font-bold text-zinc-500 uppercase tracking-wider">Fiscal Year</span>
      <select
        value={year}
        onChange={(e) => onChange(parseInt(e.target.value))}
        className="px-3 py-1.5 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        {options.map((y) => <option key={y} value={y}>FY {y}</option>)}
      </select>
    </div>
  );
}

// ── Category picker ──────────────────────────────────────────────────────────
type Category = "budget-burn" | "per-head" | "timeline" | "supplier";
const CATEGORIES: { key: Category; label: string }[] = [
  { key: "budget-burn", label: "Budget Burn" },
  { key: "per-head", label: "Per-Head vs Limit" },
  { key: "timeline", label: "Deduction Timeline" },
  { key: "supplier", label: "Spend by Supplier" },
];

// ── Chart: Budget Burn ────────────────────────────────────────────────────────
function BudgetBurnChart({ year }: { year: number }) {
  const [data, setData] = useState<BudgetBurnData | null>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true); setErr(null);
    getBudgetBurn(year).then(setData).catch((e) => setErr(e.message)).finally(() => setLoading(false));
  }, [year]);

  if (loading) return <p className="text-sm text-zinc-400">Loading…</p>;
  if (err) return <p className="text-sm text-red-500">{err}</p>;
  if (!data) return null;

  const { points, summary } = data;

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-3 gap-4">
        {[
          { label: "Allocated", val: peso(summary.allocated), color: "text-zinc-800" },
          { label: "Total Spent", val: peso(summary.total_deducted), color: "text-red-500" },
          { label: "Remaining", val: peso(summary.remaining), color: summary.remaining >= 0 ? "text-emerald-600" : "text-red-600" },
        ].map((k) => (
          <div key={k.label} className="bg-zinc-50 rounded-xl p-4 text-center">
            <div className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-1">{k.label}</div>
            <div className={`text-base font-extrabold ${k.color}`}>{k.val}</div>
          </div>
        ))}
      </div>
      <ResponsiveContainer width="100%" height={280}>
        <AreaChart data={points} margin={{ top: 8, right: 16, left: 16, bottom: 0 }}>
          <defs>
            <linearGradient id="burnGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#dc2626" stopOpacity={0.15} />
              <stop offset="95%" stopColor="#dc2626" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
          <XAxis dataKey="month" tick={{ fontSize: 10 }} />
          <YAxis tickFormatter={(v) => `₱${(v / 1000).toFixed(0)}k`} tick={{ fontSize: 10 }} />
          <Tooltip formatter={(v: unknown) => peso(Number(v))} />
          <Legend />
          <ReferenceLine y={summary.allocated} stroke="#059669" strokeDasharray="4 2" label={{ value: "Allocation", fontSize: 10, fill: "#059669" }} />
          <Area type="monotone" dataKey="cumulative_spent" name="Cumulative Spent" stroke="#dc2626" fill="url(#burnGrad)" strokeWidth={2} dot={false} />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

// ── Chart: Per-Head Actual vs Limit ──────────────────────────────────────────
function PerHeadChart({ year }: { year: number }) {
  const [data, setData] = useState<PerHeadData | null>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true); setErr(null);
    getPerHeadActualVsLimit(year).then(setData).catch((e) => setErr(e.message)).finally(() => setLoading(false));
  }, [year]);

  if (loading) return <p className="text-sm text-zinc-400">Loading…</p>;
  if (err) return <p className="text-sm text-red-500">{err}</p>;
  if (!data) return null;

  const { points, summary } = data;
  const chartData = points.filter((p) => !p.pending).map((p) => ({
    span: p.span ?? `PO ${p.po_id}`,
    actual: p.actual_per_head ?? 0,
    estimated: p.estimated_per_head ?? 0,
    limit: summary.limit_per_head ?? 0,
  }));
  const pendingCount = points.filter((p) => p.pending).length;

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-6 text-sm">
        {summary.limit_per_head !== null && (
          <span className="text-zinc-500">Limit: <strong className="text-zinc-800">{peso(summary.limit_per_head)}/head/day</strong></span>
        )}
        {summary.avg_actual !== null && (
          <span className="text-zinc-500">Avg Actual: <strong className="text-emerald-700">{peso(summary.avg_actual)}</strong></span>
        )}
        {pendingCount > 0 && (
          <span className="text-zinc-400">{pendingCount} PO{pendingCount > 1 ? "s" : ""} pending</span>
        )}
      </div>
      {chartData.length === 0 ? (
        <p className="text-sm text-zinc-400">No completed POs with per-head data yet.</p>
      ) : (
        <ResponsiveContainer width="100%" height={280}>
          <BarChart data={chartData} margin={{ top: 8, right: 16, left: 16, bottom: 32 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
            <XAxis dataKey="span" tick={{ fontSize: 9 }} angle={-25} textAnchor="end" interval={0} />
            <YAxis tickFormatter={(v) => `₱${v}`} tick={{ fontSize: 10 }} />
            <Tooltip formatter={(v: unknown) => peso(Number(v))} />
            <Legend />
            {summary.limit_per_head !== null && (
              <ReferenceLine y={summary.limit_per_head} stroke="#dc2626" strokeDasharray="4 2" label={{ value: "Limit", fontSize: 10, fill: "#dc2626" }} />
            )}
            <Bar dataKey="estimated" name="Estimated / Head / Day" fill="#cbd5e1" radius={[3, 3, 0, 0]} />
            <Bar dataKey="actual" name="Actual / Head / Day" fill="#059669" radius={[3, 3, 0, 0]}
              label={{ position: "top", formatter: (v: unknown) => `₱${Number(v).toFixed(0)}`, fontSize: 9 }}
            />
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}

// ── Chart: Deduction Timeline ─────────────────────────────────────────────────
function TimelineChart({ year }: { year: number }) {
  const [data, setData] = useState<DeductionTimelineData | null>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true); setErr(null);
    getProcurementDeductionTimeline(year).then(setData).catch((e) => setErr(e.message)).finally(() => setLoading(false));
  }, [year]);

  if (loading) return <p className="text-sm text-zinc-400">Loading…</p>;
  if (err) return <p className="text-sm text-red-500">{err}</p>;
  if (!data || data.timeline.length === 0) return <p className="text-sm text-zinc-400">No procurement entries for FY {year}.</p>;

  const chartData = data.timeline.map((e) => ({
    date: e.date?.slice(5) ?? "",
    amount: e.type === "po" ? (e.total_cost ?? 0) : (e.type === "manual_addition" ? (e.amount ?? 0) : -(e.amount ?? 0)),
    type: e.type,
    label: e.type === "po" ? (e.reference ?? "PO") : e.type.replace("manual_", ""),
  }));

  return (
    <div className="space-y-4">
      <p className="text-xs text-zinc-400">{data.timeline.length} entries for FY {year}</p>
      <ResponsiveContainer width="100%" height={280}>
        <LineChart data={chartData} margin={{ top: 8, right: 16, left: 16, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
          <XAxis dataKey="date" tick={{ fontSize: 10 }} />
          <YAxis tickFormatter={(v) => `₱${(Math.abs(v) / 1000).toFixed(0)}k`} tick={{ fontSize: 10 }} />
          <Tooltip formatter={(v: unknown) => peso(Number(v))} />
          <Line type="monotone" dataKey="amount" name="Amount" stroke="#0ea5e9" strokeWidth={2} dot={{ r: 3, fill: "#0ea5e9" }} />
        </LineChart>
      </ResponsiveContainer>
      <div className="overflow-x-auto">
        <table className="w-full text-xs">
          <thead>
            <tr className="border-b border-zinc-100">
              {["Date", "Type", "Amount", "Reference", "Span", "Est/Head", "Actual/Head"].map((h) => (
                <th key={h} className="text-left py-2 px-2 font-bold text-zinc-400 uppercase tracking-wider">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.timeline.map((e, i) => (
              <tr key={i} className="border-b border-zinc-50 hover:bg-zinc-50">
                <td className="py-1.5 px-2 text-zinc-500">{e.date ?? "—"}</td>
                <td className="py-1.5 px-2">{e.type === "po" ? "PO" : e.type.replace("manual_", "")}</td>
                <td className={`py-1.5 px-2 font-bold ${e.type === "manual_addition" ? "text-emerald-600" : "text-red-500"}`}>
                  {e.type === "manual_addition" ? "+" : "−"}{peso(e.total_cost ?? e.amount ?? 0)}
                </td>
                <td className="py-1.5 px-2 text-zinc-500">{e.reference ?? "—"}</td>
                <td className="py-1.5 px-2 text-zinc-500">{e.procurement_span ?? "—"}</td>
                <td className="py-1.5 px-2 text-zinc-500">{e.estimated_per_head != null ? peso(e.estimated_per_head) : "—"}</td>
                <td className="py-1.5 px-2 text-zinc-500">{e.actual_per_head != null ? peso(e.actual_per_head) : "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── Chart: Spend by Supplier ──────────────────────────────────────────────────
function SupplierChart({ year }: { year: number }) {
  const [data, setData] = useState<SpendBySupplierData | null>(null);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true); setErr(null);
    getSpendBySupplier(year).then(setData).catch((e) => setErr(e.message)).finally(() => setLoading(false));
  }, [year]);

  if (loading) return <p className="text-sm text-zinc-400">Loading…</p>;
  if (err) return <p className="text-sm text-red-500">{err}</p>;
  if (!data || data.points.length === 0) return <p className="text-sm text-zinc-400">No completed PO spend for FY {year}.</p>;

  return (
    <div className="space-y-4">
      <p className="text-xs text-zinc-400">Total spend: <strong>{peso(data.total)}</strong></p>
      <div className="flex flex-col md:flex-row gap-6 items-center">
        <ResponsiveContainer width="100%" height={240}>
          <PieChart>
            <Pie
              data={data.points}
              dataKey="total"
              nameKey="supplier"
              cx="50%"
              cy="50%"
              outerRadius={90}
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              label={(props: any) => `${String(props.name ?? "")} (${((Number(props.percent ?? 0)) * 100).toFixed(0)}%)`}
              labelLine={false}
            >
              {data.points.map((_, i) => (
                <Cell key={i} fill={COLORS[i % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip formatter={(v: unknown) => peso(Number(v))} />
          </PieChart>
        </ResponsiveContainer>
        <div className="flex-1 w-full">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-zinc-100">
                <th className="text-left py-1.5 px-2 text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Supplier</th>
                <th className="text-right py-1.5 px-2 text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Total</th>
                <th className="text-right py-1.5 px-2 text-[10px] font-bold text-zinc-400 uppercase tracking-wider">%</th>
              </tr>
            </thead>
            <tbody>
              {data.points.map((p, i) => (
                <tr key={i} className="border-b border-zinc-50 hover:bg-zinc-50">
                  <td className="py-1.5 px-2 flex items-center gap-2">
                    <span className="inline-block w-3 h-3 rounded-full" style={{ background: COLORS[i % COLORS.length] }} />
                    {p.supplier}
                  </td>
                  <td className="py-1.5 px-2 text-right font-bold">{peso(p.total)}</td>
                  <td className="py-1.5 px-2 text-right text-zinc-400">
                    {data.total > 0 ? ((p.total / data.total) * 100).toFixed(1) : 0}%
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────
export function BudgetInsightsPanel({ year }: { year: number }) {
  const [category, setCategory] = useState<Category>("budget-burn");

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap gap-2">
        {CATEGORIES.map((c) => (
          <button
            key={c.key}
            onClick={() => setCategory(c.key)}
            className={`px-4 py-2 rounded-xl text-sm font-bold transition-colors ${
              category === c.key
                ? "bg-emerald-600 text-white shadow-sm"
                : "bg-white text-zinc-500 border border-zinc-200 hover:border-emerald-400"
            }`}
          >
            {c.label}
          </button>
        ))}
      </div>

      <div className={`${card} min-h-[340px]`}>
        <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider mb-4">
          {CATEGORIES.find((c) => c.key === category)?.label} â€” FY {year}
        </h2>
        {category === "budget-burn" && <BudgetBurnChart year={year} />}
        {category === "per-head" && <PerHeadChart year={year} />}
        {category === "timeline" && <TimelineChart year={year} />}
        {category === "supplier" && <SupplierChart year={year} />}
      </div>
    </div>
  );
}

export default function InsightsPage() {
  const [year, setYear] = useState(currentYear);
  const [category, setCategory] = useState<Category>("budget-burn");

  return (
    <div className="min-h-screen bg-zinc-50 p-4 sm:p-8">
      <div className="max-w-5xl mx-auto space-y-6">
        <Crumbs />

        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-extrabold text-zinc-800">Insights</h1>
            <p className="text-sm text-zinc-400 mt-1">Budget analytics and procurement trends</p>
          </div>
          <YearSelector year={year} onChange={setYear} />
        </div>

        {/* Category picker */}
        <div className="flex flex-wrap gap-2">
          {CATEGORIES.map((c) => (
            <button
              key={c.key}
              onClick={() => setCategory(c.key)}
              className={`px-4 py-2 rounded-xl text-sm font-bold transition-colors ${
                category === c.key
                  ? "bg-emerald-600 text-white shadow-sm"
                  : "bg-white text-zinc-500 border border-zinc-200 hover:border-emerald-400"
              }`}
            >
              {c.label}
            </button>
          ))}
        </div>

        {/* Active chart */}
        <div className={`${card} min-h-[340px]`}>
          <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider mb-4">
            {CATEGORIES.find((c) => c.key === category)?.label} — FY {year}
          </h2>
          {category === "budget-burn" && <BudgetBurnChart year={year} />}
          {category === "per-head" && <PerHeadChart year={year} />}
          {category === "timeline" && <TimelineChart year={year} />}
          {category === "supplier" && <SupplierChart year={year} />}
        </div>
      </div>
    </div>
  );
}
