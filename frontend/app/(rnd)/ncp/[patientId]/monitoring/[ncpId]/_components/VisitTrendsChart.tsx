"use client";

import React, { useMemo } from "react";
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  ReferenceLine,
  Legend,
} from "recharts";
import {
  MonitoringEntry,
  GOAL_LAB_FLAGS,
  CLINICAL_LAB_META,
  LAB_REFERENCE_RANGES,
  type ClinicalLabKey,
} from "@/services/monitoringService";
import type { Intervention } from "@/services/interventionService";
import { GOAL_MICRO_FLAGS, ALL_MICROS } from "@/lib/nutritionCalculations";
import type { MonitoringPlan, PlanIndicator } from "@/services/monitoringPlan";
import { planToChartSeries } from "@/services/monitoringPlan";

// ─── Types ────────────────────────────────────────────────────────────────────

interface VisitTrendsChartProps {
  plan?: MonitoringPlan | null;
  entries: MonitoringEntry[];
  intervention: Intervention | null;
}

// ─── Plan-driven indicator chart (Visit 1 baseline → follow-ups) ───────────────

const PLAN_COLORS: Record<string, string> = {
  lab: "#059669",
  anthro: "#2563eb",
  intake: "#d97706",
};

function PlanIndicatorChart({ indicator }: { indicator: PlanIndicator }) {
  const points = planToChartSeries(indicator);
  const hasData = points.some((p) => p.value !== null);
  if (!hasData) return null;

  const color = PLAN_COLORS[indicator.category] ?? "#059669";
  const ref = indicator.reference;

  return (
    <div>
      <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5">
        {indicator.label}{" "}
        <span className="normal-case font-normal text-zinc-300">({indicator.unit})</span>
      </p>
      <ResponsiveContainer width="100%" height={120}>
        <LineChart data={points} margin={{ top: 4, right: 4, left: -28, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
          <XAxis dataKey="visit" tick={tickStyle} tickLine={false} axisLine={false} />
          <YAxis tick={tickStyle} tickLine={false} axisLine={false} domain={["auto", "auto"]} />
          <Tooltip content={<ChartTooltip />} />
          {ref?.min != null && <ReferenceLine y={ref.min} stroke="#fbbf24" strokeDasharray="4 3" strokeWidth={1} />}
          {ref?.max != null && <ReferenceLine y={ref.max} stroke="#fbbf24" strokeDasharray="4 3" strokeWidth={1} />}
          {indicator.target != null && (
            <ReferenceLine y={indicator.target} stroke="#059669" strokeDasharray="5 3" strokeWidth={1.5}
              label={{ value: "Target", position: "right", style: { fontSize: 8, fill: "#6b7280" } }} />
          )}
          <Line type="monotone" dataKey="value" name={indicator.label} stroke={color} strokeWidth={2}
            dot={{ r: 3, fill: color, strokeWidth: 0 }} activeDot={{ r: 5 }} connectNulls={false} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

function PlanTrendCharts({ plan }: { plan: MonitoringPlan }) {
  const groups: Array<{ title: string; category: PlanIndicator["category"] }> = [
    { title: "Anthropometric Trends", category: "anthro" },
    { title: "Clinical Lab Trends", category: "lab" },
    { title: "Intake vs Prescription", category: "intake" },
  ];

  const sections = groups
    .map((g) => ({ ...g, items: plan.indicators.filter((i) => i.category === g.category) }))
    .filter((g) => g.items.length > 0);

  if (sections.length === 0) {
    return (
      <div className="bg-white border border-zinc-200 rounded-2xl p-8 text-center shadow-sm">
        <p className="text-xs font-semibold text-zinc-400">No tracked indicators yet for this care plan.</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {sections.map((s) => (
        <ChartCard key={s.category} title={s.title}>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            {s.items.map((i) => <PlanIndicatorChart key={i.key} indicator={i} />)}
          </div>
          <p className="text-[9px] text-zinc-300 mt-3 select-none">
            Visit 1 = assessment baseline · amber = reference range · green dashed = prescription target.
          </p>
        </ChartCard>
      ))}
    </div>
  );
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmtDate = (iso: string) =>
  new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric" });

const fmtNum = (n: number) =>
  Number.isInteger(n) ? String(n) : n.toFixed(1);

// ─── Custom tooltip — matches project card aesthetic ─────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function ChartTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;
  return (
    <div className="bg-white border border-zinc-200 rounded-xl shadow-sm px-3 py-2.5 text-xs min-w-[120px]">
      <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-2">{label}</p>
      {payload.map(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (p: any) =>
          p.value !== null && p.value !== undefined ? (
            <div key={p.dataKey} className="flex items-center gap-2 py-0.5">
              <span
                className="w-1.5 h-1.5 rounded-full shrink-0"
                style={{ backgroundColor: p.color }}
              />
              <span className="text-zinc-400 truncate max-w-[80px]">{p.name}</span>
              <span className="font-mono font-semibold text-zinc-900 ml-auto pl-2">
                {typeof p.value === "number" ? fmtNum(p.value) : p.value}
              </span>
            </div>
          ) : null
      )}
    </div>
  );
}

// ─── Shared axis tick style ───────────────────────────────────────────────────
const tickStyle = {
  fontFamily: "ui-monospace, monospace",
  fontSize: 9,
  fill: "#a1a1aa",
};

// ─── Mini chart wrapper — section label + responsive container ────────────────
function ChartCard({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
      <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-4">
        {title}
      </p>
      {children}
    </div>
  );
}

// ─── Weight & BMI trend ───────────────────────────────────────────────────────

function WeightBmiChart({ entries }: { entries: MonitoringEntry[] }) {
  const data = useMemo(
    () =>
      entries
        .filter((e) => e.weight !== null || e.bmi !== null)
        .map((e) => ({
          date: fmtDate(e.created_at),
          weight: e.weight ?? null,
          bmi: e.bmi ?? null,
        })),
    [entries]
  );

  if (data.length < 2) return null;

  const hasWeight = data.some((d) => d.weight !== null);
  const hasBmi = data.some((d) => d.bmi !== null);

  return (
    <ChartCard title="Weight & BMI Trend">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {hasWeight && (
          <div>
            <p className="text-[9px] font-bold text-zinc-300 uppercase tracking-widest mb-2">
              Weight (kg)
            </p>
            <ResponsiveContainer width="100%" height={130}>
              <LineChart data={data} margin={{ top: 4, right: 4, left: -28, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
                <XAxis dataKey="date" tick={tickStyle} tickLine={false} axisLine={false} />
                <YAxis tick={tickStyle} tickLine={false} axisLine={false} domain={["auto", "auto"]} />
                <Tooltip content={<ChartTooltip />} />
                <Line
                  type="monotone"
                  dataKey="weight"
                  name="Weight"
                  stroke="#059669"
                  strokeWidth={2}
                  dot={{ r: 3, fill: "#059669", strokeWidth: 0 }}
                  activeDot={{ r: 5 }}
                  connectNulls={false}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}

        {hasBmi && (
          <div>
            <p className="text-[9px] font-bold text-zinc-300 uppercase tracking-widest mb-2">
              BMI (kg/m²)
            </p>
            <ResponsiveContainer width="100%" height={130}>
              <LineChart data={data} margin={{ top: 4, right: 4, left: -28, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
                <XAxis dataKey="date" tick={tickStyle} tickLine={false} axisLine={false} />
                <YAxis tick={tickStyle} tickLine={false} axisLine={false} domain={["auto", "auto"]} />
                <Tooltip content={<ChartTooltip />} />
                {/* WHO normal range: 18.5–24.9 */}
                <ReferenceLine y={18.5} stroke="#fbbf24" strokeDasharray="4 3" strokeWidth={1} />
                <ReferenceLine y={24.9} stroke="#fbbf24" strokeDasharray="4 3" strokeWidth={1} />
                <Line
                  type="monotone"
                  dataKey="bmi"
                  name="BMI"
                  stroke="#f59e0b"
                  strokeWidth={2}
                  dot={{ r: 3, fill: "#f59e0b", strokeWidth: 0 }}
                  activeDot={{ r: 5 }}
                  connectNulls={false}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}
      </div>
    </ChartCard>
  );
}

// ─── Macro intake vs target ───────────────────────────────────────────────────

const MACRO_META = [
  { key: "energy_kcal", label: "Energy",  unit: "kcal", targetKey: "energy_kcal", color: "#059669" },
  { key: "protein_g",   label: "Protein", unit: "g",    targetKey: "protein_g",   color: "#2563eb" },
  { key: "carbs_g",     label: "Carbs",   unit: "g",    targetKey: "carbs_g",     color: "#d97706" },
  { key: "fat_g",       label: "Fat",     unit: "g",    targetKey: "fat_g",       color: "#e11d48" },
  { key: "fluid_ml",    label: "Fluid",   unit: "mL",   targetKey: "fluid_ml",    color: "#0891b2" },
] as const;

function MacroTrendsChart({
  entries,
  intervention,
}: {
  entries: MonitoringEntry[];
  intervention: Intervention;
}) {
  // Only include macros where at least one entry has actual intake data
  const activeMacros = useMemo(
    () =>
      MACRO_META.filter((m) =>
        entries.some((e) => {
          const v = e.lab_values?.[m.key];
          return v !== null && v !== undefined;
        })
      ),
    [entries]
  );

  // Build % of target dataset — show percentage only when target is set
  // Fall back to raw value chart when no target exists
  const hasAnyTarget = useMemo(
    () =>
      activeMacros.some((m) => {
        const t = intervention[m.targetKey as keyof typeof intervention];
        return t !== null && t !== undefined && t !== "";
      }),
    [activeMacros, intervention]
  );

  const data = useMemo(
    () =>
      entries.map((e) => {
        const point: Record<string, string | number | null> = {
          date: fmtDate(e.created_at),
        };
        activeMacros.forEach((m) => {
          const actual = e.lab_values?.[m.key];
          if (actual === null || actual === undefined) {
            point[m.key] = null;
          } else if (hasAnyTarget) {
            const target = parseFloat(
              String(intervention[m.targetKey as keyof typeof intervention] ?? "0")
            );
            point[m.key] = target > 0 ? Math.round((Number(actual) / target) * 100) : Number(actual);
          } else {
            point[m.key] = Number(actual);
          }
        });
        return point;
      }),
    [entries, activeMacros, intervention, hasAnyTarget]
  );

  if (activeMacros.length === 0) return null;

  return (
    <ChartCard
      title={
        hasAnyTarget ? "Macro Intake vs Prescription (% of target)" : "Macro Intake Trends"
      }
    >
      <div className="overflow-x-auto">
        <div className="min-w-[300px]">
          <ResponsiveContainer width="100%" height={180}>
            <LineChart data={data} margin={{ top: 4, right: 16, left: -28, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
              <XAxis dataKey="date" tick={tickStyle} tickLine={false} axisLine={false} />
              <YAxis
                tick={tickStyle}
                tickLine={false}
                axisLine={false}
                domain={hasAnyTarget ? [0, "auto"] : ["auto", "auto"]}
                tickFormatter={(v) => (hasAnyTarget ? `${v}%` : String(v))}
              />
              <Tooltip content={<ChartTooltip />} />
              {hasAnyTarget && (
                <ReferenceLine
                  y={100}
                  stroke="#059669"
                  strokeDasharray="5 3"
                  strokeWidth={1.5}
                  label={{
                    value: "Target",
                    position: "right",
                    style: { fontSize: 8, fill: "#6b7280" },
                  }}
                />
              )}
              <Legend
                iconType="circle"
                iconSize={7}
                wrapperStyle={{ fontSize: 9, paddingTop: 8 }}
              />
              {activeMacros.map((m) => (
                <Line
                  key={m.key}
                  type="monotone"
                  dataKey={m.key}
                  name={m.label}
                  stroke={m.color}
                  strokeWidth={2}
                  dot={{ r: 3, fill: m.color, strokeWidth: 0 }}
                  activeDot={{ r: 5 }}
                  connectNulls={false}
                />
              ))}
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>
      {hasAnyTarget && (
        <p className="text-[9px] text-zinc-300 mt-2 select-none">
          Dashed line = prescription target (100%). Values above = exceeds target.
        </p>
      )}
    </ChartCard>
  );
}

// ─── Lab value trends ─────────────────────────────────────────────────────────

const LAB_COLORS: Record<string, string> = {
  albumin:     "#059669",
  hba1c:       "#d97706",
  ldl:         "#e11d48",
  cholesterol: "#7c3aed",
  creatinine:  "#0891b2",
  potassium:   "#2563eb",
  hemoglobin:  "#059669",
  glucose:     "#f59e0b",
};

function LabTrendsChart({
  entries,
  labKeys,
}: {
  entries: MonitoringEntry[];
  labKeys: ClinicalLabKey[];
}) {
  // Filter to numeric labs (skip bp)
  const numericKeys = labKeys.filter((k) => k !== "bp");

  // Only include labs where at least one entry has data
  const activeKeys = useMemo(
    () =>
      numericKeys.filter((k) =>
        entries.some((e) => {
          const v = e.lab_values?.[k];
          return v !== null && v !== undefined && typeof v === "number";
        })
      ),
    [entries, numericKeys]
  );

  const data = useMemo(
    () =>
      entries.map((e) => {
        const point: Record<string, string | number | null> = {
          date: fmtDate(e.created_at),
        };
        activeKeys.forEach((k) => {
          const v = e.lab_values?.[k];
          point[k] = typeof v === "number" ? v : null;
        });
        return point;
      }),
    [entries, activeKeys]
  );

  if (activeKeys.length === 0) return null;

  return (
    <ChartCard title="Clinical Lab Trends">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
        {activeKeys.map((k) => {
          const meta = CLINICAL_LAB_META[k];
          const ref = LAB_REFERENCE_RANGES[k];

          return (
            <div key={k}>
              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5">
                {meta.label}{" "}
                <span className="normal-case font-normal text-zinc-300">({meta.unit})</span>
              </p>
              <ResponsiveContainer width="100%" height={120}>
                <LineChart
                  data={data}
                  margin={{ top: 4, right: 4, left: -28, bottom: 0 }}
                >
                  <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
                  <XAxis
                    dataKey="date"
                    tick={tickStyle}
                    tickLine={false}
                    axisLine={false}
                  />
                  <YAxis
                    tick={tickStyle}
                    tickLine={false}
                    axisLine={false}
                    domain={["auto", "auto"]}
                  />
                  <Tooltip content={<ChartTooltip />} />
                  {/* Reference range lines */}
                  {ref?.min !== undefined && (
                    <ReferenceLine
                      y={ref.min}
                      stroke="#fbbf24"
                      strokeDasharray="4 3"
                      strokeWidth={1}
                    />
                  )}
                  {ref?.max !== undefined && (
                    <ReferenceLine
                      y={ref.max}
                      stroke="#fbbf24"
                      strokeDasharray="4 3"
                      strokeWidth={1}
                    />
                  )}
                  <Line
                    type="monotone"
                    dataKey={k}
                    name={meta.label}
                    stroke={LAB_COLORS[k] ?? "#059669"}
                    strokeWidth={2}
                    dot={{ r: 3, fill: LAB_COLORS[k] ?? "#059669", strokeWidth: 0 }}
                    activeDot={{ r: 5 }}
                    connectNulls={false}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          );
        })}
      </div>
      <p className="text-[9px] text-zinc-300 mt-3 select-none">
        Dashed amber lines = reference range boundaries.
      </p>
    </ChartCard>
  );
}

// ─── Micro intake trends ──────────────────────────────────────────────────────

function MicroTrendsChart({
  entries,
  microKeys,
  micronutrientLimits,
}: {
  entries: MonitoringEntry[];
  microKeys: string[];
  micronutrientLimits: Record<string, { min?: number; max?: number; unit: string }> | null;
}) {
  const activeMicros = useMemo(
    () =>
      microKeys.filter((k) =>
        entries.some((e) => {
          const v = e.lab_values?.[`micro_${k}`];
          return v !== null && v !== undefined;
        })
      ),
    [entries, microKeys]
  );

  const metaMap = Object.fromEntries(ALL_MICROS.map((m) => [m.key, m]));

  const data = useMemo(
    () =>
      entries.map((e) => {
        const point: Record<string, string | number | null> = {
          date: fmtDate(e.created_at),
        };
        activeMicros.forEach((k) => {
          const v = e.lab_values?.[`micro_${k}`];
          point[k] = typeof v === "number" ? v : null;
        });
        return point;
      }),
    [entries, activeMicros]
  );

  if (activeMicros.length === 0) return null;

  return (
    <ChartCard title="Micronutrient Intake Trends">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
        {activeMicros.map((k) => {
          const meta = metaMap[k];
          const limits = micronutrientLimits?.[k];

          return (
            <div key={k}>
              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1.5">
                {meta?.label ?? k}{" "}
                <span className="normal-case font-normal text-zinc-300">({meta?.unit ?? ""})</span>
              </p>
              <ResponsiveContainer width="100%" height={120}>
                <LineChart
                  data={data}
                  margin={{ top: 4, right: 4, left: -28, bottom: 0 }}
                >
                  <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
                  <XAxis
                    dataKey="date"
                    tick={tickStyle}
                    tickLine={false}
                    axisLine={false}
                  />
                  <YAxis
                    tick={tickStyle}
                    tickLine={false}
                    axisLine={false}
                    domain={["auto", "auto"]}
                  />
                  <Tooltip content={<ChartTooltip />} />
                  {limits?.min !== undefined && (
                    <ReferenceLine
                      y={limits.min}
                      stroke="#fbbf24"
                      strokeDasharray="4 3"
                      strokeWidth={1}
                    />
                  )}
                  {limits?.max !== undefined && (
                    <ReferenceLine
                      y={limits.max}
                      stroke="#fbbf24"
                      strokeDasharray="4 3"
                      strokeWidth={1}
                    />
                  )}
                  <Line
                    type="monotone"
                    dataKey={k}
                    name={meta?.label ?? k}
                    stroke="#059669"
                    strokeWidth={2}
                    dot={{ r: 3, fill: "#059669", strokeWidth: 0 }}
                    activeDot={{ r: 5 }}
                    connectNulls={false}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          );
        })}
      </div>
    </ChartCard>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function VisitTrendsChart({
  plan,
  entries,
  intervention,
}: VisitTrendsChartProps) {
  // Plan-driven rendering: patient-specific tracked set, seeded at Visit 1.
  if (plan && plan.indicators.length > 0) {
    return <PlanTrendCharts plan={plan} />;
  }
  return <LegacyVisitTrendsChart entries={entries} intervention={intervention} />;
}

function LegacyVisitTrendsChart({
  entries,
  intervention,
}: {
  entries: MonitoringEntry[];
  intervention: Intervention | null;
}) {
  // Sorted chronologically
  const sorted = useMemo(
    () =>
      [...entries].sort(
        (a, b) =>
          new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
      ),
    [entries]
  );

  if (sorted.length < 2) {
    return (
      <div className="bg-white border border-zinc-200 rounded-2xl p-8 text-center shadow-sm">
        <p className="text-xs font-semibold text-zinc-400">
          Log at least 2 visits to see trend charts.
        </p>
      </div>
    );
  }

  // Derive goal-relevant lab keys
  const goalType = intervention?.goal_type ?? null;
  const labKeys: ClinicalLabKey[] = goalType
    ? (GOAL_LAB_FLAGS[goalType] ?? [])
    : (Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[]);

  // Derive micro keys
  const flaggedMicros: string[] = goalType ? (GOAL_MICRO_FLAGS[goalType] ?? []) : [];
  const displayedMicros: string[] = intervention?.displayed_nutrients ?? [];
  const microKeys = Array.from(new Set([...displayedMicros, ...flaggedMicros]));

  return (
    <div className="space-y-4">
      {/* Weight & BMI */}
      <WeightBmiChart entries={sorted} />

      {/* Macro intake vs targets */}
      {intervention && (
        <MacroTrendsChart entries={sorted} intervention={intervention} />
      )}

      {/* Lab trends */}
      {labKeys.length > 0 && (
        <LabTrendsChart entries={sorted} labKeys={labKeys} />
      )}

      {/* Micro intake trends */}
      {microKeys.length > 0 && (
        <MicroTrendsChart
          entries={sorted}
          microKeys={microKeys}
          micronutrientLimits={intervention?.micronutrient_limits ?? null}
        />
      )}
    </div>
  );
}
