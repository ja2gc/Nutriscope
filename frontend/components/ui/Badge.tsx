import React from "react";

export type BadgeTone = "emerald" | "amber" | "red" | "zinc" | "sky" | "violet";

const TONES: Record<BadgeTone, string> = {
  emerald: "bg-emerald-50 text-emerald-700 border-emerald-200",
  amber: "bg-amber-50 text-amber-700 border-amber-200",
  red: "bg-red-50 text-red-700 border-red-200",
  zinc: "bg-zinc-100 text-zinc-600 border-zinc-200",
  sky: "bg-sky-50 text-sky-700 border-sky-200",
  violet: "bg-violet-50 text-violet-700 border-violet-200",
};

/**
 * Status pill. Tone carries semantic meaning (red=danger, amber=warn,
 * emerald=ok) but never *only* color — always pair with text/icon (a11y).
 */
export function Badge({
  tone = "zinc",
  className = "",
  children,
}: {
  tone?: BadgeTone;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <span
      className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider ${TONES[tone]} ${className}`}
    >
      {children}
    </span>
  );
}
