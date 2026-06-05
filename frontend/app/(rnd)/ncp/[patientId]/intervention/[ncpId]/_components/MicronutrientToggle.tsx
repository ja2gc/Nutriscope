"use client";

import { useState } from "react";
import { FlaskConical } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Checkbox } from "@/components/ui/checkbox";
import { ALL_MICROS } from "@/lib/nutritionCalculations";

interface Props {
  selected: string[];
  onChange: (keys: string[]) => void;
}

export default function MicronutrientToggle({ selected, onChange }: Props) {
  const [open, setOpen] = useState(false);

  const toggle = (key: string) => {
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
          {ALL_MICROS.map(({ key, label, unit }) => (
            <label key={key} className="flex items-center gap-2.5 cursor-pointer group py-0.5">
              <Checkbox
                checked={selected.includes(key)}
                onCheckedChange={() => toggle(key)}
                className="data-[state=checked]:bg-emerald-600 data-[state=checked]:border-emerald-600"
              />
              <span className="text-xs text-zinc-700 group-hover:text-zinc-900 transition-colors">
                {label}
              </span>
              <span className="text-[9px] text-zinc-400 ml-auto">{unit}</span>
            </label>
          ))}
        </div>
        {selected.length > 0 && (
          <button onClick={() => onChange([])}
            className="mt-3 text-[9px] font-bold text-zinc-400 hover:text-red-500 transition-colors cursor-pointer">
            Clear all
          </button>
        )}
      </PopoverContent>
    </Popover>
  );
}
