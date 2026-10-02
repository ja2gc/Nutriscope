"use client";

import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { Download, Loader2 } from "lucide-react";
import { Card } from "@/components/ui/Card";
import { getCensusSummary, type CensusSummary } from "@/services/reportService";

const selectClass = "w-full rounded-lg border border-warm-200 bg-white px-3 py-2 text-base text-warm-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500";

export function CensusPanel({ apiPrefix }: { apiPrefix: "rnd" | "admin" }) {
  const [year, setYear] = useState(() => Number(new Intl.DateTimeFormat("en", { year: "numeric", timeZone: "Asia/Manila" }).format(new Date())));
  const [month, setMonth] = useState<number | null>(null);
  const [summary, setSummary] = useState<CensusSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mounted, setMounted] = useState(false);

  useEffect(() => setMounted(true), []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);
    getCensusSummary(apiPrefix, year, month ?? undefined)
      .then((value) => { if (active) setSummary(value); })
      .catch((reason) => { if (active) { setSummary(null); setError(reason instanceof Error ? reason.message : "Failed to load census."); } })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [apiPrefix, year, month]);

  const riskOrder = ["Low", "Moderate", "High", "Unspecified"];
  const riskEntries = summary
    ? Object.entries(summary.by_risk).sort(([left], [right]) => riskOrder.indexOf(left) - riskOrder.indexOf(right))
    : [];
  const categoryEntries = summary
    ? Object.entries(summary.by_primary_diagnosis_category).sort(([left, leftCount], [right, rightCount]) => rightCount - leftCount || left.localeCompare(right))
    : [];
  const ageTotals = summary?.age_groups.reduce((result, group) => {
    const row = summary.age_sex[group];
    return { M: result.M + (row?.M ?? 0), F: result.F + (row?.F ?? 0), total: result.total + (row?.total ?? 0) };
  }, { M: 0, F: 0, total: 0 });

  const content = !loading && summary ? (
        <div className="space-y-6 bg-white p-5 sm:p-7">
          <header className="flex flex-wrap items-end justify-between gap-3 border-b border-warm-200 pb-4">
            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-emerald-700">Demographic Census</p>
              <h2 className="mt-1 text-2xl font-bold text-warm-900">{summary.label}</h2>
              <p className="mt-1 text-sm text-warm-500">ADIME cycles counted in month each cycle started.</p>
            </div>
            <span className="rounded-full border border-warm-200 bg-warm-50 px-3 py-1 text-xs font-semibold text-warm-700">
              {summary.status === "frozen" ? "Completed snapshot" : "Includes current data"}
            </span>
          </header>

          <section aria-label="Cycle total" className="rounded-xl border border-emerald-100 bg-emerald-50 px-5 py-4">
            <p className="text-xs font-bold uppercase tracking-wider text-emerald-700">Total</p>
            <p className="mt-1 text-3xl font-bold tabular-nums text-emerald-900">{summary.total.toLocaleString()} <span className="text-base font-medium">cycles</span></p>
          </section>

          <section aria-label="Age and sex">
            <h3 className="mb-3 text-base font-bold text-warm-800">Age and sex</h3>
            <div className="overflow-x-auto rounded-xl border border-warm-200">
              <table className="w-full min-w-[300px] border-collapse text-sm tabular-nums">
                <thead className="bg-warm-50 text-warm-600"><tr><th scope="col" className="px-4 py-2 text-left">Age</th><th scope="col" className="px-4 py-2 text-right">Male</th><th scope="col" className="px-4 py-2 text-right">Female</th><th scope="col" className="px-4 py-2 text-right">Total</th></tr></thead>
                <tbody className="divide-y divide-warm-100">
                  {summary.age_groups.map((group) => <tr key={group}><th scope="row" className="px-4 py-2 text-left font-medium text-warm-700">{group}</th><td className="px-4 py-2 text-right">{summary.age_sex[group]?.M ?? 0}</td><td className="px-4 py-2 text-right">{summary.age_sex[group]?.F ?? 0}</td><td className="px-4 py-2 text-right font-semibold">{summary.age_sex[group]?.total ?? 0}</td></tr>)}
                </tbody>
                <tfoot className="border-t border-warm-200 bg-warm-50 font-bold"><tr><th scope="row" className="px-4 py-2 text-left">Total classified</th><td className="px-4 py-2 text-right">{ageTotals?.M ?? 0}</td><td className="px-4 py-2 text-right">{ageTotals?.F ?? 0}</td><td className="px-4 py-2 text-right">{ageTotals?.total ?? 0}</td></tr></tfoot>
              </table>
            </div>
            {summary.unknown_sex > 0 && <p className="mt-2 text-sm text-warm-600">Unclassified age or sex: {summary.unknown_sex}</p>}
          </section>

          <section aria-label="By risk level">
            <h3 className="mb-3 text-base font-bold text-warm-800">By risk level</h3>
            {riskEntries.length === 0 ? <p className="text-sm text-warm-500">No cycles in selected period.</p> : (
              <div className="grid gap-3 sm:grid-cols-2">
                {riskEntries.map(([risk, count]) => <div key={risk} className="rounded-xl border border-warm-200 p-4">
                  <div className="flex items-baseline justify-between gap-3"><span className="font-semibold text-warm-700">{risk}</span><span className="font-bold tabular-nums text-warm-900">{count}</span></div>
                  <div className="mt-2 h-2 overflow-hidden rounded-full bg-warm-100" role="img" aria-label={`${risk}: ${count} of ${summary.total} cycles`}><div className="h-full rounded-full bg-emerald-600" style={{ width: `${summary.total ? count / summary.total * 100 : 0}%` }} /></div>
                </div>)}
              </div>
            )}
          </section>

          <section aria-label="By nutrition care category">
            <h3 className="mb-3 text-base font-bold text-warm-800">By nutrition care category</h3>
            {categoryEntries.length === 0 ? <p className="text-sm text-warm-500">No cycles in selected period.</p> : (
              <div className="grid gap-2 sm:grid-cols-2">
                {categoryEntries.map(([category, count]) => <div key={category} className="flex items-center justify-between gap-3 rounded-lg border border-warm-200 px-4 py-2.5 text-sm">
                  <span className="font-medium text-warm-700">{category}</span>
                  <span className="font-bold tabular-nums text-warm-900">{count}</span>
                </div>)}
              </div>
            )}
          </section>
        </div>
  ) : null;

  return (
    <>
    <Card className="min-w-0 overflow-hidden">
      <div className="flex flex-wrap items-end gap-3 border-b border-warm-100 px-5 py-4">
        <div className="min-w-[110px] flex-1 sm:flex-none">
          <label htmlFor="census-year" className="mb-1 block text-xs font-bold uppercase tracking-wider text-warm-500">Year</label>
          <select id="census-year" aria-label="Census year" className={selectClass} value={year} onChange={(event) => { setYear(Number(event.target.value)); setMonth(null); }}>
            {(summary?.available_years ?? [year]).map((value) => <option key={value} value={value}>{value}</option>)}
          </select>
        </div>
        <div className="min-w-[160px] flex-1 sm:flex-none">
          <label htmlFor="census-month" className="mb-1 block text-xs font-bold uppercase tracking-wider text-warm-500">View</label>
          <select id="census-month" aria-label="Census month" className={selectClass} value={month ?? "all"} onChange={(event) => setMonth(event.target.value === "all" ? null : Number(event.target.value))}>
            <option value="all">Full year</option>
            {summary?.available_months.map((value) => <option key={value.month} value={value.month}>{value.label}</option>)}
          </select>
        </div>
        <button type="button" onClick={() => window.print()} disabled={loading || !summary} className="inline-flex items-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">
          <Download className="h-4 w-4" aria-hidden="true" /> Save PDF
        </button>
      </div>

      {loading && <div className="flex items-center justify-center gap-2 py-16 text-sm text-warm-500"><Loader2 className="h-4 w-4 animate-spin" /> Loading census…</div>}
      {!loading && error && <p role="alert" className="px-5 py-12 text-center text-sm text-red-700">{error}</p>}
      {content && <div data-census-screen>{content}</div>}
    </Card>
    {mounted && content && createPortal(<div data-census-print>{content}</div>, document.body)}
    </>
  );
}
