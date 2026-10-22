"use client";

import { INTERVENTION_GUIDANCE_FIELD_MAX } from "@/lib/interventionGuidance";
import GuidanceCharacterCount from "./GuidanceCharacterCount";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import GuidanceSuggestion from "./GuidanceSuggestion";

interface Props {
  goals: string; barriers: string; strategies: string;
  goalsSuggestion?: string; strategiesSuggestion?: string;
  onChange: (field: 'counseling_goals' | 'barriers' | 'strategies', val: string) => void;
  onSave: () => void; saving: boolean;
  readOnly?: boolean;
  showSave?: boolean;
  totalCharacters: number;
  refeedingNote?: boolean;
  onRefeedingNoteChange?: (checked: boolean) => void;
}

function Area({ label, value, onChange, readOnly, suggestion }: { label: string; value: string; onChange: (v: string) => void; readOnly: boolean; suggestion?: string }) {
  return (
    <div className="space-y-1.5">
      <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest">{label}</label>
      <CharacterCountTextarea value={value} onChange={onChange} rows={4} maxLength={INTERVENTION_GUIDANCE_FIELD_MAX} disabled={readOnly} aria-label={label}
        className="w-full resize-none rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm text-warm-900 transition-colors placeholder:text-warm-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:bg-warm-50 disabled:text-warm-700" />
      {!readOnly && <GuidanceSuggestion label={label} text={suggestion} />}
    </div>
  );
}

export default function CounselingTab({ goals, barriers, strategies, goalsSuggestion, strategiesSuggestion, onChange, onSave, saving, readOnly = false, showSave = true, totalCharacters, refeedingNote, onRefeedingNoteChange }: Props) {
  return (
    <div className="space-y-4">
      <Area label="Behavioral Goals" suggestion={goalsSuggestion}
        value={goals} onChange={(v) => onChange('counseling_goals', v)} readOnly={readOnly} />
      <Area label="Identified Barriers"
        value={barriers} onChange={(v) => onChange('barriers', v)} readOnly={readOnly} />
      {!readOnly && onRefeedingNoteChange && <div className="space-y-1">
        <label className="flex items-center gap-2 text-sm text-warm-700">
          <input type="checkbox" aria-label="Add refeeding-risk strategy note (notes only)" checked={refeedingNote ?? false}
            onChange={event => onRefeedingNoteChange(event.target.checked)} />
          Include assessed refeeding-risk guidance in suggestion
        </label>
        <p className="text-xs text-warm-500">Changes the suggestion only; notes and targets stay unchanged.</p>
      </div>}
      <Area label="Strategies" suggestion={strategiesSuggestion}
        value={strategies} onChange={(v) => onChange('strategies', v)} readOnly={readOnly} />
      <GuidanceCharacterCount total={totalCharacters} />
      {showSave && !readOnly && <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Counseling"}
        </button>
      </div>}
    </div>
  );
}
