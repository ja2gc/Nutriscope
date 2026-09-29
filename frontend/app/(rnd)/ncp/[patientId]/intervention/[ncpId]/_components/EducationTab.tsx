"use client";

interface Props {
  value: string;
  onChange: (v: string) => void;
  onSave: () => void;
  saving: boolean;
  readOnly?: boolean;
  showSave?: boolean;
}

export default function EducationTab({ value, onChange, onSave, saving, readOnly = false, showSave = true }: Props) {
  return (
    <div className="space-y-4">
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={10}
        disabled={readOnly}
        aria-label="Education notes"
        className="w-full px-3.5 py-3 text-base border border-warm-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 disabled:bg-warm-50 disabled:text-warm-700"
      />
      {showSave && !readOnly && <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Notes"}
        </button>
      </div>}
    </div>
  );
}
