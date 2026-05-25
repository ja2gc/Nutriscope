import React from "react";

interface LogoProps {
  variant?: "light" | "dark";
  collapsed?: boolean;
}

export function Logo({ variant = "light", collapsed = false }: LogoProps) {
  const isDark = variant === "dark";

  return (
    <div className="flex items-center gap-2.5 select-none shrink-0">
      {/* Handcrafted Leaf + Crosshair Brand Icon */}
      <div className="relative flex items-center justify-center h-8 w-8 shrink-0">
        <svg
          className="h-7 w-7 transition-transform duration-300 hover:rotate-12"
          viewBox="0 0 32 32"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          {/* Diagnostic Scope Outer Rings / Lens Crosshairs */}
          <circle
            cx="16"
            cy="16"
            r="12"
            stroke="#ea580c"
            strokeWidth="1.5"
            strokeDasharray="4 2"
            className="opacity-75"
          />
          <circle
            cx="16"
            cy="16"
            r="6"
            stroke="#ea580c"
            strokeWidth="1"
            className="opacity-40"
          />
          {/* Scope reticle tick marks */}
          <line x1="16" y1="2" x2="16" y2="6" stroke="#ea580c" strokeWidth="1.5" />
          <line x1="16" y1="26" x2="16" y2="30" stroke="#ea580c" strokeWidth="1.5" />
          <line x1="2" y1="16" x2="6" y2="16" stroke="#ea580c" strokeWidth="1.5" />
          <line x1="26" y1="16" x2="30" y2="16" stroke="#ea580c" strokeWidth="1.5" />

          {/* Premium Green Leaf (Overlayed & Integrated in the center) */}
          <path
            d="M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z"
            fill="#059669"
            className="drop-shadow-sm"
          />
          <path
            d="M16 8C16 13 18.5 17 21 19.5"
            stroke="#d1fae5"
            strokeWidth="1"
            strokeLinecap="round"
            className="opacity-90"
          />
          <path
            d="M16 24V14"
            stroke="#10b981"
            strokeWidth="1.2"
            strokeLinecap="round"
          />
        </svg>
      </div>

      {/* Wordmark (Nutri + Scope) */}
      {!collapsed && (
        <div className="flex items-baseline">
          <span className="text-base font-extrabold tracking-tight text-emerald-600">
            Nutri
          </span>
          <span className={`text-base font-extrabold tracking-tight ${
            isDark ? "text-zinc-100" : "text-orange-600"
          }`}>
            Scope
          </span>
        </div>
      )}
    </div>
  );
}
