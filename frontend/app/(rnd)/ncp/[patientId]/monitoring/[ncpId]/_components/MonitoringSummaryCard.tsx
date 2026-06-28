"use client";

import React, { useEffect, useState } from "react";
import {
  TrendingUp, TrendingDown, Minus, Sparkles, Loader2, AlertTriangle, Target,
} from "lucide-react";
import StatusBadge from "@/components/ui/StatusBadge";
import {
  fetchMonitoringSummary, requestMonitoringAiReview,
  MonitoringSummary, GoalStatus,
} from "@/services/monitoringService";

interface Props {
  ncpId: string;
  /** Bump to re-fetch after a new visit is logged. */
  visitCount: number;
}

const STATUS_MAP: Record<string, { status: "success" | "warning" | "error" | "neutral"; label: string }> = {
  met:         { status: "success", label: "Goal Met" },
  partial:     { status: "warning", label: "Partial" },
  in_progress: { status: "warning", label: "Improving" },
  not_met:     { status: "error", label: "Not Met" },
  no_data:     { status: "neutral", label: "No Data" },
};

function statusDot(status: GoalStatus | "partial") {
  const map: Record<string, string> = {
    met: "bg-emerald-500",
    in_progress: "bg-amber-500",
    partial: "bg-amber-500",
    not_met: "bg-red-500",
    no_data: "bg-warm-300",
  };
  return map[status] ?? "bg-warm-300";
}

