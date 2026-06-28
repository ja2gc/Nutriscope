import React from "react";

const STATUS = {
  success: { bg: "var(--status-success-bg)", fg: "var(--status-success)" },
  warning: { bg: "var(--status-warning-bg)", fg: "var(--status-warning)" },
  error: { bg: "var(--status-danger-bg)", fg: "var(--status-danger)" },
  info: { bg: "var(--status-info-bg)", fg: "var(--status-info)" },
  neutral: { bg: "var(--status-neutral-bg)", fg: "var(--status-neutral)" },
};

/** StatusBadge — pill with an optional leading dot. Semantic status states. */
export function StatusBadge({ label, status = "neutral", showDot = true, style = {} }) {
  const s = STATUS[status] || STATUS.neutral;
  return (
    <span
      style={{
        display: "inline-flex",
        alignItems: "center",
        gap: "6px",
        padding: "4px 10px",
        borderRadius: "var(--radius-full)",
        background: s.bg,
        color: s.fg,
        fontFamily: "var(--font-sans)",
        fontSize: "11px",
        fontWeight: 700,
        ...style,
      }}
    >
      {showDot && <span style={{ width: "6px", height: "6px", borderRadius: "50%", background: s.fg }} />}
      {label}
    </span>
  );
}
