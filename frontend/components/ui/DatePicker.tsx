"use client";

import { useEffect, useMemo, useState } from "react";

type DatePickerProps = {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  ariaLabel?: string;
  min?: string;
  max?: string;
  disabled?: boolean;
  required?: boolean;
  className?: string;
};

function dateParts(value: string): [number, number, number] {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  return match ? [Number(match[1]), Number(match[2]), Number(match[3])] : [0, 0, 0];
}

export function DatePicker({
  value,
  onChange,
  label,
  ariaLabel = label ?? "Date",
  min,
  max,
  disabled = false,
  required = false,
  className = "",
}: DatePickerProps) {
  const [year, setYear] = useState(() => dateParts(value)[0]);
  const [month, setMonth] = useState(() => dateParts(value)[1]);
  const [day, setDay] = useState(() => dateParts(value)[2]);

  useEffect(() => {
    const next = dateParts(value);
    setYear(next[0]);
    setMonth(next[1]);
    setDay(next[2]);
  }, [value]);

  const currentYear = new Date().getFullYear();
  const minYear = min ? Number(min.slice(0, 4)) : currentYear - 120;
  const maxYear = max ? Number(max.slice(0, 4)) : currentYear + 20;
  const years = useMemo(
    () => Array.from({ length: Math.max(0, maxYear - minYear + 1) }, (_, index) => maxYear - index),
    [maxYear, minYear],
  );
  const daysInMonth = year && month ? new Date(year, month, 0).getDate() : 31;

  function commit(nextYear: number, nextMonth: number, nextDay: number) {
    const validDay = nextYear && nextMonth ? Math.min(nextDay, new Date(nextYear, nextMonth, 0).getDate()) : nextDay;
    setYear(nextYear);
    setMonth(nextMonth);
    setDay(validDay);
    if (!nextYear || !nextMonth || !validDay) {
      onChange("");
      return;
    }
    const next = `${nextYear}-${String(nextMonth).padStart(2, "0")}-${String(validDay).padStart(2, "0")}`;
    if ((min && next < min) || (max && next > max)) return;
    onChange(next);
  }

  const controlClass = "min-h-11 w-full rounded-lg border border-warm-200 bg-white px-3 text-base text-warm-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 disabled:bg-warm-50 disabled:text-warm-400";

  return (
    <fieldset className={`min-w-0 ${className}`} disabled={disabled}>
      {label && <legend className="mb-1.5 text-sm font-semibold text-warm-600">{label}{required && <span className="ml-0.5 text-red-500">*</span>}</legend>}
      <div className="grid grid-cols-[1.15fr_0.8fr_1fr] gap-2">
        <select aria-label={`${ariaLabel} month`} value={month || ""} required={required} onChange={(event) => commit(year, Number(event.target.value), day)} className={controlClass}>
          <option value="">Month</option>
          {Array.from({ length: 12 }, (_, index) => <option key={index + 1} value={index + 1}>{new Date(2000, index, 1).toLocaleString("en", { month: "short" })}</option>)}
        </select>
        <select aria-label={`${ariaLabel} day`} value={day || ""} required={required} onChange={(event) => commit(year, month, Number(event.target.value))} className={controlClass}>
          <option value="">Day</option>
          {Array.from({ length: daysInMonth }, (_, index) => <option key={index + 1} value={index + 1}>{index + 1}</option>)}
        </select>
        <select aria-label={`${ariaLabel} year`} value={year || ""} required={required} onChange={(event) => commit(Number(event.target.value), month, day)} className={controlClass}>
          <option value="">Year</option>
          {years.map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
      </div>
    </fieldset>
  );
}

export function DateTimePicker(props: DatePickerProps) {
  const [date = "", time = ""] = props.value.split("T");
  return (
    <div className={`grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem] ${props.className ?? ""}`}>
      <DatePicker {...props} className="" value={date} onChange={(nextDate) => props.onChange(nextDate ? `${nextDate}T${time || "00:00"}` : "")} />
      <label className="block">
        <span className="mb-1.5 block text-sm font-semibold text-warm-600">Time</span>
        <input aria-label={`${props.ariaLabel ?? props.label ?? "Date"} time`} type="time" value={time} disabled={props.disabled} required={props.required} onChange={(event) => props.onChange(date ? `${date}T${event.target.value}` : "")} className="min-h-11 w-full rounded-lg border border-warm-200 bg-white px-3 text-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30" />
      </label>
    </div>
  );
}
