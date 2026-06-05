"use client";

interface Props { value: string; onChange: (v: string) => void; onSave: () => void; saving: boolean }

export default function EducationTab({ value, onChange, onSave, saving }: Props) {
  return (
    <div className="space-y-4">
      <p className="text-[10px] text-zinc-400 leading-relaxed">
        Record educational topics, handouts given, and key instructions discussed with the patient.
      </p>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={10}
        placeholder="e.g. Discussed importance of low-sodium diet. Provided handout on renal diet food choices. Reviewed portion sizes..."
        className="w-full px-3.5 py-3 text-sm border border-zinc-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
      />
      <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Notes"}
        </button>
      </div>
    </div>
  );
}