export default function MonitoringSummaryCard({ ncpId, visitCount }: Props) {
  const [summary, setSummary]   = useState<MonitoringSummary | null>(null);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState<string | null>(null);

  const [aiNarrative, setAiNarrative] = useState<string | null>(null);
  const [aiLoading, setAiLoading]     = useState(false);
  const [aiError, setAiError]         = useState<string | null>(null);
  const [aiCached, setAiCached]       = useState(false);

  // Fetch on mount / ncp change / new visit. All setState calls happen after an
  // await (never synchronously in the effect body) so this stays render-safe.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const data = await fetchMonitoringSummary(ncpId);
        if (cancelled) return;
        setSummary(data);
        setError(null);
        // A reload invalidates any prior AI narrative.
        setAiNarrative(null);
        setAiError(null);
      } catch (err) {
        if (!cancelled) setError(err instanceof Error ? err.message : "Failed to load summary.");
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [ncpId, visitCount]);

  const runAiReview = async () => {
    setAiLoading(true);
    setAiError(null);
    try {
      const res = await requestMonitoringAiReview(ncpId);
      setAiNarrative(res.narrative);
      setAiCached(res.cached);
    } catch (err) {
      setAiError(err instanceof Error ? err.message : "AI review failed.");
    } finally {
      setAiLoading(false);
    }
  };

  if (loading) {
    return <div className="h-32 bg-warm-100 rounded-2xl animate-pulse" />;
  }
  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-700 text-xs rounded-2xl px-4 py-3">{error}</div>
    );
  }
  if (!summary?.has_data) {
    return (
      <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
        <h3 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider mb-2">Visit Summary</h3>
        <p className="text-xs text-warm-400">Log a visit to see the evaluation summary. A second visit unlocks visit-to-visit trends.</p>
      </div>
    );
  }

  const evalStatus = STATUS_MAP[summary.goal_evaluation.status] ?? STATUS_MAP.no_data;

  return (
    <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-4">
      {/* Header + overall evaluation */}
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <h3 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2">
          <Target className="h-4 w-4 text-emerald-600" /> Evaluation Summary
        </h3>
        <StatusBadge label={evalStatus.label} status={evalStatus.status} />
      </div>

      {summary.has_previous ? (
        <p className="text-[10px] text-warm-400">
          Comparing {summary.previous_date} → {summary.current_date}
        </p>
      ) : (
        <p className="text-[10px] text-warm-400">First visit ({summary.current_date}) — log another to compare trends.</p>
      )}

      {/* Reasons */}
      {summary.goal_evaluation.reasons.length > 0 && (
        <ul className="space-y-1">
          {summary.goal_evaluation.reasons.map((r, i) => (
            <li key={i} className="flex items-start gap-1.5 text-[10px] text-warm-500">
              <span className={`mt-1 h-1.5 w-1.5 rounded-full shrink-0 ${statusDot(summary.goal_evaluation.status)}`} />
              {r}
            </li>
          ))}
        </ul>
      )}

      {/* Metric changes */}
      {summary.changes.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-[9px] font-bold text-warm-400 uppercase tracking-widest">What changed</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
            {summary.changes.map((c) => {
              const Icon = c.direction === "up" ? TrendingUp : c.direction === "down" ? TrendingDown : Minus;
              const cStatus = STATUS_MAP[c.status] ?? STATUS_MAP.no_data;
              return (
                <div key={c.metric} className="flex items-center justify-between px-2.5 py-1.5 rounded-lg border border-warm-100 bg-warm-50/60">
                  <div className="min-w-0">
                    <p className="text-[10px] font-semibold text-warm-700 truncate">{c.label}</p>
                    <p className="text-[9px] text-warm-400 font-mono">
                      {c.previous != null ? `${c.previous} → ` : ""}{c.current}{c.unit}
                    </p>
                  </div>
                  <div className="flex items-center gap-1.5 shrink-0">
                    {c.delta != null && (
                      <span className="flex items-center gap-0.5 text-[10px] font-mono text-warm-500">
                        <Icon className="h-3 w-3" />
                        {c.delta > 0 ? "+" : ""}{c.delta}{c.delta_pct != null ? ` (${c.delta_pct > 0 ? "+" : ""}${c.delta_pct}%)` : ""}
                      </span>
                    )}
                    <span className={`h-2 w-2 rounded-full ${statusDot(c.status)}`} title={cStatus.label} />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Intake adequacy vs Rx */}
      {summary.intake.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-[9px] font-bold text-warm-400 uppercase tracking-widest">Intake vs prescribed</p>
          <div className="space-y-1.5">
            {summary.intake.map((i) => {
              const barCls = i.flag === "under" ? "bg-red-400" : i.flag === "over" ? "bg-amber-400" : "bg-emerald-500";
              return (
                <div key={i.metric}>
                  <div className="flex items-center justify-between text-[10px]">
                    <span className="font-semibold text-warm-600">{i.label}</span>
                    <span className="font-mono text-warm-500">{i.actual}/{i.target}{i.unit} · {i.pct}%</span>
                  </div>
                  <div className="mt-0.5 h-1.5 w-full rounded-full bg-warm-100 overflow-hidden">
                    <div className={`h-full rounded-full ${barCls}`} style={{ width: `${Math.min(i.pct, 100)}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Optional AI narrative */}
      <div className="pt-3 border-t border-warm-100 space-y-2">
        {!aiNarrative && (
          <button onClick={runAiReview} disabled={aiLoading}
            className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-50 transition-colors cursor-pointer disabled:opacity-50">
            {aiLoading ? <Loader2 className="h-3 w-3 animate-spin" /> : <Sparkles className="h-3 w-3" />}
            {aiLoading ? "Reviewing…" : "AI Clinical Review"}
          </button>
        )}
        {!aiNarrative && !aiLoading && (
          <p className="text-[9px] text-warm-400">Optional — sends only the small delta above to Claude Haiku for a brief interpretation. Cached per visit-pair.</p>
        )}
        {aiError && (
          <p className="flex items-start gap-1.5 text-[10px] text-amber-700">
            <AlertTriangle className="h-3 w-3 mt-0.5 shrink-0" /> {aiError}
          </p>
        )}
        {aiNarrative && (
          <div className="p-3 bg-emerald-50 border border-emerald-100 rounded-xl">
            <p className="text-[9px] font-bold text-emerald-600 uppercase tracking-widest mb-1 flex items-center gap-1">
              <Sparkles className="h-3 w-3" /> AI Clinical Review {aiCached && <span className="text-warm-400 font-normal normal-case">· cached</span>}
            </p>
            <p className="text-[11px] text-warm-700 leading-relaxed">{aiNarrative}</p>
            <button onClick={runAiReview} disabled={aiLoading}
              className="mt-2 text-[9px] font-bold text-emerald-600 hover:text-emerald-700 transition-colors cursor-pointer">
              Regenerate
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
