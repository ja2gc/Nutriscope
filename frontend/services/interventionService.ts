import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

export interface MicronutrientLimit {
  target?: number;
  max?: number;
  min?: number;
  unit: string;
}

export interface CalculationOptions {
  energy_per_kg?: number;
  protein_per_kg?: number;
  clinical_weight_kg?: number;
  protein_weight_kg?: number;
  weight_basis?: "measured" | "dry" | "clinician_selected";
  nutrition_status?: "adequate" | "malnutrition_or_muscle_loss" | "unconfirmed";
  maternal_confirmed?: boolean;
  refeeding_risk?: boolean;
  fat_fraction?: number;
}

export interface CalculationSnapshot {
  protein_fraction?: number;
  eer_coefficients?: number[];
  growth_energy_kcal?: number;
  pediatric_activity_category?: string | null;
  version: number;
  method: string;
  source?: string;
  measurement_date?: string | null;
  inputs: { weightKg?: number; heightCm?: number; ageYears?: number; ageMonths?: number; sex?: string; activityFactor?: number; pregnancyLactationStatus?: string };
  weight_kg?: number;
  protein_weight_kg?: number;
  weight_basis?: string;
  resting_energy_kcal?: number | null;
  baseline_energy_kcal?: number | null;
  baseline_kind?: string;
  energy_per_kg?: number | null;
  protein_per_kg?: number;
  fat_fraction?: number;
  energy_adjustment_mode?: string;
  energy_adjustment_kcal?: number;
  signed_adjustment_kcal?: number;
  maternal_energy_kcal?: number;
  maternal_protein_g?: number;
  maternal_water_guidance_ml?: number;
  fluid_mode?: string;
  fluid_formula?: string;
  calculated: Record<string, number | null>;
  manual_overrides?: Record<string, number>;
}

export interface FoodGuide { choose: string; limit: string; context: string }
export interface GuidanceSuggestion {
  education_notes: string;
  counseling_goals: string;
  strategies: string;
  foods?: FoodGuide;
}

