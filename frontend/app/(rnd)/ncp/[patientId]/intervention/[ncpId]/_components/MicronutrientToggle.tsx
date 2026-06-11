"use client";

import { useState } from "react";
import { FlaskConical, Lock } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Checkbox } from "@/components/ui/checkbox";
import { ALL_MICROS } from "@/lib/nutritionCalculations";

interface Props {
  selected: string[];
  onChange: (keys: string[]) => void;
  /** Goal-required micros — always checked and locked (can't be removed). */
  required?: string[];
}

export default function MicronutrientToggle({ selected, onChange, required = [] }: Props) {
  const [open, setOpen] = useState(false);

  const toggle = (key: string) => {
    if (required.includes(key)) return; // locked by the intervention goal
    onChange(
      selected.includes(key) ? selected.filter((k) => k !== key) : [...selected, key]
    );
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-zinc-500 border border-zinc-200 rounded-lg hover:border-emerald-400 hover:text-emerald-700 transition-colors cursor-pointer">
          <FlaskConical className="h-3 w-3" />
          Display Micros
          {selected.length > 0 && (
            <span className="ml-1 bg-emerald-100 text-emerald-700 rounded-full px-1.5 py-0.5 text-[9px] font-bold">
              {selected.length}
            </span>
          )}
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-3" align="start">
        <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-2">
          Choose micronutrients to display
        </p>
        <div className="space-y-1.5 max-h-64 overflow-y-auto">
          {ALL_MICROS.map(({ key, label, unit }) => {
            const isRequired = required.includes(key);
            return (
              <label key={key}
                title={isRequired ? "Required by the intervention goal — can't be removed." : undefined}
                className={`flex items-center gap-2.5 group py-0.5 ${isRequired ? "cursor-not-allowed" : "cursor-pointer"}`}>
                <Checkbox
                  checked={selected.includes(key) || isRequired}
                  disabled={isRequired}
                  onCheckedChange={() => toggle(key)}
                  className="data-[state=checked]:bg-emerald-600 data-[state=checked]:border-emerald-600"
                />
                <span className="text-xs text-zinc-700 group-hover:text-zinc-900 transition-colors">
                  {label}
                </span>
                {isRequired && <Lock className="h-2.5 w-2.5 text-zinc-300" />}
                <span className="text-[9px] text-zinc-400 ml-auto">{unit}</span>
              </label>
            );
          })}
        </div>
        {selected.some((k) => !required.includes(k)) && (
          <button onClick={() => onChange(selected.filter((k) => required.includes(k)))}
            className="mt-3 text-[9px] font-bold text-zinc-400 hover:text-red-500 transition-colors cursor-pointer">
            Clear all {required.length > 0 && "(keeps required)"}
          </button>
        )}
      </PopoverContent>
    </Popover>
  );
}
