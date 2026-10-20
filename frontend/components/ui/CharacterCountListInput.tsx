"use client";

import { CharacterCountTextarea } from "./CharacterCountTextarea";

/** Small editable lists share the same per-entry counter and input behavior. */
export function CharacterCountListInput({ value, onChange, maxLength, maxEntries, label, readOnly = false }: {
  value: string[];
  onChange: (value: string[]) => void;
  maxLength: number;
  maxEntries?: number;
  label: string;
  readOnly?: boolean;
}) {
  return <div className="min-w-0 space-y-2">
    {value.map((entry, index) => <div key={index} className="flex items-start gap-1">
      <div className="min-w-0 flex-1"><CharacterCountTextarea disabled={readOnly} value={entry} onChange={(next) => onChange(value.map((item, i) => i === index ? next : item))} maxLength={maxLength} rows={1} autoGrow aria-label={`${label} ${index + 1}`} className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" /></div>
      {!readOnly && <button type="button" aria-label={`Remove ${label.toLowerCase()} ${index + 1}`} onClick={() => onChange(value.filter((_, i) => i !== index))} className="min-h-11 min-w-11 rounded-lg text-warm-500 hover:bg-warm-50">×</button>}
    </div>)}
    {readOnly && value.length === 0 && <p className="py-2.5 text-sm text-warm-500">Not recorded</p>}
    {!readOnly && (maxEntries == null || value.length < maxEntries) && <button type="button" onClick={() => onChange([...value, ""])} className="min-h-11 rounded-lg border border-warm-200 px-3 text-sm font-semibold text-warm-600 hover:bg-warm-50">Add {label.toLowerCase()}</button>}
    {maxEntries != null && <p className="text-xs font-normal text-warm-400">{value.length}/{maxEntries} entries</p>}
  </div>;
}
