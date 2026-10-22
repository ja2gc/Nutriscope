"use client";

import { INTERVENTION_GUIDANCE_FIELD_MAX } from "@/lib/interventionGuidance";
import GuidanceCharacterCount from "./GuidanceCharacterCount";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import GuidanceSuggestion from "./GuidanceSuggestion";

interface Props {
  value: string;
  suggestion?: string;
  onChange: (v: string) => void;
  onSave: () => void;
  saving: boolean;
  readOnly?: boolean;
  showSave?: boolean;
  totalCharacters: number;
  malnutritionNote?: boolean;
  onMalnutritionNoteChange?: (checked: boolean) => void;
}

export default function EducationTab({ value, suggestion, onChange, onSave, saving, readOnly = false, showSave = true, totalCharacters, malnutritionNote, onMalnutritionNoteChange }: Props) {
  return (
    <div className="space-y-4">
      {!readOnly && onMalnutritionNoteChange && <div className="space-y-1">
        <label className="flex items-center gap-2 text-sm text-warm-700">
          <input type="checkbox" aria-label="Add malnutrition education note (notes only)" checked={malnutritionNote ?? false}
            onChange={event => onMalnutritionNoteChange(event.target.checked)} />
          Include assessed malnutrition guidance in suggestion
        </label>
        <p className="text-xs text-warm-500">Changes the suggestion only; notes and targets stay unchanged.</p>
      </div>}
      <CharacterCountTextarea
        value={value}
        onChange={onChange}
        rows={10}
        maxLength={INTERVENTION_GUIDANCE_FIELD_MAX}
        disabled={readOnly}
        aria-label="Education notes"
        className="w-full resize-none rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm text-warm-900 transition-colors placeholder:text-warm-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:bg-warm-50 disabled:text-warm-700"
      />
      {!readOnly && <GuidanceSuggestion label="Education" text={suggestion} />}
      <GuidanceCharacterCount total={totalCharacters} />
      {showSave && !readOnly && <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Notes"}
        </button>
      </div>}
    </div>
  );
}
