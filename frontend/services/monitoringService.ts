import { apiFetch } from "@/lib/apiFetch";
import type { MonitoringPlan } from "@/services/monitoringPlan";
export type { MonitoringPlan, PlanIndicator, PlanVisit, PlanSeriesPoint, IndicatorStatus, IndicatorCategory } from "@/services/monitoringPlan";
// ─── Types ────────────────────────────────────────────────────────────────────

export interface MonitoringLabValues {
  // Clinical lab results
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
  bp?: string | null;
  // Macro actual intake (logged per visit)
  energy_kcal?: number | null;
  protein_g?: number | null;
  carbs_g?: number | null;
  fat_g?: number | null;
  fluid_ml?: number | null;
  // Micro nutrient intake — dynamic keys (sodium, phosphate, fiber, etc.)
  [key: string]: number | string | null | undefined;
}

export type ComplianceStatus = 'compliant' | 'partial' | 'non_compliant';
export type GiToleranceStatus = 'tolerating' | 'not_tolerating';
export type ContinuationDecision = 'continue' | 'modify' | 'discontinue' | null;

export interface MonitoringEntry {
  id: number;
  ncp_record_id: number;
  weight: number | null;
  bmi: number | null;
  lab_values: MonitoringLabValues | null;
  intake_notes: string | null;
  symptoms: string | null;
  goal_achievement: Record<string, string> | null;
  clinical_summary: string | null;
  ai_decision: string | null;
  next_monitoring_date: string | null;
  created_at: string;
  updated_at: string;
}

export interface MonitoringPayload {
  weight?: number | null;
  bmi?: number | null;
  lab_values?: MonitoringLabValues | null;
  intake_notes?: string | null;
  symptoms?: string | null;
  goal_achievement?: Record<string, string> | null;
  clinical_summary?: string | null;
  ai_decision?: string | null;
  next_monitoring_date?: string | null;
}

// ─── API Functions ─────────────────────────────────────────────────────────────

export async function fetchMonitorings(ncpRecordId: number | string): Promise<MonitoringEntry[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitorings`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to fetch monitoring entries.');
  }
  const data = await res.json();
  return data.data ?? data ?? [];
}

export async function createMonitoring(
  ncpRecordId: number | string,
  payload: MonitoringPayload
): Promise<MonitoringEntry> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitorings`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to create monitoring entry.');
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function updateMonitoring(
  ncpRecordId: number | string,
  monitoringId: number | string,
  payload: Partial<MonitoringPayload>
): Promise<MonitoringEntry> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitorings/${monitoringId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to update monitoring entry.');
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function deleteMonitoring(
  ncpRecordId: number | string,
  monitoringId: number | string
): Promise<void> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitorings/${monitoringId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok && res.status !== 204) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to delete monitoring entry.');
  }
}

// ─── Phase 6 — Monitoring summary (rule-based delta + optional AI review) ──────

export interface MonitoringChange {
  metric: string;
  label: string;
  unit: string;
  previous: number | null;
  current: number;
  delta: number | null;
  delta_pct: number | null;
  direction: 'up' | 'down' | 'flat';
  status: GoalStatus;
}

export interface MonitoringIntake {
  metric: string;
  label: string;
  unit: string;
  actual: number;
  target: number;
  pct: number;
  flag: 'under' | 'over' | 'on_target';
}

export interface MonitoringSummary {
  has_data: boolean;
  has_previous: boolean;
  previous_date: string | null;
  current_date: string | null;
  changes: MonitoringChange[];
  intake: MonitoringIntake[];
  goal_evaluation: { status: GoalStatus | 'partial'; reasons: string[] };
}

export async function fetchMonitoringSummary(ncpRecordId: number | string): Promise<MonitoringSummary> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitorings/summary`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to load monitoring summary.');
  }
  return (await res.json()).data;
}

export async function fetchMonitoringPlan(ncpRecordId: number | string): Promise<MonitoringPlan> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitoring-plan`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to load monitoring plan.');
  }
  return (await res.json()).data;
}

export interface AiReviewResult { narrative: string; cached: boolean; }

