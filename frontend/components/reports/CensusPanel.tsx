"use client";

import { useEffect, useState } from "react";
import { Download, Loader2 } from "lucide-react";
import { censusExportUrl, getCensusSummary, type CensusSummary } from "@/services/reportService";

const selectClass = "w-full rounded-lg border border-warm-200 bg-white px-3 py-2 text-base text-warm-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500";

export function CensusPanel({ apiPrefix }: { apiPrefix: "rnd" | "admin" }) {
  const [year, setYear] = useState(() => Number(new Intl.DateTimeFormat("en", { year: "numeric", timeZone: "Asia/Manila" }).format(new Date())));
  const [month, setMonth] = useState<number | null>(null);
  const [summary, setSummary] = useState<CensusSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
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

  const riskEntries = summary
    ? (["Low", "Moderate", "High"] as const).map((risk) => [risk, summary.by_risk[risk] ?? 0] as const)
    : [];
  const categoryEntries = summary
    ? Object.entries(summary.by_primary_diagnosis_category).sort(([left, leftCount], [right, rightCount]) => rightCount - leftCount || left.localeCompare(right))
    : [];
  const nutritionalStatusEntries = summary ? Object.entries(summary.by_nutritional_status).filter(([status]) => status !== "Unspecified") : [];
  const unclassifiedStatus = summary?.by_nutritional_status.Unspecified ?? 0;
  const unscored = summary?.by_risk.Unspecified ?? 0;
  const ageTotals = summary?.age_groups.reduce((result, group) => {
    const row = summary.age_sex[group];
    return { M: result.M + (row?.M ?? 0), F: result.F + (row?.F ?? 0), total: result.total + (row?.total ?? 0) };
  }, { M: 0, F: 0, total: 0 });

  const content = !loading && summary ? (
        <div className="space-y-5 py-5">
          <header className="flex flex-wrap items-end justify-between gap-4 border-b border-warm-200 pb-4">
            <div>
              <p className="text-xs font-bold uppercase tracking-widest text-emerald-700">Demographic Census</p>
              <h2 className="mt-1 text-2xl font-bold text-warm-900">{summary.label}</h2>
              <p className="mt-1 text-sm text-warm-500">ADIME cycles counted in month each cycle started.</p>
            </div>
            <div className="text-left sm:text-right">
              <p className="text-3xl font-bold tabular-nums text-warm-900">{summary.total.toLocaleString()} <span className="text-base font-medium">{summary.total === 1 ? "cycle" : "cycles"}</span></p>
              {summary.status === "frozen" && <p className="mt-1 text-xs text-warm-500">Completed snapshot</p>}
            </div>
          </header>

          <section aria-label="Age and sex">
            <h3 className="mb-3 text-base font-bold text-warm-800">Age and sex</h3>
            <div className="overflow-x-auto rounded-xl border border-warm-200">
              <table className="w-full min-w-[640px] table-auto border-collapse whitespace-nowrap text-sm tabular-nums">
                <thead className="bg-warm-50 text-warm-600"><tr><th scope="col" className="sticky left-0 bg-warm-50 px-3 py-2 text-left">Sex</th>{summary.age_groups.map((group) => <th key={group} scope="col" className="px-3 py-2 text-right">{group}</th>)}<th scope="col" className="px-3 py-2 text-right">Total</th></tr></thead>
                <tbody className="divide-y divide-warm-100">
                  {([['M', 'Male'], ['F', 'Female']] as const).map(([sex, label]) => <tr key={sex}><th scope="row" className="sticky left-0 bg-white px-3 py-2 text-left font-medium text-warm-700">{label}</th>{summary.age_groups.map((group) => <td key={group} className="px-3 py-2 text-right">{summary.age_sex[group]?.[sex] ?? 0}</td>)}<td className="px-3 py-2 text-right font-semibold">{sex === 'M' ? ageTotals?.M ?? 0 : ageTotals?.F ?? 0}</td></tr>)}
                </tbody>
                <tfoot className="border-t border-warm-200 bg-warm-50 font-bold"><tr><th scope="row" className="sticky left-0 bg-warm-50 px-3 py-2 text-left">Total</th>{summary.age_groups.map((group) => <td key={group} className="px-3 py-2 text-right">{summary.age_sex[group]?.total ?? 0}</td>)}<td className="px-3 py-2 text-right">{ageTotals?.total ?? 0}</td></tr></tfoot>
              </table>
            </div>
            {summary.unknown_sex > 0 && <p className="mt-2 text-sm text-warm-600">Unclassified age or sex: {summary.unknown_sex}</p>}
          </section>

          <div className="grid gap-x-10 gap-y-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <section aria-label="By nutritional status">
              <h3 className="mb-2 text-base font-bold text-warm-800">By nutritional status</h3>
              <dl className="text-sm">
                {nutritionalStatusEntries.map(([status, count]) => <div key={status} className="flex items-baseline justify-between gap-4 border-b border-warm-100 py-1.5">
                  <dt className="text-warm-700">{status}</dt><dd className="font-semibold tabular-nums text-warm-900">{count}</dd>
                </div>)}
              </dl>
              {unclassifiedStatus > 0 && <p className="mt-2 text-sm text-warm-600">Not classified: {unclassifiedStatus}</p>}
            </section>
            <div className="space-y-6">
              <section aria-label="By risk level">
                <h3 className="mb-2 text-base font-bold text-warm-800">By risk level</h3>
                <dl className="text-sm">
                  {riskEntries.map(([risk, count]) => <div key={risk} className="flex items-baseline justify-between gap-4 border-b border-warm-100 py-1.5">
                    <dt className="text-warm-700">{risk}</dt><dd className="font-semibold tabular-nums text-warm-900">{count}</dd>
                  </div>)}
                </dl>
                {unscored > 0 && <p className="mt-2 text-xs text-warm-500">Not scored: {unscored}</p>}
              </section>
              <section aria-label="By nutrition care category">
                <h3 className="mb-2 text-base font-bold text-warm-800">By nutrition care category</h3>
                {categoryEntries.length === 0 ? <p className="text-sm text-warm-500">No cycles in selected period.</p> : (
                  <dl className="text-sm">
                    {categoryEntries.map(([category, count]) => <div key={category} className="flex items-baseline justify-between gap-4 border-b border-warm-100 py-1.5">
                      <dt className="text-warm-700">{category}</dt><dd className="font-semibold tabular-nums text-warm-900">{count}</dd>
                    </div>)}
                  </dl>
                )}
              </section>
            </div>
          </div>
        </div>
  ) : null;

  return (
    <>
    <div className="min-w-0">
      <div className="flex flex-wrap items-end gap-3 border-b border-warm-200 pb-4">
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
        {!loading && summary && <a href={censusExportUrl(apiPrefix, year, month ?? undefined)} download className="inline-flex items-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
          <Download className="h-4 w-4" aria-hidden="true" /> Download PDF
        </a>}
      </div>

      {loading && <div className="flex items-center justify-center gap-2 py-16 text-sm text-warm-500"><Loader2 className="h-4 w-4 animate-spin" /> Loading census…</div>}
      {!loading && error && <p role="alert" className="py-12 text-center text-sm text-red-700">{error}</p>}
      {content && <div data-census-screen>{content}</div>}
    </div>
    </>
  );
}
