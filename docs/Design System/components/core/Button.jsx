import React from "react";

const sizes = {
  sm: { padding: "6px 12px", fontSize: "13px", gap: "6px" },
  md: { padding: "9px 16px", fontSize: "14px", gap: "8px" },
  lg: { padding: "12px 22px", fontSize: "15px", gap: "8px" },
};

const variants = {
  primary: {
    background: "var(--brand-primary)",
    color: "var(--text-onbrand)",
    border: "1px solid transparent",
    boxShadow: "var(--shadow-xs)",
    "--hover-bg": "var(--brand-primary-hover)",
  },
  secondary: {
    background: "var(--surface-card)",
    color: "var(--text-body)",
    border: "1px solid var(--border-strong)",
    "--hover-bg": "var(--surface-sunken)",
  },
  ghost: {
    background: "transparent",
    color: "var(--text-muted)",
    border: "1px solid transparent",
    "--hover-bg": "var(--surface-hover)",
  },
  danger: {
    background: "var(--status-danger)",
    color: "#fff",
    border: "1px solid transparent",
    "--hover-bg": "#b91c1c",
  },
  accent: {
    background: "var(--brand-accent)",
    color: "#fff",
    border: "1px solid transparent",
    "--hover-bg": "var(--orange-700)",
  },
};

/**
 * Button — the primary interactive control.
 * variant: primary | secondary | ghost | danger | accent
 * size: sm | md | lg
 */
export function Button({
  children,
  variant = "primary",
  size = "md",
  fullWidth = false,
  loading = false,
  disabled = false,
  leftIcon = null,
  style = {},
  ...props
}) {
  const [hover, setHover] = React.useState(false);
  const v = variants[variant] || variants.primary;
  const s = sizes[size] || sizes.md;
  const isDisabled = disabled || loading;

  return (
    <button
      type="button"
      disabled={isDisabled}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        gap: s.gap,
        padding: s.padding,
        fontSize: s.fontSize,
        fontFamily: "var(--font-sans)",
        fontWeight: 600,
        lineHeight: 1.2,
        borderRadius: "var(--radius-md)",
        cursor: isDisabled ? "not-allowed" : "pointer",
        opacity: isDisabled ? 0.55 : 1,
        width: fullWidth ? "100%" : "auto",
        transition: "background var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)",
        background: hover && !isDisabled && v["--hover-bg"] ? v["--hover-bg"] : v.background,
        color: v.color,
        border: v.border,
        boxShadow: v.boxShadow || "none",
        ...style,
      }}
      {...props}
    >
      {loading && (
        <span
          style={{
            width: "14px",
            height: "14px",
            border: "2px solid currentColor",
            borderTopColor: "transparent",
            borderRadius: "50%",
            display: "inline-block",
            animation: "ns-spin 0.6s linear infinite",
          }}
        />
      )}
      {!loading && leftIcon}
      {loading ? "Processing…" : children}
      <style>{`@keyframes ns-spin{to{transform:rotate(360deg)}}`}</style>
    </button>
  );
}
