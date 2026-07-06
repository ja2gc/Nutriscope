export const brand = {
  nutriText: "text-brand-green-600",
  scopeText: "text-brand-orange-600",
  greenStroke: "var(--color-brand-green-600)",
  greenAccentStroke: "var(--color-brand-green-500)",
  greenSoftStroke: "var(--color-brand-green-100)",
  orangeStroke: "var(--color-brand-orange-600)",
};

export const focusRing = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30";

export const buttonTheme = {
  base: `inline-flex items-center justify-center gap-2 cursor-pointer transition-all duration-200 select-none disabled:opacity-50 disabled:cursor-not-allowed font-semibold rounded-lg ${focusRing}`,
  sizes: {
    sm: "px-3 py-1.5 text-sm",
    md: "px-4 py-2.5 text-base",
    icon: "p-1.5 text-base",
  },
  variants: {
    primary: "bg-brand-green-600 hover:bg-brand-green-700 active:bg-brand-green-700 text-white",
    secondary: "bg-white hover:bg-warm-50 active:bg-warm-100 border border-warm-200 text-warm-700",
    ghost: "text-warm-500 hover:bg-warm-100 active:bg-warm-200",
    danger: "bg-red-600 hover:bg-red-700 active:bg-red-800 text-white focus-visible:ring-red-500/25",
    icon: "w-auto text-warm-400 hover:bg-warm-100 active:bg-warm-200",
  },
};

export const badgeToneClasses = {
  emerald: "bg-brand-green-50 text-brand-green-700 border-brand-green-200",
  amber: "bg-brand-orange-50 text-brand-orange-700 border-brand-orange-100",
  red: "bg-red-50 text-red-700 border-red-200",
  zinc: "bg-warm-100 text-warm-600 border-warm-200",
  sky: "bg-sky-50 text-sky-700 border-sky-200",
  violet: "bg-sky-50 text-sky-700 border-sky-200",
};

export const statusToneClasses = {
  success: "bg-brand-green-50 text-brand-green-700 border-brand-green-200",
  warning: "bg-brand-orange-50 text-brand-orange-700 border-brand-orange-100",
  error: "bg-red-50 text-red-700 border-red-200",
  info: "bg-sky-50 text-sky-700 border-sky-200",
  neutral: "bg-warm-50 text-warm-600 border-warm-200",
};

export const statusDotClasses = {
  success: "bg-brand-green-500",
  warning: "bg-brand-orange-500",
  error: "bg-red-500",
  info: "bg-sky-500",
  neutral: "bg-warm-300",
};

export const tabTheme = {
  active: "border-brand-green-600 text-brand-green-700",
  inactive: "border-transparent text-warm-500 hover:text-warm-800",
  focus: "focus-visible:ring-2 focus-visible:ring-brand-green-500/30 focus-visible:outline-none",
};
