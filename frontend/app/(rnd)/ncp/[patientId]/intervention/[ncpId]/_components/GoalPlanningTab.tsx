import { ALL_MICROS, microKeys } from "@/lib/nutritionCalculations";

interface Props {
  goals: string; energy: string; protein: string; carbs: string; fat: string;
  /** Micros displayed on the prescription for the active goal. */
  displayedMicros?: string[];
  /** Prescribed micro limits keyed by micro key. */
  micronutrientLimits?: Record<string, { max?: number; min?: number; unit: string }>;
}

export default function GoalPlanningTab({
  goals, energy, protein, carbs, fat,
  displayedMicros = [], micronutrientLimits = {},
}: Props) {
  // Only real micros, and only those carrying a prescribed target — these mirror
  // the micros shown during prescription creation for this goal.
  const microRows = microKeys(displayedMicros)
    .map((key) => {
      const meta = ALL_MICROS.find((m) => m.key === key);
      const limit = micronutrientLimits[key];
      if (!meta || !limit) return null;
      const hasMax = limit.max != null;
      const hasMin = limit.min != null;
      if (!hasMax && !hasMin) return null;
      const value = hasMax ? `≤ ${limit.max}` : `≥ ${limit.min}`;
      return { key, label: meta.label, value, unit: limit.unit || meta.unit };
    })
    .filter((r): r is { key: string; label: string; value: string; unit: string } => r !== null);

  return (
    <div className="space-y-5">
      <p className="text-xs text-warm-400">Links behavioral counseling goals to measurable nutrient targets.</p>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[['Energy', energy, 'kcal'], ['Protein', protein, 'g'], ['Carbs', carbs, 'g'], ['Fat', fat, 'g']].map(([label, val, unit]) => (
          <div key={label} className="bg-emerald-50 border border-emerald-200 p-3 rounded-xl text-center">
            <p className="text-xs font-bold text-emerald-600 uppercase tracking-wider">{label} Target</p>
            <p className="text-lg font-extrabold font-mono text-warm-900 mt-1">
              {val || '—'}
              <span className="text-xs font-normal text-warm-500 ml-0.5">{unit}</span>
            </p>
          </div>
        ))}
      </div>

      {microRows.length > 0 && (
        <div className="space-y-2">
          <p className="text-xs font-bold text-warm-400 uppercase tracking-widest">Micronutrient Targets</p>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            {microRows.map(({ key, label, value, unit }) => (
              <div key={key} className="bg-sky-50 border border-sky-200 p-3 rounded-xl text-center">
                <p className="text-xs font-bold text-sky-600 uppercase tracking-wider">{label}</p>
                <p className="text-base font-extrabold font-mono text-warm-900 mt-1">
                  {value}
                  <span className="text-xs font-normal text-warm-500 ml-0.5">{unit}</span>
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {goals ? (
        <div className="bg-white border border-warm-200 rounded-xl p-4 space-y-2">
          <p className="text-xs font-bold text-warm-400 uppercase tracking-widest">Behavioral Goals</p>
          <p className="text-base text-warm-700 leading-relaxed whitespace-pre-wrap">{goals}</p>
        </div>
      ) : (
        <p className="text-sm text-warm-400 italic">No counseling goals set. Add them in the Counseling tab.</p>
      )}
    </div>
  );
}
