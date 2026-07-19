"use client";

import { useEffect, useMemo, useState } from "react";
import { BarChart3, ChevronLeft, ChevronRight } from "lucide-react";
import {
  fetchAiUsageAnalytics,
  type AiUsageAnalytics,
} from "@/services/aiUsageAnalyticsService";

const MONTHS = Array.from({ length: 12 }, (_, index) =>
  new Intl.DateTimeFormat("en-US", { month: "long" }).format(new Date(2000, index, 1)),
);

function manilaToday(): { year: number; month: number } {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: "Asia/Manila",
    year: "numeric",
    month: "numeric",
  }).formatToParts(new Date());

  return {
    year: Number(parts.find((part) => part.type === "year")?.value),
    month: Number(parts.find((part) => part.type === "month")?.value),
  };
}

function formatTokens(tokens: number): string {
  return `${tokens.toLocaleString()} tokens`;
}

export function AiUsageExplorer() {
  const today = useMemo(() => manilaToday(), []);
  const [view, setView] = useState<"month" | "year">("month");
  const [year, setYear] = useState(today.year);
  const [month, setMonth] = useState(today.month);
  const [data, setData] = useState<AiUsageAnalytics | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const years = useMemo(
    () => Array.from({ length: today.year - 1999 }, (_, index) => today.year + 1 - index),
    [today.year],
  );

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError(null);

    void fetchAiUsageAnalytics({ view, year, month })
      .then((response) => {
        if (active) setData(response);
      })
      .catch(() => {
        if (active) {
          setData(null);
          setError("AI token usage could not be loaded.");
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [month, view, year]);

  function shiftPeriod(direction: -1 | 1) {
    if (view === "year") {
      setYear((current) => current + direction);
      return;
    }

    const next = new Date(year, month - 1 + direction, 1);
    setYear(next.getFullYear());
    setMonth(next.getMonth() + 1);
  }

  const maxTokens = Math.max(1, ...(data?.points.map((point) => point.tokens ?? 0) ?? []));
  const periodLabel = view === "month" ? `${MONTHS[month - 1]} ${year}` : String(year);
  const atEarliestPeriod = year === 2000 && (view === "year" || month === 1);
  const atLatestPeriod = year === today.year + 1 && (view === "year" || month === 12);

  return (
    <section className="overflow-hidden rounded-3xl border border-warm-200 bg-white shadow-sm">
      <div className="flex flex-col gap-4 border-b border-warm-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex items-start gap-3">
          <span className="rounded-xl border border-emerald-100 bg-emerald-50 p-2 text-emerald-700">
            <BarChart3 aria-hidden="true" className="h-4 w-4" />
          </span>
          <div>
            <h3 className="text-sm font-bold uppercase tracking-[0.18em] text-warm-900">
              AI Token Consumption
            </h3>
            <p className="mt-1 text-xs text-warm-500">All users, Asia/Manila boundaries</p>
          </div>
        </div>

        <div className="flex flex-wrap items-end gap-2">
          <label className="grid gap-1 text-xs font-semibold text-warm-600">
            View
            <select
              aria-label="Usage period"
              value={view}
              onChange={(event) => setView(event.target.value as "month" | "year")}
              className="h-11 rounded-xl border border-warm-200 bg-white px-3 text-sm text-warm-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            >
              <option value="month">Month</option>
              <option value="year">Year</option>
            </select>
          </label>

          {view === "month" && (
            <label className="grid gap-1 text-xs font-semibold text-warm-600">
              Month
              <select
                aria-label="Jump to month"
                value={month}
                onChange={(event) => setMonth(Number(event.target.value))}
                className="h-11 rounded-xl border border-warm-200 bg-white px-3 text-sm text-warm-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
              >
                {MONTHS.map((label, index) => (
                  <option key={label} value={index + 1}>{label}</option>
                ))}
              </select>
            </label>
          )}

          <label className="grid gap-1 text-xs font-semibold text-warm-600">
            Year
            <select
              aria-label="Jump to year"
              value={year}
              onChange={(event) => setYear(Number(event.target.value))}
              className="h-11 rounded-xl border border-warm-200 bg-white px-3 text-sm text-warm-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            >
              {years.map((option) => <option key={option}>{option}</option>)}
            </select>
          </label>
        </div>
      </div>

      <div className="p-5 sm:p-6">
        <div className="mb-5 flex items-center justify-between gap-3">
          <button
            type="button"
            aria-label={`Previous ${view}`}
            onClick={() => shiftPeriod(-1)}
            disabled={atEarliestPeriod}
            className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-warm-200 text-warm-700 transition hover:bg-warm-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronLeft aria-hidden="true" className="h-5 w-5" />
          </button>
          <div className="text-center" aria-live="polite">
            <p className="text-sm font-bold text-warm-900">{periodLabel}</p>
            <p className="mt-0.5 text-xs tabular-nums text-warm-500">
              {data ? formatTokens(data.total_tokens) : "--"}
            </p>
          </div>
          <button
            type="button"
            aria-label={`Next ${view}`}
            onClick={() => shiftPeriod(1)}
            disabled={atLatestPeriod}
            className="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-warm-200 text-warm-700 transition hover:bg-warm-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <ChevronRight aria-hidden="true" className="h-5 w-5" />
          </button>
        </div>

        {loading && <div role="status" className="h-72 animate-pulse rounded-2xl bg-warm-50" />}
        {!loading && error && (
          <div role="alert" className="flex h-72 items-center justify-center rounded-2xl border border-red-100 bg-red-50 px-4 text-sm font-semibold text-red-700">
            {error}
          </div>
        )}
        {!loading && data && (
          <>
            <div
              role="img"
              aria-label={`${periodLabel}: ${formatTokens(data.total_tokens)}`}
              className="flex h-72 items-end gap-1 overflow-x-auto border-b border-warm-200 pb-px"
            >
              {data.points.map((point, index) => {
                const state = point.tokens === null ? "future" : point.tokens === 0 ? "zero" : "used";
                const height = point.tokens === null ? 0 : point.tokens === 0 ? 2 : Math.max(4, (point.tokens / maxTokens) * 100);
                const label = point.day ?? MONTHS[(point.month ?? 1) - 1].slice(0, 3);

                return (
                  <div key={point.day ?? point.month ?? index} className="flex min-w-[14px] flex-1 flex-col items-center justify-end gap-2 self-stretch">
                    <div className="flex w-full flex-1 items-end justify-center">
                      <div
                        data-usage-state={state}
                        title={state === "future" ? `${label}: not occurred yet` : `${label}: ${formatTokens(point.tokens ?? 0)}`}
                        className={state === "used" ? "w-full max-w-8 rounded-t bg-emerald-600" : state === "zero" ? "w-full max-w-8 bg-warm-300" : "w-full max-w-8"}
                        style={{ height: `${height}%` }}
                      />
                    </div>
                    <span className="text-xs tabular-nums text-warm-400">
                      {view === "year" || index % 3 === 0 || index === data.points.length - 1 ? label : ""}
                    </span>
                  </div>
                );
              })}
            </div>
            <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-warm-500">
              <span className="inline-flex items-center gap-2"><i className="h-3 w-3 rounded-sm bg-emerald-600" />Usage</span>
              <span className="inline-flex items-center gap-2"><i className="h-0.5 w-3 bg-warm-300" />Zero usage</span>
              {view === "month" && <span className="inline-flex items-center gap-2"><i className="h-3 w-3 rounded-sm border border-dashed border-warm-300" />Not occurred yet</span>}
            </div>
          </>
        )}
      </div>
    </section>
  );
}
