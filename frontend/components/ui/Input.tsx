import React, { forwardRef, InputHTMLAttributes } from "react";

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, className = "", id, ...props }, ref) => {
    const inputId = id || label.toLowerCase().replace(/\s+/g, "-");
    
    return (
      <div className="flex flex-col gap-1.5 w-full">
        <label 
          htmlFor={inputId} 
          className="text-xs font-semibold text-warm-600 select-none tracking-wide"
        >
          {label}
        </label>
        <input
          ref={ref}
          id={inputId}
          className={`w-full px-3.5 py-2 text-sm bg-white border rounded-lg border-warm-300 text-warm-900 focus:outline-none focus:ring-2 focus:ring-brand-green-500/20 focus:border-brand-green-600 transition-all placeholder:text-warm-400 ${
            error ? "border-red-500 focus:ring-red-500/20 focus:border-red-500" : ""
          } ${className}`}
          {...props}
        />
        {error && (
          <span className="text-xs font-semibold text-red-600 mt-0.5">
            {error}
          </span>
        )}
      </div>
    );
  }
);

Input.displayName = "Input";

