"use client";

import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";

export default function DiagnosisOtherField({ id, label, checked, value, onToggle, onChange, maxLength = 75 }: {
  id: string; label: string; checked: boolean; value: string;
  onToggle: (checked: boolean) => void; onChange: (value: string) => void;
  maxLength?: number;
}) {
  const fieldId = `${id}-text`;
  const fieldLabel = `Specify ${label.toLowerCase()}`;
  return (
    <div className="space-y-2">
      <label className="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-1.5 text-xs text-warm-700 hover:bg-warm-50">
        <input type="checkbox" checked={checked} aria-label={label} aria-controls={fieldId} aria-expanded={checked}
          onChange={event => onToggle(event.target.checked)} className="shrink-0 accent-emerald-600" />
        <span>Other</span>
      </label>
      {checked && <div className="space-y-1.5 pl-1.5">
        <label htmlFor={fieldId} className="block text-xs font-semibold text-warm-600">{fieldLabel}</label>
        <CharacterCountTextarea id={fieldId} aria-label={fieldLabel} value={value} onChange={onChange} maxLength={maxLength} required rows={2}
          className="w-full resize-none rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm text-warm-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20" />
      </div>}
    </div>
  );
}
