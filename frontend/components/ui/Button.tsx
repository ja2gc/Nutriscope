import React, { forwardRef, ButtonHTMLAttributes } from "react";
import { buttonTheme } from "./theme";

// Variants:
// primary   — main positive action (Save, Apply, Generate, Add)
// secondary — neutral action (New Week, From Template, Save Template, Auto-Generate)
// ghost     — cancel / text links / Change Goal
// danger    — destructive (Delete Plan, Delete Template)
// icon      — icon-only square button (trash, edit pencil)

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "ghost" | "danger" | "icon";
  size?: "sm" | "md";
  fullWidth?: boolean;
  loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ children, variant = "primary", size = "md", fullWidth = false, loading, className = "", disabled, ...props }, ref) => {
    const sizeClass = variant === "icon" ? buttonTheme.sizes.icon : buttonTheme.sizes[size];

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        aria-busy={loading || undefined}
        className={`${buttonTheme.base} ${sizeClass} ${fullWidth ? "w-full" : "w-auto"} ${buttonTheme.variants[variant]} ${className}`}
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
