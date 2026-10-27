"use client";

import { useMemo, useState } from "react";
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
  className?: string;
  isDateDisabled?: (date: string) => boolean;
};

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
  isDateDisabled,
}: DatePickerProps) {
  const [open, setOpen] = useState(false);
  const [view, setView] = useState(() => {
    const today = new Date();
    return value.slice(0, 7) || `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}`;
  });

  const currentYear = new Date().getFullYear();
  const minYear = min ? Number(min.slice(0, 4)) : currentYear - 120;
  const maxYear = max ? Number(max.slice(0, 4)) : currentYear + 20;
  const years = useMemo(
    () => Array.from({ length: Math.max(0, maxYear - minYear + 1) }, (_, index) => maxYear - index),
    [maxYear, minYear],
  );
  const controlClass = "min-h-11 rounded-lg border border-warm-200 bg-white px-3 text-base text-warm-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 disabled:bg-warm-50 disabled:text-warm-400";

  const [viewYear, viewMonth] = view.split('-').map(Number);
  const days = new Date(viewYear, viewMonth, 0).getDate();
  const offset = (new Date(viewYear, viewMonth - 1, 1).getDay() + 6) % 7;
  const move = (by: number) => { const date = new Date(Date.UTC(viewYear, viewMonth - 1 + by, 1)); setView(date.toISOString().slice(0, 7)); };
  return <fieldset className={`min-w-0 ${className}`} disabled={disabled}>
      {label && <legend className="mb-1.5 text-sm font-semibold text-warm-600">{label}{required && <><span aria-hidden="true"> *</span><span className="sr-only"> required</span></>}</legend>}
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
