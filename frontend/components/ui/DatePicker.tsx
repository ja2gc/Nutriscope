"use client";

import { useEffect, useMemo, useState } from "react";
import { Popover, PopoverContent, PopoverTrigger } from "./popover";
import { ChevronLeft, ChevronRight, CalendarDays } from "lucide-react";

type DatePickerProps = {
  value: string;
  onChange: (value: string) => void;
  label?: string;
  ariaLabel?: string;
  min?: string;
  max?: string;
  disabled?: boolean;
  required?: boolean;
  compactLabels?: boolean;
  className?: string;
  calendar?: boolean;
  isDateDisabled?: (date: string) => boolean;
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
  compactLabels = false,
  className = "",
  calendar = false,
  isDateDisabled,
}: DatePickerProps) {
  const [open, setOpen] = useState(false);
  const [view, setView] = useState(() => value.slice(0, 7) || new Date().toISOString().slice(0, 7));
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
    const next = `${nextYear}-${String(nextMonth).padStart(2, "0")}-${String(validDay).padStart(2, "0")}`;
    if (nextYear && nextMonth && validDay && ((min && next < min) || (max && next > max) || isDateDisabled?.(next))) return;
    setYear(nextYear);
    setMonth(nextMonth);
    setDay(validDay);
    if (!nextYear || !nextMonth || !validDay) {
      onChange("");
      return;
    }
    onChange(next);
  }

  const controlClass = "min-h-11 rounded-lg border border-warm-200 bg-white px-3 text-base text-warm-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 disabled:bg-warm-50 disabled:text-warm-400";
  const monthWidth = `calc(${compactLabels ? 3 : 5}ch + 2.5rem)`;
  const dayWidth = `calc(${compactLabels ? 2 : 3}ch + 2.5rem)`;
  const yearWidth = "calc(4ch + 2.5rem)";

  if (calendar) {
    const [viewYear, viewMonth] = view.split('-').map(Number);
    const days = new Date(viewYear, viewMonth, 0).getDate();
    const offset = (new Date(viewYear, viewMonth - 1, 1).getDay() + 6) % 7;
    const move = (by: number) => { const date = new Date(Date.UTC(viewYear, viewMonth - 1 + by, 1)); setView(date.toISOString().slice(0, 7)); };
    return <fieldset className={`min-w-0 ${className}`} disabled={disabled}>
      {label && <legend className="mb-1.5 text-sm font-semibold text-warm-600">{label}{required && ' *'}</legend>}
      <Popover open={open} onOpenChange={(next) => { setOpen(next); if (next && value) setView(value.slice(0, 7)); }}>
        <PopoverTrigger asChild><button type="button" disabled={disabled} aria-label={ariaLabel} className={`${controlClass} flex w-full items-center justify-between gap-2`}><span>{value ? new Date(`${value}T00:00:00Z`).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }) : 'Select a date'}</span><CalendarDays className="h-4 w-4 text-warm-400" /></button></PopoverTrigger>
        <PopoverContent align="start" className="w-80 max-w-[calc(100vw-2rem)] p-3" aria-label={`${ariaLabel} calendar`}>
          <div className="mb-3 flex items-center gap-2">
            <button type="button" aria-label="Previous month" onClick={() => move(-1)} className="rounded p-2 hover:bg-warm-50"><ChevronLeft className="h-4 w-4" /></button>
            <select aria-label="Calendar month" value={viewMonth} onChange={(e) => setView(`${viewYear}-${e.target.value.padStart(2, '0')}`)} className="min-w-0 flex-1 rounded border border-warm-200 p-1.5">{Array.from({ length: 12 }, (_, index) => <option key={index} value={index + 1}>{new Date(2000, index, 1).toLocaleString('en', { month: 'long' })}</option>)}</select>
            <select aria-label="Calendar year" value={viewYear} onChange={(e) => setView(`${e.target.value}-${String(viewMonth).padStart(2, '0')}`)} className="rounded border border-warm-200 p-1.5">{years.map((option) => <option key={option}>{option}</option>)}</select>
            <button type="button" aria-label="Next month" onClick={() => move(1)} className="rounded p-2 hover:bg-warm-50"><ChevronRight className="h-4 w-4" /></button>
          </div>
          <div className="grid grid-cols-7 text-center text-xs text-warm-500">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((name) => <span key={name} className="py-2">{name}</span>)}{Array.from({ length: offset }, (_, index) => <span key={`blank-${index}`} />)}{Array.from({ length: days }, (_, index) => {
            const date = `${view}-${String(index + 1).padStart(2, '0')}`;
            const unavailable = Boolean((min && date < min) || (max && date > max) || isDateDisabled?.(date));
            return <button key={date} type="button" aria-label={`${date}${unavailable ? ' unavailable' : ''}`} aria-pressed={value === date} disabled={unavailable} title={unavailable ? 'Unavailable' : date} onClick={() => { onChange(date); setOpen(false); }} className={`min-h-10 rounded-lg text-sm focus-visible:ring-2 focus-visible:ring-emerald-500 ${value === date ? 'bg-emerald-600 text-white' : 'hover:bg-emerald-50'} disabled:bg-warm-100 disabled:text-warm-400 disabled:line-through`}>{index + 1}</button>;
          })}</div>
        </PopoverContent>
      </Popover>
    </fieldset>;
  }

  return (
    <fieldset className={`min-w-0 ${className}`} disabled={disabled}>
      {label && <legend className="mb-1.5 text-sm font-semibold text-warm-600">{label}{required && <span className="ml-0.5 text-red-500">*</span>}</legend>}
      <div className="flex w-fit gap-2">
        <select aria-label={`${ariaLabel} month`} value={month || ""} required={required} onChange={(event) => commit(year, Number(event.target.value), day)} className={controlClass} style={{ width: monthWidth }}>
          <option value="">{compactLabels ? "MM" : "Month"}</option>
          {Array.from({ length: 12 }, (_, index) => <option key={index + 1} value={index + 1}>{new Date(2000, index, 1).toLocaleString("en", { month: "short" })}</option>)}
        </select>
        <select aria-label={`${ariaLabel} day`} value={day || ""} required={required} onChange={(event) => commit(year, month, Number(event.target.value))} className={controlClass} style={{ width: dayWidth }}>
          <option value="">{compactLabels ? "DD" : "Day"}</option>
          {Array.from({ length: daysInMonth }, (_, index) => <option key={index + 1} value={index + 1}>{index + 1}</option>)}
        </select>
        <select aria-label={`${ariaLabel} year`} value={year || ""} required={required} onChange={(event) => commit(Number(event.target.value), month, day)} className={controlClass} style={{ width: yearWidth }}>
          <option value="">{compactLabels ? "YYYY" : "Year"}</option>
          {years.map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
      </div>
    </fieldset>
  );
}

export function DateTimePicker(props: DatePickerProps) {
  const [date = "", time = ""] = props.value.split("T");
  return (
    <div className={`grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_8rem] ${props.className ?? ""}`}>
      <DatePicker {...props} className="" value={date} onChange={(nextDate) => props.onChange(nextDate ? `${nextDate}T${time || "00:00"}` : "")} />
      <label className="block">
        <span className="mb-1.5 block text-sm font-semibold text-warm-600">Time</span>
        <input aria-label={`${props.ariaLabel ?? props.label ?? "Date"} time`} type="time" value={time} disabled={props.disabled} required={props.required} onChange={(event) => props.onChange(date ? `${date}T${event.target.value}` : "")} className="min-h-11 w-full rounded-lg border border-warm-200 bg-white px-3 text-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30" />
      </label>
    </div>
  );
}
