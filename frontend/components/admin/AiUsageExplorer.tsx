"use client";

import { useEffect, useMemo, useState } from "react";
import { BarChart3, ChevronLeft, ChevronRight } from "lucide-react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { calcTokenCostUsd } from "@/lib/aiTokenCost";
import {
  fetchAiUsageAnalytics,
  type AiUsageAnalytics,
  type AiUsagePoint,
} from "@/services/aiUsageAnalyticsService";

const MONTHS = Array.from({ length: 12 }, (_, index) =>
  new Intl.DateTimeFormat("en-US", { month: "long" }).format(new Date(2000, index, 1)),
);

interface AiUsageExplorerProps {
  inputCostPer1mTokensUsd: number;
  outputCostPer1mTokensUsd: number;
  phpRate: number;
}

interface AiUsageTooltipProps extends AiUsageExplorerProps {
  active?: boolean;
  label?: string | number;
  month: number;
  point?: AiUsagePoint;
}

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

function formatAxisTokens(tokens: number): string {
  if (tokens >= 1_000_000) return `${(tokens / 1_000_000).toFixed(tokens % 1_000_000 === 0 ? 0 : 1)}M`;
  if (tokens >= 1_000) return `${(tokens / 1_000).toFixed(tokens % 1_000 === 0 ? 0 : 1)}k`;
  return tokens.toLocaleString();
}

function formatPhpCost(usd: number, phpRate: number): string {
  const php = usd * phpRate;
  const maximumFractionDigits = php > 0 && php < 0.01 ? 4 : 2;

  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    minimumFractionDigits: 2,
    maximumFractionDigits,
  }).format(php);
}

export function AiUsageTooltip({
  active,
  month,
  point,
  inputCostPer1mTokensUsd,
  outputCostPer1mTokensUsd,
  phpRate,
}: AiUsageTooltipProps) {
  if (!active || !point || point.tokens === null) return null;

  const inputTokens = point.tokens_input ?? 0;
  const outputTokens = point.tokens_output ?? 0;
  const estimatedCost = calcTokenCostUsd(
    inputTokens,
    outputTokens,
    inputCostPer1mTokensUsd,
    outputCostPer1mTokensUsd,
  );
  const period = point.day ? `${MONTHS[month - 1]} ${point.day}` : MONTHS[(point.month ?? 1) - 1];

  return (
    <div className="min-w-48 rounded-xl border border-warm-200 bg-white px-3 py-2.5 text-xs shadow-lg">
      <p className="mb-2 font-bold text-warm-900">{period}</p>
      <dl className="space-y-1.5 tabular-nums">
        <div className="flex items-center justify-between gap-5">
          <dt className="text-warm-500">Total tokens</dt>
          <dd className="font-semibold text-warm-900">{point.tokens.toLocaleString()}</dd>
        </div>
        <div className="flex items-center justify-between gap-5">
          <dt className="text-warm-500">Input / output</dt>
          <dd className="font-semibold text-warm-900">
            {inputTokens.toLocaleString()} / {outputTokens.toLocaleString()}
          </dd>
        </div>
        <div className="flex items-center justify-between gap-5 border-t border-warm-100 pt-1.5">
          <dt className="text-warm-500">Estimated cost</dt>
          <dd className="font-semibold text-warm-900">{formatPhpCost(estimatedCost, phpRate)}</dd>
        </div>
      </dl>
    </div>
  );
}

function RechartsUsageTooltip({
  month,
  inputCostPer1mTokensUsd,
  outputCostPer1mTokensUsd,
  phpRate,
  ...props
}: AiUsageExplorerProps & {
  month: number;
  active?: boolean;
  label?: string | number;
  payload?: ReadonlyArray<{ payload?: unknown }>;
}) {
  return (
    <AiUsageTooltip
      active={props.active}
      label={props.label}
      month={month}
      point={props.payload?.[0]?.payload as AiUsagePoint | undefined}
      inputCostPer1mTokensUsd={inputCostPer1mTokensUsd}
      outputCostPer1mTokensUsd={outputCostPer1mTokensUsd}
      phpRate={phpRate}
    />
  );
}

export function AiUsageExplorer({
  inputCostPer1mTokensUsd,
  outputCostPer1mTokensUsd,
  phpRate,
}: AiUsageExplorerProps) {
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

  const periodLabel = view === "month" ? `${MONTHS[month - 1]} ${year}` : String(year);
  const atEarliestPeriod = year === 2000 && (view === "year" || month === 1);
  const atLatestPeriod = year === today.year + 1 && (view === "year" || month === 12);
  const chartData = data?.points.map((point) => ({
    ...point,
    label: point.day ?? MONTHS[(point.month ?? 1) - 1].slice(0, 3),
  })) ?? [];

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
            <p className="mt-1 text-xs text-warm-500">All users · Asia/Manila</p>
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
        <div className="mb-4 flex items-center justify-between gap-3">
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
            <p data-period-total className="mt-0.5 text-xs tabular-nums text-warm-500">
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
          <div data-testid="ai-usage-chart" className="overflow-x-auto pb-1">
            <ul className="sr-only">
              {data.points.map((point) => (
                <li data-day-label={point.day ?? undefined} key={point.day ?? point.month}>
                  {point.day ? `Day ${point.day}` : MONTHS[(point.month ?? 1) - 1]}: {point.tokens ?? 0} tokens
                </li>
              ))}
            </ul>
            <div className={view === "month" ? "h-72 min-w-[760px]" : "h-72 min-w-[560px]"}>
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} margin={{ top: 12, right: 8, left: 4, bottom: 0 }} accessibilityLayer>
                  <CartesianGrid vertical={false} stroke="#e7e5df" />
                  <XAxis
                    dataKey="label"
                    interval={0}
                    tickLine={false}
                    axisLine={{ stroke: "#d8d5cd" }}
                    tick={{ fill: "#8a8b83", fontSize: 11 }}
                    height={30}
                  />
                  <YAxis
                    domain={[0, "auto"]}
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    tick={{ fill: "#8a8b83", fontSize: 11 }}
                    tickFormatter={formatAxisTokens}
                    width={48}
                  />
                  <Tooltip
                    cursor={{ fill: "#f5f5f0" }}
                    content={(tooltipProps) => (
                      <RechartsUsageTooltip
                        {...tooltipProps}
                        month={month}
                        inputCostPer1mTokensUsd={inputCostPer1mTokensUsd}
                        outputCostPer1mTokensUsd={outputCostPer1mTokensUsd}
                        phpRate={phpRate}
                      />
                    )}
                  />
                  <Bar dataKey="tokens" name="Tokens" fill="#059669" maxBarSize={view === "month" ? 16 : 32} radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
