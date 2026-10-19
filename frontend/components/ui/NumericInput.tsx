"use client";

import React from "react";

interface Props {
  label: string;
  value: string;
  onChange: (value: string) => void;
  unit: string;
  min?: number;
  max?: number;
  step?: number;
  disabled?: boolean;
  prefix?: React.ReactNode;
  unitPlacement?: 'inside' | 'outside';
}

export function numericStepBase(value: string, min = 0, step = 0.1): number {
  const amount = Number(value);
  const remainder = Number((((amount - min) % step + step) % step).toFixed(10));
  return value.trim() && Number.isFinite(amount) && amount >= min && step > 0
    ? Number((min + (remainder >= step ? 0 : remainder)).toFixed(10)) : min;
}

export default function NumericInput({
  label,
  value,
  onChange,
  unit,
  min = 0,
  max,
  step = 0.1,
  disabled = false,
  prefix,
  unitPlacement = 'inside',
}: Props) {
  // Keep calculated precision on the native step grid (1.01 → 1.11).
  const stepBase = numericStepBase(value, min, step);
  return (
    <div>
      <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest mb-1">
        {label}
      </label>
      <div className="flex items-center gap-2">
      <div className="flex min-w-0 flex-1 items-center border border-warm-200 rounded-lg overflow-hidden focus-within:border-brand-green-500 focus-within:ring-2 focus-within:ring-brand-green-500/20 transition-all">
        {prefix}
        <input
          aria-label={label}
          type="number"
          min={stepBase}
          max={max}
          step={step}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          disabled={disabled}
          className="min-w-0 flex-1 w-full px-2.5 py-2 text-base font-numeric text-warm-900 bg-transparent focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
        />
        {unitPlacement === 'inside' && <span className="px-2 text-xs text-warm-400 font-bold bg-warm-50 border-l border-warm-200 whitespace-nowrap">
          {unit}
        </span>}
      </div>
      {unitPlacement === 'outside' && <span className="shrink-0 text-xs text-warm-400">{unit}</span>}
      </div>
    </div>
  );
}
