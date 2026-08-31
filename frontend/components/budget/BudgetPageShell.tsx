"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/Button";
import {
  FiscalYearBudget, FiscalYearSummary, BudgetLedgerEntry, LedgerFilter,
  listFiscalYears, getFiscalYearSummary, setupFiscalYear, getLedger, addManualAdjustment,
  type BudgetApiPrefix,
} from "@/services/budgetService";
import { AuditTrail } from "@/components/audit/AuditTrail";
import { AuditTimestamp } from "@/components/audit/AuditTimestamp";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";

const peso = (n: number) =>
  `PHP ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const num = (s: string | null | undefined) => (s ? parseFloat(s) : 0);
const currentYear = new Date().getFullYear();

export type BudgetPageShellProps = {
  apiPrefix: BudgetApiPrefix;
  canMutate: boolean;
  crumbs: [string, string?][];
  homeHref: string;
};

function Crumbs({ crumbs }: { crumbs: [string, string?][] }) {
  return (
    <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
      {crumbs.map(([label, href], index) => (
        <React.Fragment key={`${label}-${index}`}>
          {href ? (
            <Link href={href} className="hover:text-emerald-700">{label}</Link>
          ) : (
            <span className="font-bold text-warm-600">{label}</span>
          )}
          {index < crumbs.length - 1 && <span>/</span>}
        </React.Fragment>
      ))}
    </div>
  );
}

function Label({ children }: { children: React.ReactNode }) {
  return <label className="block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1">{children}</label>;
}
const inp = "w-full px-3 py-2 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500";
const card = "bg-white border border-warm-100 rounded-2xl shadow-sm p-6";

// ───── Fiscal Year Selector ──────────────────────────────────────────────────
function YearSelector({ years, selected, onChange }: {
  years: number[]; selected: number; onChange: (y: number) => void;
}) {
  return (
    <div className="flex items-center gap-2">
      <span className="text-sm font-bold text-warm-500 uppercase tracking-wider">Fiscal Year</span>
      <select
        value={selected}
        onChange={(e) => onChange(parseInt(e.target.value))}
        className="px-3 py-1.5 text-base border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        {years.map((y) => (
          <option key={y} value={y}>FY {y}</option>
        ))}
        {!years.includes(selected) && <option value={selected}>FY {selected}</option>}
      </select>
    </div>
  );
}

function FiscalYearSetupSection({ existingYears, onCreated, apiPrefix }: {
  existingYears: number[]; onCreated: (year: number) => void;
  apiPrefix: BudgetApiPrefix;
}) {
  const nextYear = (existingYears.length > 0 ? Math.max(...existingYears) : currentYear) + 1;
  const [fiscalYear, setFiscalYear] = useState(nextYear);
  const [allocated, setAllocated] = useState("");
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setErr(null);
    if (!allocated || parseFloat(allocated) <= 0) { setErr("Allocated amount required."); return; }
    setSaving(true);
    try {
      await setupFiscalYear({ fiscal_year: fiscalYear, allocated_amount: parseFloat(allocated) }, apiPrefix);
      setAllocated("");
      onCreated(fiscalYear);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className={card}>
      <h2 className="text-sm font-extrabold text-warm-500 uppercase tracking-wider mb-4">Fiscal Year Setup</h2>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <Label>Fiscal Year</Label>
            <input
              type="number" min="2020" max="2100"
              value={fiscalYear} onChange={(e) => setFiscalYear(parseInt(e.target.value))}
              className={inp}
            />
          </div>
          <div>
            <Label>Allocated Amount (PHP)</Label>
            <input type="number" min="0" step="0.01" value={allocated} onChange={(e) => setAllocated(e.target.value)} className={inp} />
          </div>
        </div>
        {err && <p className="text-sm text-red-500">{err}</p>}
        <Button type="submit" disabled={saving} className="text-base">
          {saving ? "Creating..." : `Setup FY ${fiscalYear}`}
        </Button>
        <p className="text-xs text-warm-400">Budget per head per day is configured in Settings.</p>
      </form>
    </div>
  );
}

// ───── Summary: three cards only ──────────────────────────────────────────────
function SummarySection({ summary, notice }: {
  summary: FiscalYearSummary | null; notice?: string;
}) {
  if (notice || !summary) {
    return (
      <div className={card}>
        <h2 className="text-sm font-extrabold text-warm-500 uppercase tracking-wider mb-4">Fiscal Year Summary</h2>
        <p className="text-base text-warm-400">{notice ?? "No budget allocated for this fiscal year."}</p>
      </div>
    );
  }

  const allocated = num(summary.allocated_amount);
  const deductions = num(summary.total_deductions);
  const remaining = num(summary.remaining);

  const kpis = [
    { label: "Allocated", value: peso(allocated), color: "text-warm-800" },
    { label: "Total Deductions", value: peso(deductions), color: "text-red-600" },
    { label: "Remaining", value: peso(remaining), color: remaining >= 0 ? "text-emerald-700" : "text-red-600" },
  ];

  return (
    <div className={card}>
      <h2 className="text-sm font-extrabold text-warm-500 uppercase tracking-wider mb-4">Fiscal Year Summary</h2>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {kpis.map((k) => (
          <div key={k.label} className="bg-warm-50 rounded-xl p-4 text-center">
            <div className="text-xs font-bold text-warm-400 uppercase tracking-wider mb-1">{k.label}</div>
            <div className={`text-lg font-extrabold ${k.color}`}>{k.value}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ───── Manual Adjustment (RND only) ───────────────────────────────────────────
function ManualAdjustSection({ fiscalYear, onAdjusted, apiPrefix }: {
  fiscalYear: number; onAdjusted: () => void;
  apiPrefix: BudgetApiPrefix;
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
      await addManualAdjustment({ fiscal_year: fiscalYear, type, amount: parseFloat(amount), reason: reason.trim(), reference: reference || null }, apiPrefix);
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
      <h2 className="text-sm font-extrabold text-warm-500 uppercase tracking-wider mb-4">Manual Adjustment</h2>
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
            <Label>Amount (PHP)</Label>
            <input type="number" min="0.01" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} className={inp} />
          </div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <Label>Reason</Label>
            <input value={reason} onChange={(e) => setReason(e.target.value)} className={inp} />
          </div>
          <div>
            <Label>Reference (optional)</Label>
            <input value={reference} onChange={(e) => setReference(e.target.value)} className={inp} />
          </div>
        </div>
        {err && <p className="text-sm text-red-500">{err}</p>}
        <Button type="submit" disabled={saving} className="text-base">
          {saving ? "Saving..." : "Add Adjustment"}
        </Button>
      </form>
    </div>
  );
}

// ───── Ledger Table ───────────────────────────────────────────────────────────
const TYPE_LABELS: Record<string, string> = {
  po_deduction: "PO Deduction",
  manual_addition: "Manual Addition",
  manual_deduction: "Manual Deduction",
};

const FILTERS: { key: LedgerFilter; label: string }[] = [
  { key: "all", label: "All" },
  { key: "system", label: "System (PO deductions)" },
  { key: "manual", label: "Manual" },
];

function LedgerSection({ entries, loading, filter, onFilter, meta, page, onPage }: {
  entries: BudgetLedgerEntry[]; loading: boolean; filter: LedgerFilter; onFilter: (f: LedgerFilter) => void;
  meta: PaginationMeta | null; page: number; onPage: (page: number) => void;
}) {
  return (
    <div className={card}>
      <div className="flex items-center justify-between gap-4 flex-wrap mb-4">
        <h2 className="text-sm font-extrabold text-warm-500 uppercase tracking-wider">Ledger</h2>
        <select
          value={filter}
          onChange={(e) => onFilter(e.target.value as LedgerFilter)}
          className="px-3 py-1.5 text-sm border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          {FILTERS.map((f) => <option key={f.key} value={f.key}>{f.label}</option>)}
        </select>
      </div>
      {loading ? (
        <p className="text-base text-warm-400">Loading...</p>
      ) : entries.length === 0 ? (
        <p className="text-base text-warm-400">No ledger entries for this fiscal year.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-base">
            <thead>
              <tr className="border-b border-warm-100">
                <th className="text-left py-2 px-3 text-xs font-extrabold text-warm-400 uppercase tracking-wider">Date</th>
                <th className="text-left py-2 px-3 text-xs font-extrabold text-warm-400 uppercase tracking-wider">Type</th>
                <th className="text-right py-2 px-3 text-xs font-extrabold text-warm-400 uppercase tracking-wider">Amount</th>
                <th className="text-left py-2 px-3 text-xs font-extrabold text-warm-400 uppercase tracking-wider">Reason</th>
                <th className="text-left py-2 px-3 text-xs font-extrabold text-warm-400 uppercase tracking-wider">Reference</th>
                <th className="text-left py-2 px-3 text-xs font-extrabold text-warm-400 uppercase tracking-wider">Actor</th>
              </tr>
            </thead>
            <tbody>
              {entries.map((e) => (
                <tr key={e.id} className="border-b border-warm-100 hover:bg-warm-50">
                  <td className="py-2 px-3 text-warm-500">{e.created_at ? e.created_at.slice(0, 10) : "-"}</td>
                  <td className="py-2 px-3">{TYPE_LABELS[e.type] ?? e.type}</td>
                  <td className={`py-2 px-3 text-right font-bold ${e.signed_amount >= 0 ? "text-emerald-600" : "text-red-500"}`}>
                    {e.signed_amount >= 0 ? "+" : "-"}{peso(Math.abs(e.signed_amount))}
                  </td>
                  <td className="py-2 px-3 text-warm-500">{e.reason ?? "-"}</td>
                  <td className="py-2 px-3 text-warm-500">{e.reference ?? e.po_number ?? "-"}</td>
                  <td className="py-2 px-3 text-warm-500">{e.actor.name}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pagination meta={meta} page={page} onPageChange={onPage} />
    </div>
  );
}

// ───── Page ───────────────────────────────────────────────────────────────────
export function BudgetPageShell({ apiPrefix, canMutate, crumbs }: BudgetPageShellProps) {
  const [budgets, setBudgets] = useState<FiscalYearBudget[]>([]);
  const [selectedYear, setSelectedYear] = useState(currentYear);
  const [summary, setSummary] = useState<FiscalYearSummary | null>(null);
  const [notice, setNotice] = useState<string | undefined>();
  const [entries, setEntries] = useState<BudgetLedgerEntry[]>([]);
  const [ledgerLoading, setLedgerLoading] = useState(false);
  const [ledgerFilter, setLedgerFilter] = useState<LedgerFilter>("all");
  const [ledgerPage, setLedgerPage] = useState(1);
  const [ledgerMeta, setLedgerMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(true);

  const years = budgets.map((b) => b.fiscal_year).sort((a, b) => b - a);
  const selectedBudget = budgets.find((budget) => budget.fiscal_year === selectedYear) ?? null;

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const list = await listFiscalYears(apiPrefix);
      setBudgets(list);
    } finally {
      setLoading(false);
    }
  }, [apiPrefix]);

  const loadSummary = useCallback(async (year: number) => {
    const res = await getFiscalYearSummary(year, apiPrefix);
    setSummary(res.data);
    setNotice(res.notice);
  }, [apiPrefix]);

  const loadLedger = useCallback(async (year: number, filter: LedgerFilter, page: number) => {
    setLedgerLoading(true);
    try {
      const result = await getLedger(year, filter, apiPrefix, page);
      setEntries(result.data);
      setLedgerMeta(result.meta);
    } finally {
      setLedgerLoading(false);
    }
  }, [apiPrefix]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { void loadSummary(selectedYear); }, [loadSummary, selectedYear]);
  useEffect(() => { setLedgerPage(1); }, [selectedYear, ledgerFilter]);
  useEffect(() => { void loadLedger(selectedYear, ledgerFilter, ledgerPage); }, [loadLedger, selectedYear, ledgerFilter, ledgerPage]);

  function refresh() { load(); loadSummary(selectedYear); loadLedger(selectedYear, ledgerFilter, ledgerPage); }

  return (
    <div className="min-h-screen bg-warm-50 p-4 sm:p-8">
      <div className="max-w-5xl mx-auto space-y-6">
        <Crumbs crumbs={crumbs} />

        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 className="text-2xl font-extrabold text-warm-800">Budget</h1>
            <p className="text-base text-warm-400 mt-1">Fiscal year allocation and shared budget ledger</p>
          </div>
          {years.length > 0 && (
            <YearSelector years={years} selected={selectedYear} onChange={(y) => { setSelectedYear(y); }} />
          )}
        </div>

        {loading ? (
          <div className="text-base text-warm-400 py-12 text-center">Loading...</div>
        ) : (
          <>
            {/* Fiscal Year Setup - top of page (RND only) */}
            {canMutate && (
              <FiscalYearSetupSection
                apiPrefix={apiPrefix}
                existingYears={years}
                onCreated={(year) => { load(); setSelectedYear(year); }}
              />
            )}

            {/* Three summary cards */}
            <SummarySection summary={summary} notice={notice} />

            {selectedBudget && (
              <div className="rounded-2xl border border-warm-200 bg-white p-5 shadow-sm">
                <h2 className="text-sm font-extrabold uppercase tracking-wider text-warm-500">Budget record</h2>
                <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                  <div>
                    <dt className="text-xs font-bold uppercase tracking-wider text-warm-400">Created by</dt>
                    <dd className="mt-1 font-semibold text-warm-800">{selectedBudget.creator?.name ?? "System"}</dd>
                  </div>
                  <div>
                    <dt className="text-xs font-bold uppercase tracking-wider text-warm-400">Created</dt>
                    <dd className="mt-1 text-warm-700"><AuditTimestamp value={selectedBudget.created_at} /></dd>
                  </div>
                </dl>
              </div>
            )}

            {/* Manual Adjust (RND only, only when budget exists) */}
            {canMutate && summary && (
              <ManualAdjustSection apiPrefix={apiPrefix} fiscalYear={selectedYear} onAdjusted={refresh} />
            )}

            {/* Ledger log */}
            <LedgerSection entries={entries} loading={ledgerLoading} filter={ledgerFilter} onFilter={setLedgerFilter} meta={ledgerMeta} page={ledgerPage} onPage={setLedgerPage} />

            {selectedBudget && (
              <AuditTrail
                path={`/api/${apiPrefix}/budgets/${selectedBudget.id}/activity`}
                title={`FY ${selectedBudget.fiscal_year} budget activity`}
              />
            )}
          </>
        )}
      </div>
    </div>
  );
}
