"use client";

import React from "react";
import { Stethoscope, Target, FlaskConical, ClipboardList } from "lucide-react";
import type { MonitoringPlan } from "@/services/monitoringPlan";

const SOURCE_LABELS: Record<string, string> = {
  flagged_abnormal: "Flagged abnormal",
  goal: "Goal",
  pes: "Diagnosis",
  prescription: "Prescription",
  calculated: "Calculated",
};

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
        <h3 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2">
          <ClipboardList className="h-4 w-4 text-emerald-600" /> Care Plan — What We&apos;re Monitoring
        </h3>
        {plan.goal_type && (
          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
            <Target className="h-3 w-3" /> {plan.goal_type.replace(/_/g, " ")}
          </span>
        )}
      </div>

      {/* PES diagnoses */}
      {plan.pes_statements.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-[9px] font-bold text-warm-400 uppercase tracking-widest flex items-center gap-1.5">
            <Stethoscope className="h-3 w-3" /> Nutrition Diagnoses (PES)
          </p>
          <ul className="space-y-1">
            {plan.pes_statements.map((pes, i) => (
              <li key={i} className="text-[11px] text-warm-600 leading-relaxed flex gap-1.5">
                <span className="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />
                {pes}
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Flagged abnormalities */}
      {flagged.length > 0 && (
        <div className="space-y-1.5">
          <p className="text-[9px] font-bold text-warm-400 uppercase tracking-widest flex items-center gap-1.5">
            <FlaskConical className="h-3 w-3" /> Tracked Because Abnormal at Assessment
          </p>
          <div className="flex flex-wrap gap-1.5">
            {flagged.map((i) => {
              const baseline = i.series[0]?.value;
              return (
                <span key={i.key} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200">
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
          <p className="text-[9px] font-bold text-warm-400 uppercase tracking-widest">Prescription Targets</p>
          <div className="flex flex-wrap gap-1.5">
            {intake.map((i) => (
              <span key={i.key} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-warm-50 text-warm-600 border border-warm-200">
                {i.label.replace(/ intake$/i, "")} <span className="font-mono text-warm-900">{i.target}{i.unit}</span>
              </span>
            ))}
          </div>
        </div>
      )}

      <p className="text-[9px] text-warm-400 pt-1 border-t border-warm-100">
        Indicators are tracked from: {Object.values(SOURCE_LABELS).join(" · ")} — only what this patient&apos;s data supports.
      </p>
    </div>
  );
}
