"use client";

import type { ReactNode } from "react";
import type { Intervention } from "@/services/interventionService";

interface Props {
  mode: "edit" | "readonly";
  plan: Intervention | null;
  saving: boolean;
  error: string | null;
  children: ReactNode;
  onSave: () => void;
  onCancel: () => void;
}

export default function InterventionPlanEditor({
  mode,
  plan,
  saving,
  error,
  children,
  onSave,
  onCancel,
}: Props) {
  const displayDate = (value: string) => new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    timeZone: "Asia/Manila",
  }).format(new Date(value));
  const source = plan?.source_monitoring_date
    ? `Monitoring — ${displayDate(plan.source_monitoring_date)}`
    : "Original Assessment";

  return (
    <section aria-label={mode === "edit" ? "New intervention plan" : "Saved intervention plan"} className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-warm-200 pb-3">
        <div>
          <h3 className="text-base font-bold text-warm-900">
            {mode === "edit" ? "New Intervention Plan" : "Intervention Plan"}
          </h3>
          {mode === "readonly" && plan?.created_at && (
            <p className="mt-1 text-sm font-semibold text-warm-700">{displayDate(plan.created_at)}</p>
          )}
          <p className="mt-1 text-xs text-warm-500">Calculation source: {source}</p>
        </div>
        {mode === "edit" && (
          <div className="flex gap-2">
            <button type="button" onClick={onCancel} className="px-3 py-2 text-sm font-semibold text-warm-700 border border-warm-300 rounded-lg">
              Cancel
            </button>
            <button type="button" onClick={onSave} disabled={saving} className="px-3 py-2 text-sm font-bold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg disabled:opacity-50">
              {saving ? "Saving…" : "Save Intervention Plan"}
            </button>
          </div>
        )}
      </div>
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      {children}
    </section>
  );
}
