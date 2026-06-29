import React from "react";
import { brand } from "./theme";

interface LogoProps {
  variant?: "light" | "dark";
  collapsed?: boolean;
}

const COLORS = {
  light: {
    leaf:   brand.greenStroke,
    vein:   brand.greenSoftStroke,
    stem:   brand.greenAccentStroke,
    ring:   brand.orangeStroke,
    nutri:  brand.nutriText,
    scope:  brand.scopeText,
  },
  dark: {
    leaf:   "var(--color-brand-green-400)",
    vein:   "var(--color-brand-green-200)",
    stem:   "var(--color-brand-green-300)",
    ring:   "var(--color-brand-orange-500)",
    nutri:  brand.nutriText,
    scope:  brand.scopeText,
  },
};

export function Logo({ variant = "light", collapsed = false }: LogoProps) {
  const c = COLORS[variant];
  return (
    <div className="flex items-center gap-2.5 select-none shrink-0" data-variant={variant}>
      <div className="relative flex items-center justify-center h-8 w-8 shrink-0">
        <svg
          className="h-7 w-7 transition-transform duration-300 hover:rotate-12"
          viewBox="0 0 32 32"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <circle cx="16" cy="16" r="12" stroke={c.ring} strokeWidth="1.5" strokeDasharray="4 2" className="opacity-75" />
          <circle cx="16" cy="16" r="6"  stroke={c.ring} strokeWidth="1"   className="opacity-40" />
          <line x1="16" y1="2"  x2="16" y2="6"  stroke={c.ring} strokeWidth="1.5" />
          <line x1="16" y1="26" x2="16" y2="30" stroke={c.ring} strokeWidth="1.5" />
          <line x1="2"  y1="16" x2="6"  y2="16" stroke={c.ring} strokeWidth="1.5" />
          <line x1="26" y1="16" x2="30" y2="16" stroke={c.ring} strokeWidth="1.5" />
          <path
            d="M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z"
            fill={c.leaf}
            className="drop-shadow-sm"
          />
          <path d="M16 8C16 13 18.5 17 21 19.5" stroke={c.vein} strokeWidth="1"   strokeLinecap="round" className="opacity-90" />
          <path d="M16 24V14"                     stroke={c.stem} strokeWidth="1.2" strokeLinecap="round" />
        </svg>
      </div>

      {!collapsed && (
        <div className="flex items-baseline">
          <span className={`text-base font-extrabold tracking-tight ${c.nutri}`}>Nutri</span>
          <span className={`text-base font-extrabold tracking-tight ${c.scope}`}>Scope</span>
        </div>
      )}
    </div>
  );
}
