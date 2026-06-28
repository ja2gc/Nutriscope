import React from "react";

const TONES = {
  emerald: { bg: "var(--green-50)", fg: "var(--green-700)", bd: "var(--green-200)" },
  amber: { bg: "var(--orange-50)", fg: "var(--orange-700)", bd: "var(--orange-200)" },
  red: { bg: "var(--status-danger-bg)", fg: "var(--status-danger)", bd: "#fecaca" },
  sky: { bg: "var(--status-info-bg)", fg: "var(--status-info)", bd: "#bae6fd" },
  lime: { bg: "#f7fee7", fg: "var(--lime-600)", bd: "#d9f99d" },
  neutral: { bg: "var(--neutral-100)", fg: "var(--neutral-600)", bd: "var(--neutral-200)" },
};

/** Badge — small status/category pill. Pair tone with text, never color alone. */
export function Badge({ children, tone = "neutral", style = {} }) {
  const t = TONES[tone] || TONES.neutral;
  return (
    <span
      style={{
        display: "inline-flex",
        alignItems: "center",
        gap: "5px",
        padding: "3px 9px",
        borderRadius: "var(--radius-full)",
        background: t.bg,
        color: t.fg,
        border: `1px solid ${t.bd}`,
        fontFamily: "var(--font-sans)",
        fontSize: "11px",
        fontWeight: 700,
        textTransform: "uppercase",
        letterSpacing: "0.04em",
        whiteSpace: "nowrap",
        ...style,
      }}
    >
      {children}
    </span>
  );
}
