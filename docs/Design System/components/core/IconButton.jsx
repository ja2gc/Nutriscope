import React from "react";

/**
 * IconButton — square, icon-only affordance (header actions, table row tools).
 * Pass a Lucide (or any) icon node as children.
 */
export function IconButton({
  children,
  size = "md",
  tone = "neutral",
  active = false,
  style = {},
  "aria-label": ariaLabel,
  ...props
}) {
  const [hover, setHover] = React.useState(false);
  const dim = size === "sm" ? 30 : size === "lg" ? 42 : 36;
  const tones = {
    neutral: { color: "var(--text-muted)", hover: "var(--surface-hover)" },
    brand: { color: "var(--brand-primary)", hover: "var(--brand-primary-soft)" },
    accent: { color: "var(--brand-accent)", hover: "var(--brand-accent-soft)" },
    danger: { color: "var(--status-danger)", hover: "var(--status-danger-bg)" },
  };
  const t = tones[tone] || tones.neutral;
  return (
    <button
      type="button"
      aria-label={ariaLabel}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        width: dim,
        height: dim,
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        borderRadius: "var(--radius-md)",
        border: "none",
        cursor: "pointer",
        color: t.color,
        background: active || hover ? t.hover : "transparent",
        transition: "background var(--dur-fast) var(--ease-out)",
        ...style,
      }}
      {...props}
    >
      {children}
    </button>
  );
}
