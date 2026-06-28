"use client";

import React from "react";

interface Props {
  label: string;
  value: string;
  onChange: (value: string) => void;
  unit: string;
  placeholder?: string;
  min?: number;
  step?: number;
  disabled?: boolean;
}

export default function NumericInput({
  label,
  value,
  onChange,
  unit,
  placeholder,
  min = 0,
  step = 0.1,
  disabled = false,
}: Props) {
  return (
    <div>
      <label className="block text-[9px] font-bold text-warm-400 uppercase tracking-widest mb-1">
        {label}
      </label>
      <div className="flex items-center border border-warm-200 rounded-lg overflow-hidden focus-within:border-brand-green-500 focus-within:ring-2 focus-within:ring-brand-green-500/20 transition-all">
        <input
          type="number"
          min={min}
          step={step}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          disabled={disabled}
          className="w-full px-2.5 py-2 text-sm font-numeric text-warm-900 bg-transparent focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
        />
        <span className="px-2 text-[9px] text-warm-400 font-bold bg-warm-50 border-l border-warm-200 whitespace-nowrap">
          {unit}
        </span>
      </div>
    </div>
  );
}
