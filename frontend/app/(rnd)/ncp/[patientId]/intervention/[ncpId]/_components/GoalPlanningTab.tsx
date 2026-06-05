interface Props {
  goals: string; energy: string; protein: string; carbs: string; fat: string;
}

export default function GoalPlanningTab({ goals, energy, protein, carbs, fat }: Props) {
  return (
    <div className="space-y-5">
      <p className="text-[10px] text-zinc-400">Links behavioral counseling goals to measurable nutrient targets.</p>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[['Energy', energy, 'kcal'], ['Protein', protein, 'g'], ['Carbs', carbs, 'g'], ['Fat', fat, 'g']].map(([label, val, unit]) => (
          <div key={label} className="bg-emerald-50 border border-emerald-200 p-3 rounded-xl text-center">
            <p className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label} Target</p>
            <p className="text-lg font-extrabold font-mono text-zinc-900 mt-1">
              {val || '—'}
              <span className="text-[9px] font-normal text-zinc-500 ml-0.5">{unit}</span>
            </p>
          </div>
        ))}
      </div>
      {goals ? (
        <div className="bg-white border border-zinc-200 rounded-xl p-4 space-y-2">
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Behavioral Goals</p>
          <p className="text-sm text-zinc-700 leading-relaxed whitespace-pre-wrap">{goals}</p>
        </div>
      ) : (
        <p className="text-xs text-zinc-400 italic">No counseling goals set. Add them in the Counseling tab.</p>
      )}
    </div>
  );
}
