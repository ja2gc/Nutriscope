"use client";

import { FlaskConical } from "lucide-react";
import MultiSelectPopover from "@/components/ui/MultiSelectPopover";
import { ALL_MICROS } from "@/lib/nutritionCalculations";

interface Props {
  selected: string[];
  onChange: (keys: string[]) => void;
  required?: string[];
}

export default function MicronutrientToggle({ selected, onChange, required = [] }: Props) {
  const items = ALL_MICROS.map(({ key, label, unit }) => ({ key, label, unit }));

  return (
    <MultiSelectPopover
      items={items}
      selected={selected}
      onChange={onChange}
      required={required}
      triggerLabel="Display Micros"
      triggerIcon={<FlaskConical className="h-3 w-3" />}
      title="Choose micronutrients to display"
      width="w-72"
      showCount
    />
  );
}
