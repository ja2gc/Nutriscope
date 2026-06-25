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
    sm: "px-3 py-1.5 text-xs",
    md: "px-4 py-2.5 text-sm",
    icon: "p-1.5 text-sm",
  },
  variants: {
    primary: "bg-brand-green-600 hover:bg-brand-green-700 active:bg-brand-green-700 text-white",
    secondary: "bg-white hover:bg-zinc-50 active:bg-zinc-100 border border-zinc-200 text-zinc-700",
    ghost: "text-zinc-500 hover:bg-zinc-100 active:bg-zinc-200",
    danger: "bg-red-600 hover:bg-red-700 active:bg-red-800 text-white focus-visible:ring-red-500/25",
    icon: "w-auto text-zinc-400 hover:bg-zinc-100 active:bg-zinc-200",
  },
};

export const badgeToneClasses = {
  emerald: "bg-brand-green-50 text-brand-green-700 border-brand-green-200",
  amber: "bg-brand-orange-50 text-brand-orange-700 border-brand-orange-100",
  red: "bg-red-50 text-red-700 border-red-200",
  zinc: "bg-zinc-100 text-zinc-600 border-zinc-200",
  sky: "bg-sky-50 text-sky-700 border-sky-200",
  violet: "bg-violet-50 text-violet-700 border-violet-200",
};

export const statusToneClasses = {
  success: "bg-brand-green-50 text-brand-green-700 border-brand-green-200",
  warning: "bg-brand-orange-50 text-brand-orange-700 border-brand-orange-100",
  error: "bg-red-50 text-red-700 border-red-200",
  info: "bg-sky-50 text-sky-700 border-sky-200",
  neutral: "bg-zinc-50 text-zinc-600 border-zinc-200",
};

export const statusDotClasses = {
  success: "bg-brand-green-500",
  warning: "bg-brand-orange-500",
  error: "bg-red-500",
  info: "bg-sky-500",
  neutral: "bg-zinc-300",
};

export const tabTheme = {
  active: "border-brand-green-600 text-brand-green-700",
  inactive: "border-transparent text-zinc-500 hover:text-zinc-800",
  focus: "focus-visible:ring-2 focus-visible:ring-brand-green-500/30 focus-visible:outline-none",
};
