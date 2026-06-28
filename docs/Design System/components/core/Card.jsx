import React from "react";

/**
 * Card — the canonical NutriScope surface. White, subtle border, 16px radius,
 * soft shadow. `padded` adds the standard 20px inset.
 */
export function Card({ children, padded = false, interactive = false, style = {}, ...props }) {
  const [hover, setHover] = React.useState(false);
  return (
    <div
      onMouseEnter={() => interactive && setHover(true)}
      onMouseLeave={() => interactive && setHover(false)}
      style={{
        background: "var(--surface-card)",
        border: "1px solid var(--border-subtle)",
        borderRadius: "var(--radius-xl)",
        boxShadow: hover ? "var(--shadow-md)" : "var(--shadow-sm)",
        padding: padded ? "var(--space-5)" : 0,
        transition: "box-shadow var(--dur-base) var(--ease-out)",
        cursor: interactive ? "pointer" : "default",
        ...style,
      }}
      {...props}
    >
      {children}
    </div>
  );
}