export async function requestMonitoringAiReview(ncpRecordId: number | string): Promise<AiReviewResult> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpRecordId}/monitorings/ai-review`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'AI review failed.');
  }
  return (await res.json()).data;
}

// ─── BMI Helper ───────────────────────────────────────────────────────────────

export function calculateBmi(weightKg: number, heightCm: number): number {
  const heightM = heightCm / 100;
  return Math.round((weightKg / (heightM * heightM)) * 10) / 10;
}

// ─── Goal Status Helpers ──────────────────────────────────────────────────────
// Status is derived from standard clinical reference ranges.
// Met     = value is within the normal range.
// In Progress = improved from baseline but not yet normal.
// Not Met = not improved or worsened from baseline.

export type GoalStatus = 'met' | 'in_progress' | 'not_met' | 'no_data';

export interface LabReferenceRange {
  label: string;
  unit: string;
  min?: number;
  max?: number;
  lowerIsBetter?: boolean;
}

export const LAB_REFERENCE_RANGES: Record<keyof MonitoringLabValues, LabReferenceRange> = {
  albumin:     { label: 'Albumin',       unit: 'g/dL',   min: 3.5 },
  hba1c:       { label: 'HbA1c',         unit: '%',      max: 7.0,  lowerIsBetter: true },
  ldl:         { label: 'LDL',           unit: 'mg/dL',  max: 100,  lowerIsBetter: true },
  cholesterol: { label: 'Cholesterol',   unit: 'mg/dL',  max: 200,  lowerIsBetter: true },
  creatinine:  { label: 'Creatinine',    unit: 'mg/dL',  max: 1.2,  lowerIsBetter: true },
  potassium:   { label: 'Potassium',     unit: 'mEq/L',  min: 3.5,  max: 5.0 },
  phosphate:   { label: 'Phosphate',     unit: 'mg/dL',  min: 2.5,  max: 4.5 },
  magnesium:   { label: 'Magnesium',     unit: 'mg/dL',  min: 1.7,  max: 2.2 },
  hemoglobin:  { label: 'Hemoglobin',    unit: 'g/dL',   min: 12.0 },
  glucose:     { label: 'Glucose',       unit: 'mg/dL',  max: 100,  lowerIsBetter: true },
  bp:          { label: 'Blood Pressure', unit: 'mmHg' },
};

export function getLabStatus(
  key: keyof MonitoringLabValues,
  current: number,
  baseline: number | null
): GoalStatus {
  if (key === 'bp') return 'no_data';
  const ref = LAB_REFERENCE_RANGES[key];
  if (!ref || typeof current !== 'number') return 'no_data';

  const inRange =
    (ref.min === undefined || current >= ref.min) &&
    (ref.max === undefined || current <= ref.max);

  if (inRange) return 'met';

  if (baseline !== null && baseline !== undefined) {
    const improving = ref.lowerIsBetter
      ? current < baseline
      : ref.min !== undefined
        ? current > baseline
        : current < baseline;
    if (improving) return 'in_progress';
  }

  return 'not_met';
}

export function getWeightStatus(
  currentWeight: number,
  baselineWeight: number | null,
  nutritionalStatus: string | null
): GoalStatus {
  if (baselineWeight === null) return 'no_data';
  if (nutritionalStatus?.includes('Malnutrition')) {
    if (currentWeight > baselineWeight) return 'met';
    if (currentWeight === baselineWeight) return 'in_progress';
    return 'not_met';
  }
  if (currentWeight < baselineWeight) return 'met';
  if (currentWeight === baselineWeight) return 'in_progress';
  return 'not_met';
}

// ─── Goal → Clinical Lab mapping ──────────────────────────────────────────────
// Determines which lab fields to show in the monitoring form based on goal_type.
// Keys must match MonitoringLabValues clinical fields.

export type ClinicalLabKey = 'albumin' | 'hba1c' | 'ldl' | 'cholesterol' |
  'creatinine' | 'potassium' | 'phosphate' | 'magnesium' | 'hemoglobin' | 'glucose' | 'bp';

export const GOAL_LAB_FLAGS: Record<string, ClinicalLabKey[]> = {
  renal_diet:       ['albumin', 'creatinine', 'potassium', 'phosphate', 'hemoglobin'],
  diabetic_control: ['hba1c', 'glucose', 'albumin'],
  cardiac_diet:     ['bp', 'ldl', 'cholesterol'],
  weight_loss:      [],
  weight_gain:      ['albumin', 'hemoglobin'],
  high_protein:     ['albumin', 'creatinine'],
  liver_disease:    ['albumin'],
  malnutrition:     ['albumin', 'hemoglobin'],
  custom:           ['albumin', 'hba1c', 'ldl', 'cholesterol', 'creatinine', 'potassium', 'phosphate', 'magnesium', 'hemoglobin', 'glucose', 'bp'],
};

// Clinical lab display metadata (label + unit) for the form and tracker
export const CLINICAL_LAB_META: Record<ClinicalLabKey, { label: string; unit: string; type: 'number' | 'text' }> = {
  albumin:     { label: 'Albumin',       unit: 'g/dL',   type: 'number' },
  hba1c:       { label: 'HbA1c',         unit: '%',      type: 'number' },
  ldl:         { label: 'LDL',           unit: 'mg/dL',  type: 'number' },
  cholesterol: { label: 'Cholesterol',   unit: 'mg/dL',  type: 'number' },
  creatinine:  { label: 'Creatinine',    unit: 'mg/dL',  type: 'number' },
  potassium:   { label: 'Potassium',     unit: 'mEq/L',  type: 'number' },
  phosphate:   { label: 'Phosphate',     unit: 'mg/dL',  type: 'number' },
  magnesium:   { label: 'Magnesium',     unit: 'mg/dL',  type: 'number' },
  hemoglobin:  { label: 'Hemoglobin',    unit: 'g/dL',   type: 'number' },
  glucose:     { label: 'Glucose',       unit: 'mg/dL',  type: 'number' },
  bp:          { label: 'Blood Pressure', unit: 'mmHg',  type: 'text'   },
};
