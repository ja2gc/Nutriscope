import React from "react";

function Mark({ size = 30 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32" fill="none" style={{ flexShrink: 0 }}>
      <circle cx="16" cy="16" r="12" stroke="#ea580c" strokeWidth="1.5" strokeDasharray="4 2" opacity="0.75" />
      <circle cx="16" cy="16" r="6" stroke="#ea580c" strokeWidth="1" opacity="0.4" />
      <line x1="16" y1="2" x2="16" y2="6" stroke="#ea580c" strokeWidth="1.5" />
      <line x1="16" y1="26" x2="16" y2="30" stroke="#ea580c" strokeWidth="1.5" />
      <line x1="2" y1="16" x2="6" y2="16" stroke="#ea580c" strokeWidth="1.5" />
      <line x1="26" y1="16" x2="30" y2="16" stroke="#ea580c" strokeWidth="1.5" />
      <path d="M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z" fill="#059669" />
      <path d="M16 8C16 13 18.5 17 21 19.5" stroke="#d1fae5" strokeWidth="1" strokeLinecap="round" opacity="0.9" />
      <path d="M16 24V14" stroke="#10b981" strokeWidth="1.2" strokeLinecap="round" />
    </svg>
  );
}

/**
 * Logo — leaf + diagnostic-scope mark with the Nutri/Scope wordmark.
 * variant "light" for white surfaces, "forest" for the dark green nav.
 */
export function Logo({ variant = "light", collapsed = false, size = 30 }) {
  const nutri = variant === "forest" ? "#34d399" : "var(--brand-nutri)";
  const scope = variant === "forest" ? "#fb923c" : "var(--brand-scope)";
  return (
    <div style={{ display: "inline-flex", alignItems: "center", gap: "10px", userSelect: "none" }}>
      <Mark size={size} />
      {!collapsed && (
        <div style={{ display: "flex", alignItems: "baseline", fontFamily: "var(--font-sans)" }}>
          <span style={{ fontSize: size * 0.55, fontWeight: 800, letterSpacing: "-0.03em", color: nutri }}>Nutri</span>
          <span style={{ fontSize: size * 0.55, fontWeight: 800, letterSpacing: "-0.03em", color: scope }}>Scope</span>
        </div>
      )}
    </div>
  );
}
