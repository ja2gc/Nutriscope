"use client";

import { TrendingUp } from "lucide-react";
import TrendSparkline from "./TrendSparkline";
import {
  MonitoringEntry,
  MonitoringLabValues,
  LAB_REFERENCE_RANGES,
  getLabStatus,
  getWeightStatus,
  GoalStatus,
} from "@/services/monitoringService";
import type { MonitoringPlan } from "@/services/monitoringPlan";

interface BiochemicalBaseline {
  albumin?: number | null;
  hba1c?: number | null;
  ldl?: number | null;
  cholesterol?: number | null;
  creatinine?: number | null;
  potassium?: number | null;
  phosphate?: number | null;
  magnesium?: number | null;
  hemoglobin?: number | null;
  glucose?: number | null;
}

interface GoalProgressTrackerProps {
  plan?: MonitoringPlan | null;
  entries: MonitoringEntry[];
  baselineWeight: number | null;
  baselineBmi: number | null;
  baselineLabs: BiochemicalBaseline | null;
  nutritionalStatus: string | null;
}

function referenceLabelFor(reference: { min?: number | null; max?: number | null } | null, target: number | null, unit: string): string {
  if (target != null) return `target ${target}${unit ? ` ${unit}` : ""}`;
  if (!reference) return "—";
  const { min, max } = reference;
  if (min != null && max != null) return `${min}–${max}`;
  if (min != null) return `≥${min}`;
  if (max != null) return `≤${max}`;
  return "—";
}

