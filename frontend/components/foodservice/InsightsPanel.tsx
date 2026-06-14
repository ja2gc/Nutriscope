"use client";

import React, { useCallback, useEffect, useState } from "react";
import { RefreshCw, AlertTriangle } from "lucide-react";
import {
  ResponsiveContainer, BarChart, Bar, LineChart, Line, XAxis, YAxis,
  CartesianGrid, Tooltip,
} from "recharts";
import {
  SpendBySupplier, CostPerHead, Consumption,
  getSpendBySupplier, getCostPerHead, getConsumption,
} from "@/services/insightsService";

const peso = (n: number) => `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const todayISO = () => new Date().toISOString().slice(0, 10);
const monthStartISO = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); };

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
      <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">{title}</h3>
      {children}
    </div>
  );
}

function Empty({ msg }: { msg: string }) {
  return <div className="h-[220px] flex items-center justify-center text-xs text-zinc-400">{msg}</div>;
}

/**
 * Analytics charts (spend/cost-per-head/consumption), rendered as a tab inside the
 * Budget page (A3 — merged from the former standalone /food-service/insights route).
 */
export function InsightsPanel() {
  const [start, setStart] = useState(monthStartISO());
  const [end, setEnd] = useState(todayISO());
  const [spend, setSpend] = useState<SpendBySupplier | null>(null);
  const [cph, setCph] = useState<CostPerHead | null>(null);
  const [cons, setCons] = useState<Consumption | null>(null);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setErr(null);
    try {
      const [s, c, k] = await Promise.all([
        getSpendBySupplier({ start, end }),
        getCostPerHead(),
        getConsumption({ start, end }),
      ]);
      setSpend(s); setCph(c); setCons(k);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load insights.");
    } finally { setLoading(false); }
  }, [start, end]);
  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <p className="text-xs text-zinc-500">Interactive analytics over real spend, menu cost, and consumption. Separate from the compliance PDFs.</p>
        <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 shrink-0"><RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh</button>
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">From</label><input type="date" value={start} onChange={(e) => setStart(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" /></div>
        <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">To</label><input type="date" value={end} onChange={(e) => setEnd(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" /></div>
      </div>

      {err && <div className="flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-xl border bg-red-50 text-red-700 border-red-200 w-fit"><AlertTriangle className="h-3.5 w-3.5" /> {err}</div>}

      <Card title="Spend by Supplier (received POs)">
        {!spend || spend.points.length === 0 ? <Empty msg="No received purchase orders in this range." /> : (
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={spend.points} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="supplier" tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <Tooltip formatter={(v) => peso(Number(v))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
              <Bar dataKey="total" name="Spend" radius={[3, 3, 0, 0]} fill="#059669" />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <Card title="Cost per Head by Menu Cycle (avg daily)">
        {!cph || cph.points.length === 0 ? <Empty msg="No menu cycles to cost yet." /> : (
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={cph.points} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="cycle" tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <Tooltip formatter={(v) => peso(Number(v))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
              <Bar dataKey="cost_per_head" name="₱/head/day" radius={[3, 3, 0, 0]} fill="#0ea5e9" />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <Card title="Consumption — Value Served per Day">
        {!cons || cons.points.length === 0 ? <Empty msg="No service days completed in this range." /> : (
          <>
            <ResponsiveContainer width="100%" height={240}>
              <LineChart data={cons.points} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                <XAxis dataKey="date" tick={{ fontSize: 10, fill: "#94a3b8" }} />
                <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
                <Tooltip formatter={(v) => peso(Number(v))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Line type="monotone" dataKey="actual" name="Served value" stroke="#059669" strokeWidth={2} dot={{ r: 2 }} />
              </LineChart>
            </ResponsiveContainer>
            <p className="text-[10px] text-zinc-400 mt-2">{cons.summary.shortfall_days} of {cons.summary.days} served day(s) had a recorded shortfall.</p>
          </>
        )}
      </Card>
    </div>
  );
}
