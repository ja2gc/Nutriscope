"use client";

interface Props {
  goals: string; barriers: string; strategies: string;
  onChange: (field: 'counseling_goals' | 'barriers' | 'strategies', val: string) => void;
  onSave: () => void; saving: boolean;
}

function Area({ label, hint, value, onChange }: { label: string; hint: string; value: string; onChange: (v: string) => void }) {
  return (
    <div className="space-y-1.5">
      <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest">{label}</label>
      <p className="text-xs text-warm-300">{hint}</p>
      <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={4}
        className="w-full px-3.5 py-3 text-base border border-warm-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
    </div>
  );
}

export default function CounselingTab({ goals, barriers, strategies, onChange, onSave, saving }: Props) {
  return (
    <div className="space-y-4">
      <Area label="Behavioral Goals" hint="Specific, measurable nutrition goals agreed with the patient."
        value={goals} onChange={(v) => onChange('counseling_goals', v)} />
      <Area label="Identified Barriers" hint="Financial, cultural, lifestyle, or knowledge barriers to adherence."
        value={barriers} onChange={(v) => onChange('barriers', v)} />
      <Area label="Strategies" hint="Motivational approaches and action steps to improve adherence."
        value={strategies} onChange={(v) => onChange('strategies', v)} />
      <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Counseling"}
        </button>
      </div>
    </div>
  );
}
