import React from "react";

/**
 * Tabs — underline tab bar. `tabs` is [{id,label}]; controlled via `value`/`onChange`.
 */
export function Tabs({ tabs = [], value, onChange, style = {} }) {
  return (
    <div style={{ display: "flex", gap: "2px", borderBottom: "1px solid var(--border-subtle)", fontFamily: "var(--font-sans)", ...style }}>
      {tabs.map((t) => {
        const active = t.id === value;
        return (
          <button
            key={t.id}
            type="button"
            onClick={() => onChange?.(t.id)}
            style={{
              appearance: "none",
              background: "transparent",
              border: "none",
              borderBottom: `2px solid ${active ? "var(--brand-primary)" : "transparent"}`,
              padding: "10px 14px",
              marginBottom: "-1px",
              fontSize: "13px",
              fontWeight: active ? 700 : 500,
              color: active ? "var(--green-700)" : "var(--text-muted)",
              cursor: "pointer",
              transition: "color var(--dur-fast) var(--ease-out)",
            }}
          >
            {t.label}
          </button>
        );
      })}
    </div>
  );
}
