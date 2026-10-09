"use client";

export const MONTH_NAMES = Array.from({ length: 12 }, (_, index) =>
  new Intl.DateTimeFormat("en-US", { month: "long" }).format(new Date(2000, index, 1)),
);

const selectClass = "h-11 min-w-0 rounded-xl border border-warm-200 bg-white px-3 text-sm text-warm-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20";

export function MonthYearSelect({
  month,
  year,
  onMonthChange,
  onYearChange,
  monthAriaLabel,
  yearAriaLabel,
  allowAllMonths = false,
}: {
  month: number | null;
  year: number;
  onMonthChange: (month: number | null) => void;
  onYearChange: (year: number) => void;
  monthAriaLabel: string;
  yearAriaLabel: string;
  allowAllMonths?: boolean;
}) {
  const lastYear = Math.max(new Date().getFullYear() + 1, year);
  const years = Array.from({ length: lastYear - 1999 }, (_, index) => lastYear - index);

  return (
    <>
      <label className="grid min-w-0 gap-1 text-xs font-semibold text-warm-600">
        Month
        <select aria-label={monthAriaLabel} value={month ?? ""} onChange={(event) => onMonthChange(event.target.value ? Number(event.target.value) : null)} className={selectClass}>
          {allowAllMonths && <option value="">All dates</option>}
          {MONTH_NAMES.map((label, index) => <option key={label} value={index + 1}>{label}</option>)}
        </select>
      </label>
      <label className="grid min-w-0 gap-1 text-xs font-semibold text-warm-600">
        Year
        <select aria-label={yearAriaLabel} value={year} onChange={(event) => onYearChange(Number(event.target.value))} disabled={allowAllMonths && month === null} className={`${selectClass} disabled:cursor-not-allowed disabled:opacity-50`}>
          {years.map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
      </label>
    </>
  );
}