/** Plan-driven tracker: one row per patient-specific tracked indicator (Visit 1 → latest). */
function PlanProgressTable({ plan }: { plan: MonitoringPlan }) {
  return (
    <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-hidden">
      <div className="px-5 py-4 border-b border-warm-100">
        <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2">
          <TrendingUp className="h-4 w-4 text-emerald-600" />
          Goal Progress
        </h3>
        <p className="text-xs text-warm-400 mt-0.5">
          Visit 1 = assessment baseline · only this patient&apos;s tracked indicators
        </p>
      </div>
      <div className="overflow-x-auto">
        <div className="min-w-[560px]">
          <div className="grid grid-cols-[1.4fr_72px_72px_90px_90px_80px] gap-2 px-5 py-2.5 bg-warm-50 border-b border-warm-100">
            {["Indicator", "Baseline", "Current", "Reference", "Status", "Trend"].map((h) => (
              <span key={h} className="text-xs font-bold text-warm-400 uppercase tracking-widest">{h}</span>
            ))}
          </div>
          <div className="divide-y divide-zinc-100">
            {plan.indicators.map((ind) => {
              const values = ind.series.map((s) => s.value).filter((v): v is number => v !== null);
              const baseline = ind.series[0]?.value ?? null;
              const current = values.length > 0 ? values[values.length - 1] : null;
              return (
                <TrackerRow
                  key={ind.key}
                  label={ind.label}
                  unit={ind.unit}
                  baseline={baseline}
                  current={current}
                  referenceLabel={referenceLabelFor(ind.reference, ind.target, ind.unit)}
                  status={ind.latest_status}
                  sparkValues={values}
                  lowerIsBetter={ind.reference?.lowerIsBetter ?? false}
                />
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

const STATUS_STYLES: Record<GoalStatus, string> = {
  met:         'bg-emerald-50 text-emerald-700 border border-emerald-200',
  in_progress: 'bg-amber-50 text-amber-700 border border-amber-200',
  not_met:     'bg-red-50 text-red-700 border border-red-200',
  no_data:     'bg-warm-50 text-warm-400 border border-warm-200',
};

const STATUS_LABELS: Record<GoalStatus, string> = {
  met:         'Met',
  in_progress: 'In Progress',
  not_met:     'Not Met',
  no_data:     '—',
};

type LabKey = keyof MonitoringLabValues & keyof BiochemicalBaseline;
const LAB_KEYS: LabKey[] = [
  'albumin', 'hba1c', 'ldl', 'cholesterol',
  'creatinine', 'potassium', 'phosphate', 'magnesium', 'hemoglobin', 'glucose',
];

export default function GoalProgressTracker({
  plan,
  entries,
  baselineWeight,
  baselineBmi,
  baselineLabs,
  nutritionalStatus,
}: GoalProgressTrackerProps) {
  // Plan-driven: render the patient-specific tracked set when available.
  if (plan && plan.indicators.length > 0) {
    return <PlanProgressTable plan={plan} />;
  }

  // Sort oldest-first for sparklines
  const sorted = [...entries].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
  );
  const latestEntry = sorted[sorted.length - 1] ?? null;

  const weightValues = sorted
    .map((e) => (e.weight ? Number(e.weight) : null))
    .filter((v): v is number => v !== null);

  const bmiValues = sorted
    .map((e) => (e.bmi ? Number(e.bmi) : null))
    .filter((v): v is number => v !== null);

  // Only build rows for labs that have baseline data OR have been logged at least once
  const labRows = LAB_KEYS
    .map((key) => {
      const baseline = (baselineLabs?.[key] as number | null | undefined) ?? null;
      const allValues = sorted
        .map((e) => e.lab_values?.[key])
        .filter((v): v is number => v !== null && v !== undefined && typeof v === 'number');
      const current = allValues[allValues.length - 1] ?? null;
      if (baseline === null && allValues.length === 0) return null;
      return { key, baseline, current, allValues };
    })
    .filter(Boolean) as Array<{ key: LabKey; baseline: number | null; current: number | null; allValues: number[] }>;

  const currentWeight = latestEntry?.weight ? Number(latestEntry.weight) : null;
  const currentBmi = latestEntry?.bmi ? Number(latestEntry.bmi) : null;

  const hasAnyData =
    baselineWeight !== null || baselineBmi !== null ||
    currentWeight !== null || labRows.length > 0;

  if (!hasAnyData) {
    return (
      <div className="bg-white border border-warm-200 rounded-2xl shadow-sm">
        <div className="px-5 py-4 border-b border-warm-100">
          <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2">
            <TrendingUp className="h-4 w-4 text-emerald-600" />
            Goal Progress
          </h3>
        </div>
        <div className="p-10 text-center">
          <div className="p-3 bg-warm-50 border border-dashed border-warm-300 rounded-xl w-fit mx-auto mb-3">
            <TrendingUp className="h-6 w-6 text-warm-400" />
          </div>
          <p className="text-sm font-semibold text-warm-500">No assessment baseline found.</p>
          <p className="text-xs text-warm-400 mt-1">
            Complete the assessment step to enable goal tracking.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-hidden">
      <div className="px-5 py-4 border-b border-warm-100">
        <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2">
          <TrendingUp className="h-4 w-4 text-emerald-600" />
          Goal Progress
        </h3>
        <p className="text-xs text-warm-400 mt-0.5">
          Baseline from initial assessment · Status based on clinical reference ranges
        </p>
      </div>

      {/* Horizontally scrollable on small screens */}
      <div className="overflow-x-auto">
        <div className="min-w-[560px]">
          {/* Column headers */}
          <div className="grid grid-cols-[1.4fr_72px_72px_90px_90px_80px] gap-2 px-5 py-2.5 bg-warm-50 border-b border-warm-100">
            {['Indicator', 'Baseline', 'Current', 'Reference', 'Status', 'Trend'].map((h) => (
              <span key={h} className="text-xs font-bold text-warm-400 uppercase tracking-widest">{h}</span>
            ))}
          </div>

          <div className="divide-y divide-zinc-100">
            {/* Weight row */}
            {(baselineWeight !== null || currentWeight !== null) && (
              <TrackerRow
                label="Weight"
                unit="kg"
                baseline={baselineWeight}
                current={currentWeight}
                referenceLabel="IBW target"
                status={
                  currentWeight !== null
                    ? getWeightStatus(currentWeight, baselineWeight, nutritionalStatus)
                    : 'no_data'
                }
                sparkValues={weightValues}
                lowerIsBetter={false}
              />
            )}

            {/* BMI row */}
            {(baselineBmi !== null || currentBmi !== null) && (
              <TrackerRow
                label="BMI"
                unit="kg/m²"
                baseline={baselineBmi}
                current={currentBmi}
                referenceLabel="18.5–24.9"
                status={
                  currentBmi !== null
                    ? currentBmi >= 18.5 && currentBmi <= 24.9
                      ? 'met'
                      : baselineBmi !== null && Math.abs(currentBmi - 22) < Math.abs(baselineBmi - 22)
                        ? 'in_progress'
                        : 'not_met'
                    : 'no_data'
                }
                sparkValues={bmiValues}
                lowerIsBetter={false}
              />
            )}

            {/* Lab rows — only rendered when baseline or monitoring data exists */}
            {labRows.map(({ key, baseline, current, allValues }) => {
              const ref = LAB_REFERENCE_RANGES[key];
              const refLabel =
                ref.min !== undefined && ref.max !== undefined
                  ? `${ref.min}–${ref.max}`
                  : ref.min !== undefined
                    ? `≥${ref.min}`
                    : ref.max !== undefined
                      ? `≤${ref.max}`
                      : '—';

              return (
                <TrackerRow
                  key={key}
                  label={ref.label}
                  unit={ref.unit}
                  baseline={baseline}
                  current={current}
                  referenceLabel={refLabel}
                  status={current !== null ? getLabStatus(key, current, baseline) : 'no_data'}
                  sparkValues={allValues}
                  lowerIsBetter={ref.lowerIsBetter ?? false}
                />
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

// ─── Tracker Row ──────────────────────────────────────────────────────────────

interface TrackerRowProps {
  label: string;
  unit: string;
  baseline: number | null;
  current: number | null;
  referenceLabel: string;
  status: GoalStatus;
  sparkValues: number[];
  lowerIsBetter: boolean;
}

function TrackerRow({
  label, unit, baseline, current,
  referenceLabel, status, sparkValues, lowerIsBetter,
}: TrackerRowProps) {
  return (
    <div className="grid grid-cols-[1.4fr_72px_72px_90px_90px_80px] gap-2 items-center px-5 py-3">
      {/* Label + unit */}
      <div className="min-w-0">
        <p className="text-sm font-semibold text-warm-800 truncate">{label}</p>
        <p className="text-xs text-warm-400">{unit}</p>
      </div>

      {/* Baseline */}
      <p className="text-sm font-mono text-warm-500 truncate">
        {baseline !== null ? baseline : <span className="text-warm-300">—</span>}
      </p>

      {/* Current */}
      <p className={`text-sm font-mono font-bold truncate ${current !== null ? 'text-warm-900' : 'text-warm-300'}`}>
        {current !== null ? current : '—'}
      </p>

      {/* Reference range */}
      <p className="text-xs text-warm-400 truncate">{referenceLabel}</p>

      {/* Status chip */}
      <span className={`inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap ${STATUS_STYLES[status]}`}>
        {STATUS_LABELS[status]}
      </span>

      {/* Sparkline */}
      <div className="flex items-center">
        {sparkValues.length >= 2
          ? <TrendSparkline values={sparkValues} lowerIsBetter={lowerIsBetter} />
          : <span className="text-warm-300 text-xs">—</span>
        }
      </div>
    </div>
  );
}
