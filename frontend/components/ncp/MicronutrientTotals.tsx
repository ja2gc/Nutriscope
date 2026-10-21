import { ALL_MICROS } from '@/lib/nutritionCalculations';
import { formatMealPlanAmount } from '@/lib/mealPlanNutrition';

export type MicronutrientLimits = Record<string, { target?: number; min?: number; max?: number; unit: string }>;

export function MicronutrientTotals({ title, selected, totals, limits = {} }: {
  title: string; selected: string[]; totals: Record<string, number | undefined>; limits?: MicronutrientLimits;
}) {
  if (!selected.length) return null;
  return <div className="space-y-2 rounded-xl border border-warm-200 bg-white p-3">
    <p className="text-xs font-bold uppercase tracking-widest text-warm-500">{title}</p>
    <div className="grid gap-2" style={{ gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,180px),1fr))' }}>{selected.map(key => {
      const meta = ALL_MICROS.find(entry => entry.key === key), value = totals[key], limit = limits[key];
      const over = value != null && limit?.max != null && value > limit.max;
      const under = value != null && limit?.min != null && value < limit.min;
      return <div key={key} className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 rounded-lg border border-warm-200 bg-white px-2.5 py-1.5 text-xs">
        <span className="font-semibold text-warm-700">{meta?.label ?? key}</span>
        <span className="font-mono font-bold text-warm-600">
          {value == null ? 'Not recorded' : <>{formatMealPlanAmount(value)} {meta?.unit ?? limit?.unit}</>}
          {value != null && limit?.target != null && <span className="font-normal text-warm-500"> / {limit.target}</span>}
          {value != null && limit?.min != null && <span className="font-normal text-warm-500"> / ≥{limit.min}</span>}
          {value != null && limit?.max != null && <span className="font-normal text-warm-500"> / ≤{limit.max}</span>}
          {over && <span className="ml-1 font-normal">Above limit</span>}{under && <span className="ml-1 font-normal">Below minimum</span>}
        </span>
      </div>;
    })}</div>
  </div>;
}
