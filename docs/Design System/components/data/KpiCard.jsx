import React from "react";

const TONES = {
  neutral: { bg: "var(--neutral-50)", bd: "var(--neutral-200)", fg: "var(--neutral-700)" },
  emerald: { bg: "var(--green-50)", bd: "var(--green-200)", fg: "var(--green-700)" },
  amber: { bg: "var(--orange-50)", bd: "var(--orange-200)", fg: "var(--orange-700)" },
  red: { bg: "var(--status-danger-bg)", bd: "#fecaca", fg: "var(--status-danger)" },
  sky: { bg: "var(--status-info-bg)", bd: "#bae6fd", fg: "var(--status-info)" },
};

/**
 * KpiCard — metric tile: uppercase label, big tabular value, optional hint.
 * Tone tints the whole tile for at-a-glance scanning.
 */
export function KpiCard({ label, value, hint, tone = "neutral", icon = null, style = {} }) {
  const t = TONES[tone] || TONES.neutral;
  return (
    <div
      style={{
        padding: "14px 16px",
        borderRadius: "var(--radius-xl)",
        background: t.bg,
        border: `1px solid ${t.bd}`,
        fontFamily: "var(--font-sans)",
        color: t.fg,
        ...style,
      }}
    >
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "8px" }}>
        <span style={{ fontSize: "10.5px", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.07em", opacity: 0.72 }}>
          {label}
        </span>
        {icon}
      </div>
      <div style={{ fontSize: "26px", fontWeight: 800, marginTop: "4px", fontFamily: "var(--font-mono)", letterSpacing: "-0.01em", fontVariantNumeric: "tabular-nums" }}>
        {value}
      </div>
      {hint && <div style={{ fontSize: "11px", marginTop: "3px", opacity: 0.66 }}>{hint}</div>}
    </div>
  );
}
