"use client";

import { ChevronDown, ChevronUp } from "lucide-react";
import type { CalculationTargetStatus, CalculationTrace } from "@/lib/prescriptionCalculationTrace";

interface Props {
  trace: CalculationTrace;
  expanded: boolean;
  onToggle: () => void;
}

const statusClasses: Record<CalculationTargetStatus, string> = {
  matches: "bg-brand-green-50 text-brand-green-700 border-brand-green-200",
  modified: "bg-brand-orange-50 text-brand-orange-700 border-brand-orange-200",
  manual: "bg-sky-50 text-sky-700 border-sky-200",
  missing: "bg-red-50 text-red-700 border-red-200",
  flagged: "bg-warm-100 text-warm-700 border-warm-200",
};

const statusText: Record<CalculationTargetStatus, string> = {
  matches: "Matches",
  modified: "Modified",
  manual: "Manual",
  missing: "Missing",
  flagged: "Flagged",
};

export default function PrescriptionCalculationPanel({ trace, expanded, onToggle }: Props) {
  const panelId = "prescription-calculation-panel";
  const Icon = expanded ? ChevronUp : ChevronDown;

  return (
    <div className="rounded-xl border border-warm-200 bg-warm-25">
      <div className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="text-xs font-extrabold uppercase tracking-wider text-warm-700">Calculations</p>
          <p className="text-xs leading-relaxed text-warm-500">
            Review how current targets were derived from the selected goal.
          </p>
        </div>
        <button
          type="button"
          aria-expanded={expanded}
          aria-controls={panelId}
          onClick={onToggle}
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm font-bold text-warm-700 transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30"
        >
          {expanded ? "Hide calculations" : "Show calculations"}
          <Icon className="h-4 w-4" />
        </button>
      </div>

      {expanded && (
        <div id={panelId} className="space-y-3 border-t border-warm-200 p-3">
          <TraceSection title="Inputs Used" rows={trace.inputs} />
          <TraceSection title="Weight Basis" rows={trace.weights} />

          <div className="space-y-2">
            <p className="text-xs font-extrabold uppercase tracking-wider text-warm-500">Prescribed Targets</p>
            <div className="space-y-2">
              {trace.targets.map((target) => (
                <div key={target.key} className="rounded-xl border border-warm-200 bg-white p-3">
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <p className="text-sm font-extrabold text-warm-900">{target.label}</p>
                      <p className="text-xs text-warm-500">{target.formula}</p>
                    </div>
                    <span className={`w-fit rounded-full border px-2 py-0.5 text-xs font-bold ${statusClasses[target.status]}`}>
                      {statusText[target.status]}
                    </span>
                  </div>
                  <div className="mt-3 grid gap-2 sm:grid-cols-3">
                    <ValueBlock label="Prescribed" text={target.prescribed?.text ?? "-"} />
                    <ValueBlock label="Calculated" text={target.calculated?.text ?? "-"} />
                    <ValueBlock label="Calculation" text={target.calculation} />
                  </div>
                </div>
              ))}
            </div>
          </div>

          {trace.notes.length > 0 && (
            <div className="rounded-xl border border-brand-orange-200 bg-brand-orange-50 p-3">
              <p className="text-xs font-extrabold uppercase tracking-wider text-brand-orange-700">Notes</p>
              <div className="mt-1 space-y-1">
                {trace.notes.map((note) => (
                  <p key={note} className="text-xs leading-relaxed text-brand-orange-700">{note}</p>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function TraceSection({ title, rows }: { title: string; rows: CalculationTrace["inputs"] }) {
  return (
    <div className="space-y-2">
      <p className="text-xs font-extrabold uppercase tracking-wider text-warm-500">{title}</p>
      <div className="grid gap-2 sm:grid-cols-2">
        {rows.map((row) => (
          <div key={`${title}-${row.label}`} className="rounded-xl border border-warm-200 bg-white p-3">
            <div className="flex items-start justify-between gap-2">
              <p className="text-xs font-bold uppercase tracking-wider text-warm-500">{row.label}</p>
              <p className="font-numeric text-sm font-extrabold text-warm-900">{row.value}</p>
            </div>
            <p className="mt-1 text-xs text-warm-500">{row.formula}</p>
            <p className="mt-1 font-numeric text-xs text-warm-700">{row.calculation}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function ValueBlock({ label, text }: { label: string; text: string }) {
  return (
    <div className="rounded-lg bg-warm-50 px-3 py-2">
      <p className="text-xs font-bold uppercase tracking-wider text-warm-400">{label}</p>
      <p className="font-numeric text-sm font-semibold leading-relaxed text-warm-800">{text}</p>
    </div>
  );
}
