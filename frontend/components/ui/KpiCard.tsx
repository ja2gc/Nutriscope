import React from "react";

export type KpiTone = "zinc" | "emerald" | "red" | "amber" | "sky";

const TONES: Record<KpiTone, string> = {
  zinc: "bg-zinc-50 border-zinc-200 text-zinc-700",
  emerald: "bg-emerald-50 border-emerald-200 text-emerald-700",
  red: "bg-red-50 border-red-200 text-red-700",
  amber: "bg-amber-50 border-amber-200 text-amber-700",
  sky: "bg-sky-50 border-sky-200 text-sky-700",
};

/** Metric tile — uppercase label + big tabular value. Shared KPI card. */
export function KpiCard({
  label,
  value,
  hint,
  tone = "zinc",
}: {
  label: string;
  value: React.ReactNode;
  hint?: string;
  tone?: KpiTone;
}) {
  return (
    <div className={`px-4 py-3 rounded-2xl border ${TONES[tone]}`}>
      <div className="text-[10px] font-extrabold uppercase tracking-wider opacity-70">{label}</div>
      <div className="text-xl font-extrabold mt-0.5 tabular-nums">{value}</div>
      {hint && <div className="text-[10px] mt-0.5 opacity-60">{hint}</div>}
    </div>
  );
}
