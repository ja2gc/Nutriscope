import React from "react";
import { badgeToneClasses } from "./theme";

export type BadgeTone = "emerald" | "amber" | "red" | "zinc" | "sky" | "violet";

const TONES: Record<BadgeTone, string> = badgeToneClasses;

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
