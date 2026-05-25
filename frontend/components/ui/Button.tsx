import React, { forwardRef, ButtonHTMLAttributes } from "react";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "danger";
  loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ children, variant = "primary", loading, className = "", disabled, ...props }, ref) => {
    const baseStyles = "px-4 py-2 text-sm font-semibold rounded flex items-center justify-center gap-2 cursor-pointer transition-colors focus:outline-none select-none disabled:opacity-50 disabled:cursor-not-allowed w-full";
    
    const variants = {
      primary: "bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white focus:ring-2 focus:ring-blue-500/25",
      secondary: "bg-white hover:bg-gray-50 active:bg-gray-100 border border-gray-300 text-gray-700 focus:ring-2 focus:ring-gray-200",
      danger: "bg-red-600 hover:bg-red-700 active:bg-red-800 text-white focus:ring-2 focus:ring-red-500/25",
    };

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={`${baseStyles} ${variants[variant]} ${className}`}
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
                cx="12" 
                cy="12" 
                r="10" 
                stroke="currentColor" 
                strokeWidth="4"
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
