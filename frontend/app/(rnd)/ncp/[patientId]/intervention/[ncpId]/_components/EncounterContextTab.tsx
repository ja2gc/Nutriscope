"use client";

interface Props {
  sessionType: string; nextFollowup: string;
  onChange: (field: 'session_type' | 'next_followup_date', val: string) => void;
  onSave: () => void; saving: boolean;
}

export default function EncounterContextTab({ sessionType, nextFollowup, onChange, onSave, saving }: Props) {
  return (
    <div className="space-y-5 max-w-md">
      <div className="space-y-1.5">
        <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest">Session Type</label>
        <select value={sessionType} onChange={(e) => onChange('session_type', e.target.value)}
          className="w-full px-3.5 py-2.5 text-base border border-warm-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white cursor-pointer">
          <option value="">Select…</option>
          <option value="Initial Consultation">Initial Consultation</option>
          <option value="Follow-up">Follow-up</option>
        </select>
      </div>
      <div className="space-y-1.5">
        <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest">Next Follow-up Date</label>
        <input type="date" value={nextFollowup} onChange={(e) => onChange('next_followup_date', e.target.value)}
          className="w-full px-3.5 py-2.5 text-base border border-warm-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
      </div>
      <button onClick={onSave} disabled={saving}
        className="px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
        {saving ? "Saving…" : "Save Encounter"}
      </button>
    </div>
  );
}