export interface Intervention {
  recalculate?: boolean;
  energy_adjustment_mode?: "none" | "surplus" | "deficit" | null;
  energy_adjustment_kcal?: number | null;
  fluid_mode?: "estimated" | "restricted" | null;
  calculation_options?: CalculationOptions | null;
  calculation_snapshot?: CalculationSnapshot | null;
  guidance?: GuidanceSuggestion;
  weight_target?: { target?: number; min?: number; max?: number } | null;
  nutrient_operators?: Record<string, 'min' | 'max'> | null;
  id: string;
  goal_type: string | null;
  disease_stage: string | null;
  displayed_nutrients: string[] | null;
  energy_kcal: string | number | null;
  protein_g: string | number | null;
  carbs_g: string | number | null;
  fat_g: string | number | null;
  fluid_ml: string | number | null;
  micronutrient_limits: Record<string, MicronutrientLimit> | null;
  education_notes: string | null;
  counseling_goals: string | null;
  barriers: string | null;
  strategies: string | null;
  session_type: string | null;
  next_followup_date: string | null;
  source_monitoring_id: string | null;
  source_monitoring_date: string | null;
  has_meal_plan: boolean;
  meal_plan_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface InterventionPlanSummary {
  id: string;
  plan_date: string;
  goal_type: string | null;
  disease_stage: string | null;
  source_monitoring_id: string | null;
  source_monitoring_date: string | null;
  has_meal_plan: boolean;
  meal_plan_id: string | null;
}

export interface InterventionPlanPage {
  data: InterventionPlanSummary[];
  meta: PaginationMeta;
}

export interface RecommendResult {
  recommend: { tag: string; condition: string; reason: string }[];
  avoid:     { tag: string; condition: string; reason: string }[];
  limits:    { tag: string; condition: string; reason: string; threshold: number | null; unit: string }[];
}

const plansBase = (ncpId: string) => `/api/rnd/ncp-records/${ncpId}/interventions`;

export async function updateIntervention(ncpId: string, planId: string, payload: Partial<Intervention>): Promise<Intervention> {
  const res = await apiFetch(`${plansBase(ncpId)}/${planId}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) });
  if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'Failed to update intervention plan.');
  return (await res.json()).data;
}

export async function deleteIntervention(ncpId: string, planId: string): Promise<void> {
  const res = await apiFetch(`${plansBase(ncpId)}/${planId}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'Failed to delete intervention plan.');
}

export async function fetchInterventionPlans(ncpId: string, page = 1): Promise<InterventionPlanPage> {
  const res = await apiFetch(`${plansBase(ncpId)}?page=${page}&per_page=10`, {
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error("Failed to fetch intervention plans.");
  const body = await res.json();

  return {
    data: body.data ?? [],
    meta: body.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 },
  };
}

export async function fetchInterventionPlan(ncpId: string, planId: string): Promise<Intervention> {
  const res = await apiFetch(`${plansBase(ncpId)}/${planId}`, {
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error("Failed to fetch intervention plan.");

  return (await res.json()).data;
}

export async function fetchIntervention(ncpId: string): Promise<Intervention | null> {
  const res = await apiFetch(`${plansBase(ncpId)}/latest`, { headers: { Accept: 'application/json' } });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error('Failed to fetch intervention.');
  const data = await res.json();
  return data.data ?? null;
}

export async function createIntervention(ncpId: string, payload: Partial<Intervention>): Promise<Intervention> {
  const res = await apiFetch(plansBase(ncpId), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || 'Failed to create intervention.');
  }
  return (await res.json()).data;
}

/** Authoritative prescription from the backend engine (Phase 2/2.4 — source of truth). */
export interface AutofillResult {
  energy_kcal: number | null;
  protein_g: number | null;
  carbs_g: number | null;
  fat_g: number | null;
  fluid_ml: number | null;
  fluid_mode?: "estimated" | "restricted";
  nutrient_operators?: Record<string, "min" | "max">;
  calculation_snapshot?: CalculationSnapshot;
  guidance?: GuidanceSuggestion;
  fiber_g?: number;
  sodium_max_mg?: number;
  free_sugar_max_pct?: number;
  maternal_modifier?: {
    status: string;
    baseline: { energy_kcal: number; protein_g: number; fluid_ml: number };
    modifier: { energy_kcal: number; protein_g: number; water_guidance_ml: number };
    final: { energy_kcal: number; protein_g: number; fluid_ml: number };
    source_key: string;
  };
  calculation_status?: "ok" | "warning" | "incomplete" | "invalid_goal_stage" | "maternal_status_confirmation_required" | "manual_targets_required";
  safety_warnings?: { key: string; severity: "warning" | "critical"; message: string }[];
  note?: string;
  source_type?: "assessment" | "monitoring";
  source_monitoring_id?: string | null;
  source_monitoring_date?: string | null;
}

export class AutofillError extends Error {
  missingFields: string[];

  constructor(message: string, missingFields: string[] = []) {
    super(message);
    this.name = "AutofillError";
    this.missingFields = missingFields;
  }
}

/**
 * Current calculation previews and their provenance come from the PHP engine.
 */
export async function autofillIntervention(
  ncpId: string,
  goalType: string,
  diseaseStage: string | null,
  options: Partial<Intervention> = {},
  guidanceOnly = false,
): Promise<AutofillResult> {
  const res = await apiFetch(`${plansBase(ncpId)}/autofill`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      goal_type: goalType, disease_stage: diseaseStage,
      energy_adjustment_mode: options.energy_adjustment_mode,
      energy_adjustment_kcal: options.energy_adjustment_kcal,
      fluid_mode: options.fluid_mode, fluid_ml: options.fluid_ml,
      calculation_options: options.calculation_options,
      ...(guidanceOnly ? { guidance_only: true } : {}),
    }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new AutofillError(
      (err as { message?: string }).message || 'Failed to autofill prescription.',
      Array.isArray((err as { missing_fields?: unknown }).missing_fields)
        ? (err as { missing_fields: string[] }).missing_fields
        : Object.keys((err as { errors?: Record<string, unknown> }).errors ?? {}),
    );
  }
  return (await res.json()).data;
}

export async function fetchRecommendations(ncpId: string): Promise<RecommendResult> {
  const res = await apiFetch(`${plansBase(ncpId)}/recommendations`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return { recommend: [], avoid: [], limits: [] };
  return (await res.json()).data ?? { recommend: [], avoid: [], limits: [] };
}
