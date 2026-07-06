import React from "react";

export type KpiTone = "zinc" | "emerald" | "red" | "amber" | "sky";

interface ToneConfig {
  border: string;
  labelColor: string;
}

const TONES: Record<KpiTone, ToneConfig> = {
  zinc:    { border: "border-warm-200",                                          labelColor: "text-warm-500"         },
  emerald: { border: "border-warm-200 border-l-4 border-l-brand-green-500",     labelColor: "text-brand-green-600"  },
  red:     { border: "border-warm-200 border-l-4 border-l-red-500",             labelColor: "text-red-600"          },
  amber:   { border: "border-warm-200 border-l-4 border-l-amber-500",           labelColor: "text-amber-600"        },
  sky:     { border: "border-warm-200 border-l-4 border-l-sky-500",             labelColor: "text-sky-600"          },
};

/** Metric tile — uppercase label + big tabular value. White card, tone shown via left border accent. */
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
  const { border, labelColor } = TONES[tone];
  return (
    <div className={`px-4 py-3 bg-white rounded-2xl border ${border} shadow-sm`}>
      <div className={`text-xs font-extrabold uppercase tracking-wider ${labelColor}`}>{label}</div>
      <div className="text-xl font-bold mt-0.5 font-numeric text-warm-900">{value}</div>
      {hint && <div className="text-xs mt-0.5 text-warm-400">{hint}</div>}
    </div>
  );
}
