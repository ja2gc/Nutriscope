import React from "react";

/**
 * Input — labelled text field. Brand-green focus ring, error state.
 * Always pass a `label`; pass `error` to show a validation message.
 */
export function Input({ label, error, hint, id, style = {}, ...props }) {
  const [focus, setFocus] = React.useState(false);
  const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, "-") : undefined);
  const borderColor = error
    ? "var(--status-danger)"
    : focus
    ? "var(--brand-primary)"
    : "var(--border-strong)";
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "6px", width: "100%", fontFamily: "var(--font-sans)" }}>
      {label && (
        <label htmlFor={inputId} style={{ fontSize: "13px", fontWeight: 600, color: "var(--text-body)" }}>
          {label}
        </label>
      )}
      <input
        id={inputId}
        onFocus={(e) => { setFocus(true); props.onFocus?.(e); }}
        onBlur={(e) => { setFocus(false); props.onBlur?.(e); }}
        style={{
          width: "100%",
          boxSizing: "border-box",
          padding: "9px 13px",
          fontSize: "14px",
          fontFamily: "var(--font-sans)",
          color: "var(--text-strong)",
          background: "var(--surface-card)",
          border: `1px solid ${borderColor}`,
          borderRadius: "var(--radius-md)",
          outline: "none",
          boxShadow: focus ? "var(--ring-focus)" : "none",
          transition: "border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)",
          ...style,
        }}
        {...props}
      />
      {error ? (
        <span style={{ fontSize: "12px", fontWeight: 600, color: "var(--status-danger)" }}>{error}</span>
      ) : hint ? (
        <span style={{ fontSize: "12px", color: "var(--text-muted)" }}>{hint}</span>
      ) : null}
    </div>
  );
}
