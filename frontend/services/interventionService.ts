import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

export interface MicronutrientLimit {
  max?: number;
  min?: number;
  unit: string;
}

export interface Intervention {
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
  limits:    { tag: string; condition: string; reason: string; threshold: number; unit: string }[];
}

const plansBase = (ncpId: string) => `/api/rnd/ncp-records/${ncpId}/interventions`;

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
  energy_kcal: number;
  protein_g: number;
  carbs_g: number;
  fat_g: number;
  fluid_ml: number;
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
  calculation_status?: "ok" | "warning" | "incomplete" | "invalid_goal_stage" | "maternal_status_confirmation_required";
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
 * POST /interventions/autofill — returns the spec-correct prescription computed
 * by the PHP engine. The TS mirror is for instant preview only; persisted values
 * should come from here so the frontend can never drift from the backend.
 */
export async function autofillIntervention(
  ncpId: string,
  goalType: string,
  diseaseStage: string | null,
): Promise<AutofillResult> {
  const res = await apiFetch(`${plansBase(ncpId)}/autofill`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ goal_type: goalType, disease_stage: diseaseStage }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new AutofillError(
      (err as { message?: string }).message || 'Failed to autofill prescription.',
      Array.isArray((err as { missing_fields?: unknown }).missing_fields)
        ? (err as { missing_fields: string[] }).missing_fields
        : [],
    );
  }
  return (await res.json()).data;
}

export async function fetchRecommendations(ncpId: string): Promise<RecommendResult> {
  const res = await apiFetch(`${plansBase(ncpId)}/recommendations`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return { recommend: [], avoid: [], limits: [] };
  return (await res.json()).data ?? { recommend: [], avoid: [], limits: [] };
}
