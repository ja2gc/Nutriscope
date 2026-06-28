import React from "react";

/**
 * Canonical surface. White card, zinc border, rounded-2xl, soft shadow — the
 * shared container used across Food Service, NCP, and Reports pages so every
 * panel reads the same. Pass `padded` for the common p-5 inset.
 */
export function Card({
  className = "",
  padded = false,
  children,
  ...props
}: React.HTMLAttributes<HTMLDivElement> & { padded?: boolean }) {
  return (
    <div
      className={`bg-white border border-warm-200 rounded-2xl shadow-sm ${padded ? "p-5" : ""} ${className}`}
      {...props}
    >
      {children}
    </div>
  );
}
