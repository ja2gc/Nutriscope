"use client";

import React, { useState } from "react";
import { Lock } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Checkbox } from "@/components/ui/checkbox";

interface Item {
  key: string;
  label: string;
  unit?: string;
}

interface Props {
  items: Item[];
  selected: string[];
  onChange: (keys: string[]) => void;
  required?: string[];
  triggerLabel: string;
  triggerIcon?: React.ReactNode;
  title?: string;
  description?: string;
  width?: string;
  showCount?: boolean;
}

export default function MultiSelectPopover({
  items,
  selected,
  onChange,
  required = [],
  triggerLabel,
  triggerIcon,
  title,
  description,
  width = "w-72",
  showCount = true,
}: Props) {
  const [open, setOpen] = useState(false);

  const toggle = (key: string) => {
    if (required.includes(key)) return;
    onChange(
      selected.includes(key)
        ? selected.filter((k) => k !== key)
        : [...selected, key]
    );
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-warm-500 border border-warm-200 rounded-lg hover:border-brand-green-500 hover:text-brand-green-700 transition-colors cursor-pointer">
          {triggerIcon}
          {triggerLabel}
          {showCount && selected.length > 0 && (
            <span className="ml-1 bg-brand-green-100 text-brand-green-700 rounded-full px-1.5 py-0.5 text-xs font-bold">
              {selected.length}
            </span>
          )}
        </button>
      </PopoverTrigger>
      <PopoverContent className={`${width} p-3`} align="start">
        {(title || description) && (
          <div className="mb-3 space-y-1">
            {title && (
              <p className="text-xs font-bold text-warm-700 uppercase tracking-widest">
                {title}
              </p>
            )}
            {description && (
              <p className="text-xs text-warm-400 leading-relaxed">
                {description}
              </p>
            )}
          </div>
        )}
        <div className="space-y-1.5 max-h-64 overflow-y-auto">
          {items.map(({ key, label, unit }) => {
            const isRequired = required.includes(key);
            return (
              <label
                key={key}
                title={
                  isRequired
                    ? "Required — can't be removed."
                    : undefined
                }
                className={`flex items-center gap-2.5 group py-0.5 ${
                  isRequired ? "cursor-not-allowed" : "cursor-pointer"
                }`}
              >
                <Checkbox
                  checked={selected.includes(key) || isRequired}
                  disabled={isRequired}
                  onCheckedChange={() => toggle(key)}
                  className="data-[state=checked]:bg-brand-green-600 data-[state=checked]:border-brand-green-600"
                />
                <span className="text-sm text-warm-700 group-hover:text-warm-900 transition-colors flex-1">
                  {label}
                </span>
                {isRequired && (
                  <Lock className="h-2.5 w-2.5 text-warm-300 shrink-0" />
                )}
                {unit && (
                  <span className="text-xs text-warm-400 shrink-0">
                    {unit}
                  </span>
                )}
              </label>
            );
          })}
        </div>
        {selected.some((k) => !required.includes(k)) && (
          <button
            onClick={() =>
              onChange(selected.filter((k) => required.includes(k)))
            }
            className="mt-3 text-xs font-bold text-warm-400 hover:text-red-500 transition-colors cursor-pointer"
          >
            Clear all {required.length > 0 && "(keeps required)"}
          </button>
        )}
      </PopoverContent>
    </Popover>
  );
}
