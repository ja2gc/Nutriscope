"use client";

import { ChevronDown, ChevronUp } from "lucide-react";
import type { CalculationTargetStatus, CalculationTrace } from "@/lib/prescriptionCalculationTrace";

interface Props {
  trace: CalculationTrace;
  expanded: boolean;
  onToggle: () => void;
}

const statusClasses: Record<CalculationTargetStatus, string> = {
  matches: "border-brand-green-200 bg-brand-green-50 text-brand-green-700",
  modified: "border-brand-orange-200 bg-brand-orange-50 text-brand-orange-700",
  manual: "border-sky-200 bg-sky-50 text-sky-700",
  missing: "border-red-200 bg-red-50 text-red-700",
};

const statusText: Record<CalculationTargetStatus, string> = {
  matches: "Recommended",
  modified: "Modified",
  manual: "Manual",
  missing: "Missing",
};

export default function PrescriptionCalculationPanel({ trace, expanded, onToggle }: Props) {
  const panelId = "prescription-calculation-panel";
  const Icon = expanded ? ChevronUp : ChevronDown;

  return (
    <div className="rounded-lg border border-warm-200 bg-warm-25">
      <div className="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p className="text-sm font-extrabold text-warm-800">Calculation details</p>
        </div>
        <button
          type="button"
          aria-expanded={expanded}
          aria-controls={panelId}
          onClick={onToggle}
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm font-bold text-warm-700 transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30"
        >
          {expanded ? "Hide calculations" : "Show calculations"}
          <Icon className="h-4 w-4" aria-hidden="true" />
        </button>
      </div>

      {expanded && (
        <div id={panelId} className="space-y-4 border-t border-warm-200 p-3">
          <section aria-labelledby={`${panelId}-context`}>
            <h4 id={`${panelId}-context`} className="text-xs font-extrabold uppercase tracking-wider text-warm-500">
              Patient &amp; Assessment Context
            </h4>
            <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-3 rounded-lg border border-warm-200 bg-white p-3 sm:grid-cols-3 lg:grid-cols-5">
              {trace.context.map((item) => (
                <div key={item.label} className="min-w-0">
                  <dt className="text-xs font-semibold text-warm-500">{item.label}</dt>
                  <dd className="font-numeric text-sm font-extrabold text-warm-900">
                    {item.value}
                    {item.formulaName && <span className="ml-1 font-sans text-xs font-medium text-warm-500">({item.formulaName})</span>}
                  </dd>
                </div>
              ))}
            </dl>
          </section>

          <section aria-labelledby={`${panelId}-prescription`}>
            <h4 id={`${panelId}-prescription`} className="text-xs font-extrabold uppercase tracking-wider text-warm-500">
              Nutrition Prescription
            </h4>
            <div className="mt-2 overflow-hidden rounded-lg border border-warm-200 bg-white">
              <div className="hidden grid-cols-[minmax(7rem,0.7fr)_minmax(12rem,1.3fr)_minmax(14rem,1.5fr)_minmax(7rem,0.6fr)] gap-3 border-b border-warm-200 bg-warm-50 px-3 py-2 text-xs font-bold text-warm-500 md:grid">
                <span>Nutrient</span>
                <span>Formula</span>
                <span>Values substituted</span>
                <span>Current value</span>
              </div>
              {trace.targets.map((target) => (
                <div
                  key={target.key}
                  className="grid gap-2 border-b border-warm-100 px-3 py-3 last:border-b-0 md:grid-cols-[minmax(7rem,0.7fr)_minmax(12rem,1.3fr)_minmax(14rem,1.5fr)_minmax(7rem,0.6fr)] md:gap-3"
                >
                  <div className="flex items-start justify-between gap-2 md:block">
                    <p className="text-sm font-extrabold text-warm-900">{target.label}</p>
                    <span className={`w-fit rounded-full border px-2 py-0.5 text-xs font-bold md:mt-1 ${statusClasses[target.status]}`}>
                      {statusText[target.status]}
                    </span>
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-warm-400 md:hidden">Formula</p>
                    {target.formulaName && <p className="text-xs font-bold text-warm-600">{target.formulaName}</p>}
                    <p className="text-sm leading-relaxed text-warm-700">{target.formula}</p>
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-warm-400 md:hidden">Values substituted</p>
                    <p className="font-numeric text-sm leading-relaxed text-warm-700">{target.calculation}</p>
                  </div>
                  <div>
                    <p className="text-xs font-semibold text-warm-400 md:hidden">Current value</p>
                    <p className="font-numeric text-sm font-extrabold text-warm-900">{target.value?.text ?? "Not set"}</p>
                  </div>
                </div>
              ))}
            </div>
          </section>

          {trace.notes.length > 0 && (
            <div className="border-l-4 border-brand-orange-300 bg-brand-orange-50 px-3 py-2">
              <p className="text-xs font-extrabold uppercase tracking-wider text-brand-orange-700">Clinical notes</p>
              {trace.notes.map((note) => (
                <p key={note} className="mt-1 text-sm leading-relaxed text-brand-orange-800">{note}</p>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
