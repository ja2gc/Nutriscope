"use client";

import { useState, type ReactNode } from "react";
import type { Intervention } from "@/services/interventionService";
import { Button } from "@/components/ui/Button";

interface Props {
  mode: "edit" | "readonly";
  plan: Intervention | null;
  saving: boolean;
  error: string | null;
  children: ReactNode;
  onSave: () => void;
  onCancel: () => void | Promise<void>;
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
  const [canceling, setCanceling] = useState(false);
  const displayDate = (value: string) => new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    timeZone: "Asia/Manila",
  }).format(new Date(value));
  const measurementDate = plan?.calculation_snapshot?.measurement_date ?? plan?.source_monitoring_date;

  return (
    <section aria-label={mode === "edit" && !plan?.id ? "New intervention plan" : "Saved intervention plan"} className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-warm-200 pb-3">
        <div>
          <h3 className="text-base font-bold text-warm-900">
            {mode === "edit" && !plan?.id ? "New Intervention Plan" : "Intervention Plan"}
          </h3>
          {plan?.id && plan.created_at && (
            <p className="mt-1 text-sm font-semibold text-warm-700">{displayDate(plan.created_at)}</p>
          )}
          {measurementDate && <p className="mt-1 text-xs text-warm-500">Measurements: {displayDate(measurementDate)}</p>}
        </div>
        {mode === 'readonly' && <span className="rounded-full border border-warm-200 px-3 py-1 text-xs font-bold text-warm-500">View only</span>}
        {mode === "edit" && (
          <div className="flex gap-2">
            <Button type="button" variant="secondary" onClick={async () => {
              setCanceling(true);
              try { await onCancel(); } finally { setCanceling(false); }
            }} disabled={saving} loading={canceling}>
              Cancel
            </Button>
            <Button type="button" onClick={onSave} loading={saving} disabled={canceling}>
              Save Intervention Plan
            </Button>
          </div>
        )}
      </div>
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      {children}
    </section>
  );
}
