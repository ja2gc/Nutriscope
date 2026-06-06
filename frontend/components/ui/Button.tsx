import React, { forwardRef, ButtonHTMLAttributes } from "react";

// Variants:
// primary   — main positive action (Save, Apply, Generate, Add)
// secondary — neutral action (New Week, From Template, Save Template, Auto-Generate)
// ghost     — cancel / text links / Change Goal
// danger    — destructive (Delete Plan, Delete Template)
// icon      — icon-only square button (trash, edit pencil)

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "ghost" | "danger" | "icon";
  loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ children, variant = "primary", loading, className = "", disabled, ...props }, ref) => {
    const base = "flex items-center justify-center gap-2 cursor-pointer transition-all duration-200 focus:outline-none select-none disabled:opacity-50 disabled:cursor-not-allowed font-semibold rounded-lg";

    const variants = {
      primary:   "px-4.5 py-2.5 w-full text-sm bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white focus:ring-2 focus:ring-emerald-500/25",
      secondary: "px-4.5 py-2.5 w-full text-sm bg-white hover:bg-zinc-50 active:bg-zinc-100 border border-zinc-200 text-zinc-700 focus:ring-2 focus:ring-zinc-500/10",
      ghost:     "px-3 py-2 text-sm text-zinc-500 hover:bg-zinc-100 active:bg-zinc-200 focus:ring-2 focus:ring-zinc-500/10",
      danger:    "px-4.5 py-2.5 w-full text-sm bg-red-600 hover:bg-red-700 active:bg-red-800 text-white focus:ring-2 focus:ring-red-500/25",
      icon:      "p-1.5 w-auto text-zinc-400 hover:bg-zinc-100 active:bg-zinc-200 focus:ring-2 focus:ring-zinc-500/10",
    };

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={`${base} ${variants[variant]} ${className}`}
        {...props}
      >
        {loading ? (
          <>
            <svg
              className="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                className="opacity-25"
                cx="12" cy="12" r="10"
                stroke="currentColor" strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            Processing...
          </>
        ) : (
          children
        )}
      </button>
    );
  }
);

Button.displayName = "Button";
