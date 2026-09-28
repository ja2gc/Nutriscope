"use client";

import React from "react";
import type { MonitoringPlan } from "@/services/monitoringPlan";
import { InfoHint } from "@/components/ui/InfoHint";

/**
 * The "specialized patient" context at the top of monitoring: the PES problems,
 * intervention goal, prescription targets, and the abnormalities that put each
 * tracked indicator into this patient's plan.
 */
export default function CarePlanHeader({ plan }: { plan: MonitoringPlan | null }) {
  if (!plan) return null;

  const flagged = plan.indicators.filter((i) => i.sources.includes("flagged_abnormal"));
  const intake = plan.indicators.filter((i) => i.category === "intake" && i.target !== null);

  return (
    <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-4">
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <div className="flex items-center gap-1">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">
            Monitoring Targets
          </h3>
          <InfoHint label="How monitoring targets are selected" title="Monitoring target sources">
            Targets come from supported assessment findings, nutrition diagnoses, goals, prescriptions, and calculated values. Only available patient data is included.
          </InfoHint>
        </div>
        {plan.goal_type && (
          <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
            {plan.goal_type.replace(/_/g, " ")}
          </span>
        )}
      </div>

      {/* PES diagnoses */}
      {plan.pes_statements.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-xs font-bold text-warm-400 uppercase tracking-widest">
            Nutrition Diagnoses (PES)
          </p>
          <ul className="space-y-1">
            {plan.pes_statements.map((pes, i) => (
              <li key={i} className="text-xs text-warm-600 leading-relaxed">
                {pes}
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Flagged abnormalities */}
      {flagged.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-xs font-bold text-warm-400 uppercase tracking-widest">
            Baseline Assessment Findings
          </p>
          <div className="flex flex-wrap gap-1.5">
            {flagged.map((i) => {
              const baseline = i.series[0]?.value;
              return (
                <span key={i.key} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                  {i.label}
                  {baseline != null && <span className="font-mono">{baseline}{i.unit}</span>}
                </span>
              );
            })}
          </div>
        </div>
      )}

      {/* Prescription targets */}
      {intake.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-xs font-bold text-warm-400 uppercase tracking-widest">Prescription Targets</p>
          <div className="flex flex-wrap gap-1.5">
            {intake.map((i) => (
              <span key={i.key} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold bg-warm-50 text-warm-600 border border-warm-200">
                {i.label.replace(/ intake$/i, "")} <span className="font-mono text-warm-900">{i.target}{i.unit}</span>
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
