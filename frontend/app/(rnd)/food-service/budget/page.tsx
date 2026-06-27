"use client";

import React, { useEffect, useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { Tabs } from "@/components/ui/Tabs";
import { useAuth } from "@/contexts/AuthContext";
import {
  FiscalYearBudget, FiscalYearSummary, BudgetLedgerEntry, LedgerFilter,
  listFiscalYears, getFiscalYearSummary, setupFiscalYear, getLedger, addManualAdjustment,
} from "@/services/budgetService";
import { BudgetInsightsPanel } from "../insights/page";

const peso = (n: number) =>
  `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const num = (s: string | null | undefined) => (s ? parseFloat(s) : 0);
const currentYear = new Date().getFullYear();

function Crumbs() {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
      <span>Food Service</span><span>/</span>
      <span className="font-bold text-zinc-600">Budget</span>
    </div>
  );
}

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1">{children}</label>;
}
const inp = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500";
const card = "bg-white border border-zinc-100 rounded-2xl shadow-sm p-6";
type BudgetTab = "budget" | "insights";
const PAGE_TABS: { key: BudgetTab; label: string }[] = [
  { key: "budget", label: "Budget" },
  { key: "insights", label: "Insights" },
];

// ───── Fiscal Year Selector ──────────────────────────────────────────────────
function YearSelector({ years, selected, onChange }: {
  years: number[]; selected: number; onChange: (y: number) => void;
}) {
  return (
    <div className="flex items-center gap-2">
      <span className="text-xs font-bold text-zinc-500 uppercase tracking-wider">Fiscal Year</span>
      <select
        value={selected}
        onChange={(e) => onChange(parseInt(e.target.value))}
        className="px-3 py-1.5 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        {years.map((y) => (
          <option key={y} value={y}>FY {y}</option>
        ))}
        {!years.includes(selected) && <option value={selected}>FY {selected}</option>}
      </select>
    </div>
  );
}

// ───── Section 1: Fiscal Year Summary ───────────────────────────────────────
function SummarySection({ summary, notice }: {
  summary: FiscalYearSummary | null; notice?: string;
}) {
  if (notice || !summary) {
    return (
      <div className={card}>
        <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider mb-4">Fiscal Year Summary</h2>
        <p className="text-sm text-zinc-400">{notice ?? "No budget allocated for this fiscal year."}</p>
      </div>
    );
  }

  const allocated = num(summary.allocated_amount);
  const remaining = num(summary.remaining_balance);
  const poDeduc = num(summary.total_po_deductions);
  const manAdd = num(summary.total_manual_additions);
  const manDeduc = num(summary.total_manual_deductions);
  const pct = allocated > 0 ? Math.min(100, ((allocated - remaining) / allocated) * 100) : 0;

  const kpis = [
    { label: "Allocated", value: peso(allocated), color: "text-zinc-800" },
    { label: "PO Deductions", value: peso(poDeduc), color: "text-red-600" },
    { label: "Manual Additions", value: peso(manAdd), color: "text-emerald-600" },
    { label: "Manual Deductions", value: peso(manDeduc), color: "text-amber-600" },
    { label: remaining >= 0 ? "Remaining" : "Over Allocation", value: peso(Math.abs(remaining)), color: remaining >= 0 ? "text-emerald-700" : "text-red-600" },
  ];

  return (
    <div className={card}>
      <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider mb-4">Fiscal Year Summary</h2>
      <div className="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-4">
        {kpis.map((k) => (
          <div key={k.label} className="bg-zinc-50 rounded-xl p-4 text-center">
            <div className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-1">{k.label}</div>
            <div className={`text-lg font-extrabold ${k.color}`}>{k.value}</div>
          </div>
        ))}
      </div>
      <div className="flex items-center gap-3">
        <div className="flex-1 bg-zinc-100 rounded-full h-2">
          <div
            className={`h-2 rounded-full transition-all ${remaining < 0 ? "bg-red-500" : pct > 80 ? "bg-amber-400" : "bg-emerald-500"}`}
            style={{ width: `${pct}%` }}
          />
        </div>
        <span className="text-xs font-bold text-zinc-500">{pct.toFixed(1)}% spent</span>
      </div>
      {summary.per_head_day_limit && (
        <p className="text-xs text-zinc-400 mt-2">Per-head day limit: {peso(num(summary.per_head_day_limit))}</p>
      )}
    </div>
  );
}

// ───── Section 2: Manual Adjustment (RND only) ───────────────────────────────
function ManualAdjustSection({ fiscalYear, onAdjusted }: {
  fiscalYear: number; onAdjusted: () => void;
}) {
  const [type, setType] = useState<"manual_addition" | "manual_deduction">("manual_addition");
  const [amount, setAmount] = useState("");
  const [reason, setReason] = useState("");
  const [reference, setReference] = useState("");
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErr(null);
    if (!amount || parseFloat(amount) <= 0) { setErr("Amount must be positive."); return; }
    if (!reason.trim()) { setErr("Reason is required."); return; }
    setSaving(true);
    try {
      await addManualAdjustment({ fiscal_year: fiscalYear, type, amount: parseFloat(amount), reason: reason.trim(), reference: reference || null });
      setAmount(""); setReason(""); setReference("");
      onAdjusted();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className={card}>
      <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider mb-4">Manual Adjustment</h2>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <Label>Type</Label>
            <select value={type} onChange={(e) => setType(e.target.value as typeof type)} className={inp}>
              <option value="manual_addition">Addition</option>
              <option value="manual_deduction">Deduction</option>
            </select>
          </div>
          <div>
            <Label>Amount (₱)</Label>
            <input type="number" min="0.01" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0.00" className={inp} />
          </div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <Label>Reason</Label>
            <input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Reason for adjustment" className={inp} />
          </div>
          <div>
            <Label>Reference (optional)</Label>
            <input value={reference} onChange={(e) => setReference(e.target.value)} placeholder="e.g. BUR-2026-05" className={inp} />
          </div>
        </div>
        {err && <p className="text-xs text-red-500">{err}</p>}
        <Button type="submit" disabled={saving} className="text-sm">
          {saving ? "Saving…" : "Add Adjustment"}
        </Button>
      </form>
    </div>
  );
}

// ───── Section 3: Ledger Table ────────────────────────────────────────────────
const TYPE_LABELS: Record<string, string> = {
  po_deduction: "PO Deduction",
  manual_addition: "Manual Addition",
  manual_deduction: "Manual Deduction",
};

const FILTERS: { key: LedgerFilter; label: string }[] = [
  { key: "all", label: "All" },
  { key: "manual", label: "Manual only" },
  { key: "po", label: "Purchase orders only" },
  { key: "manual_addition", label: "Additions" },
  { key: "manual_deduction", label: "Deductions" },
  { key: "po_deduction", label: "PO deductions" },
];

function LedgerSection({ entries, loading, filter, onFilter }: {
  entries: BudgetLedgerEntry[]; loading: boolean; filter: LedgerFilter; onFilter: (f: LedgerFilter) => void;
}) {
  return (
    <div className={card}>
      <div className="flex items-center justify-between gap-4 flex-wrap mb-4">
        <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider">Ledger</h2>
        <select
          value={filter}
          onChange={(e) => onFilter(e.target.value as LedgerFilter)}
          className="px-3 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {FILTERS.map((f) => <option key={f.key} value={f.key}>{f.label}</option>)}
        </select>
      </div>
      {loading ? (
        <p className="text-sm text-zinc-400">Loading…</p>
      ) : entries.length === 0 ? (
        <p className="text-sm text-zinc-400">No ledger entries for this fiscal year.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-zinc-100">
                <th className="text-left py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">Date</th>
                <th className="text-left py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">Type</th>
                <th className="text-right py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">Amount</th>
                <th className="text-left py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">Reference / PO</th>
                <th className="text-left py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">Span</th>
                <th className="text-left py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">Reason</th>
                <th className="text-left py-2 px-3 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">By</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((e) => (
                <tr key={e.id} className="border-b border-zinc-50 hover:bg-zinc-50">
                  <td className="py-2 px-3 text-zinc-500">{e.created_at ? e.created_at.slice(0, 10) : "—"}</td>
                  <td className="py-2 px-3">{TYPE_LABELS[e.type] ?? e.type}</td>
                  <td className={`py-2 px-3 text-right font-bold ${e.signed_amount >= 0 ? "text-emerald-600" : "text-red-500"}`}>
                    {e.signed_amount >= 0 ? "+" : "−"}{peso(Math.abs(e.signed_amount))}
                  </td>
                  <td className="py-2 px-3 text-zinc-500">{e.po_number ?? e.reference ?? "—"}</td>
                  <td className="py-2 px-3 text-zinc-500">{e.procurement_span ?? "—"}</td>
                  <td className="py-2 px-3 text-zinc-500">{e.reason ?? "—"}</td>
                  <td className="py-2 px-3 text-zinc-500">{e.created_by ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

// ───── Section 4: New Year Setup (RND only) ───────────────────────────────────
function NewYearSection({ existingYears, onCreated }: {
  existingYears: number[]; onCreated: () => void;
}) {
  const nextYear = (existingYears.length > 0 ? Math.max(...existingYears) : currentYear) + 1;
  const [fiscalYear, setFiscalYear] = useState(nextYear);
  const [allocated, setAllocated] = useState("");
  const [perHead, setPerHead] = useState("");
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErr(null);
    if (!allocated || parseFloat(allocated) <= 0) { setErr("Allocated amount required."); return; }
    setSaving(true);
    try {
      await setupFiscalYear({
        fiscal_year: fiscalYear,
        allocated_amount: parseFloat(allocated),
        per_head_day_limit: perHead ? parseFloat(perHead) : null,
      });
      setAllocated(""); setPerHead("");
      onCreated();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className={card}>
      <h2 className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider mb-4">Setup New Fiscal Year</h2>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <Label>Fiscal Year</Label>
            <input
              type="number" min="2020" max="2100"
              value={fiscalYear} onChange={(e) => setFiscalYear(parseInt(e.target.value))}
              className={inp}
            />
          </div>
          <div>
            <Label>Allocated Amount (₱)</Label>
            <input type="number" min="0" step="0.01" value={allocated} onChange={(e) => setAllocated(e.target.value)} placeholder="0.00" className={inp} />
          </div>
          <div>
            <Label>Per-Head Day Limit (₱, optional)</Label>
            <input type="number" min="0" step="0.01" value={perHead} onChange={(e) => setPerHead(e.target.value)} placeholder="0.00" className={inp} />
          </div>
        </div>
        {err && <p className="text-xs text-red-500">{err}</p>}
        <Button type="submit" disabled={saving} className="text-sm">
          {saving ? "Creating…" : `Setup FY ${fiscalYear}`}
        </Button>
      </form>
    </div>
  );
}

// ───── Page ───────────────────────────────────────────────────────────────────
export default function BudgetPage() {
  const { user } = useAuth();
  const isRnd = user?.role === "RND";
  const [activeTab, setActiveTab] = useState<BudgetTab>("budget");
  const [budgets, setBudgets] = useState<FiscalYearBudget[]>([]);
  const [selectedYear, setSelectedYear] = useState(currentYear);
  const [summary, setSummary] = useState<FiscalYearSummary | null>(null);
  const [notice, setNotice] = useState<string | undefined>();
  const [entries, setEntries] = useState<BudgetLedgerEntry[]>([]);
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [ledgerFilter, setLedgerFilter] = useState<LedgerFilter>("all");
  const [loading, setLoading] = useState(true);

  const years = budgets.map((b) => b.fiscal_year).sort((a, b) => b - a);

  async function load() {
    setLoading(true);
    try {
      const list = await listFiscalYears();
      setBudgets(list);
    } finally {
      setLoading(false);
    }
  }

  async function loadSummary(year: number) {
    const res = await getFiscalYearSummary(year);
    setSummary(res.data);
    setNotice(res.notice);
  }

  async function loadLedger(year: number, filter: LedgerFilter) {
    setLedgerLoading(true);
    try {
      setEntries(await getLedger(year, filter));
    } finally {
      setLedgerLoading(false);
    }
  }

  useEffect(() => { load(); }, []);
  useEffect(() => { loadSummary(selectedYear); }, [selectedYear]);
  useEffect(() => { loadLedger(selectedYear, ledgerFilter); }, [selectedYear, ledgerFilter]);

  function refresh() { load(); loadSummary(selectedYear); loadLedger(selectedYear, ledgerFilter); }

  return (
    <div className="min-h-screen bg-zinc-50 p-4 sm:p-8">
      <div className="max-w-5xl mx-auto space-y-6">
        <Crumbs />

        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-extrabold text-zinc-800">Budget</h1>
            <p className="text-sm text-zinc-400 mt-1">Fiscal year allocation, ledger, and insights</p>
          </div>
          {years.length > 0 && (
            <YearSelector years={years} selected={selectedYear} onChange={(y) => { setSelectedYear(y); }} />
          )}
        </div>

        <Tabs items={PAGE_TABS} value={activeTab} onChange={setActiveTab} />

        {loading ? (
          <div className="text-sm text-zinc-400 py-12 text-center">Loading…</div>
        ) : activeTab === "insights" ? (
          <BudgetInsightsPanel year={selectedYear} />
        ) : (
          <>
            {/* Section 1: Summary */}
            <SummarySection summary={summary} notice={notice} />

            {/* Section 2: Manual Adjust (RND only, only when budget exists) */}
            {isRnd && summary && (
              <ManualAdjustSection fiscalYear={selectedYear} onAdjusted={refresh} />
            )}

            {/* Section 3: Ledger */}
            <LedgerSection entries={entries} loading={ledgerLoading} filter={ledgerFilter} onFilter={setLedgerFilter} />

            {/* Section 4: New Year Setup (RND only) */}
            {isRnd && (
              <NewYearSection
                existingYears={years}
                onCreated={() => { load(); setSelectedYear(years.length > 0 ? Math.max(...years) + 1 : currentYear); }}
              />
            )}
          </>
        )}
      </div>
    </div>
  );
}
