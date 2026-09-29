"use client";

import { INTERVENTION_GUIDANCE_FIELD_MAX } from "@/lib/interventionGuidance";
import GuidanceCharacterCount from "./GuidanceCharacterCount";

interface Props {
  goals: string; barriers: string; strategies: string;
  onChange: (field: 'counseling_goals' | 'barriers' | 'strategies', val: string) => void;
  onSave: () => void; saving: boolean;
  readOnly?: boolean;
  showSave?: boolean;
  totalCharacters: number;
}

function Area({ label, value, onChange, readOnly }: { label: string; value: string; onChange: (v: string) => void; readOnly: boolean }) {
  return (
    <div className="space-y-1.5">
      <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest">{label}</label>
      <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={4} maxLength={INTERVENTION_GUIDANCE_FIELD_MAX} disabled={readOnly}
        className="w-full px-3.5 py-3 text-base border border-warm-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 disabled:bg-warm-50 disabled:text-warm-700" />
    </div>
  );
}

export default function CounselingTab({ goals, barriers, strategies, onChange, onSave, saving, readOnly = false, showSave = true, totalCharacters }: Props) {
  return (
    <div className="space-y-4">
      <Area label="Behavioral Goals"
        value={goals} onChange={(v) => onChange('counseling_goals', v)} readOnly={readOnly} />
      <Area label="Identified Barriers"
        value={barriers} onChange={(v) => onChange('barriers', v)} readOnly={readOnly} />
      <Area label="Strategies"
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
