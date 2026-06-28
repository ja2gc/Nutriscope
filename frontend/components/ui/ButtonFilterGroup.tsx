"use client";

import React from "react";

interface FilterOption {
  value: string;
  label: string;
}

interface Props {
  options: FilterOption[];
  value: string;
  onChange: (value: string) => void;
  label?: string;
  size?: "sm" | "md";
}

export default function ButtonFilterGroup({
  options,
  value,
  onChange,
  label,
  size = "md",
}: Props) {
  const sizeClass =
    size === "sm"
      ? "px-2.5 py-1 text-[9px]"
      : "px-3 py-1.5 text-[10px]";

  return (
    <div className="flex flex-wrap items-center gap-2">
      {label && (
        <span className="text-[10px] font-bold text-warm-500 uppercase tracking-wider">
          {label}
        </span>
      )}
      {options.map((opt) => (
        <button
          key={opt.value}
          type="button"
          onClick={() => onChange(opt.value)}
          className={`${sizeClass} font-bold uppercase tracking-wider rounded-lg border transition-all cursor-pointer ${
            value === opt.value
              ? "bg-forest-900 text-white border-forest-900"
              : "bg-white text-warm-500 border-warm-200 hover:border-warm-400"
          }`}
        >
          {opt.label}
        </button>
      ))}
    </div>
  );
}
