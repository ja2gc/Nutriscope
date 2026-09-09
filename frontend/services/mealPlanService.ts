import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

// ─── Types ───────────────────────────────────────────────────────────────────

export interface NutrientSnapshot {
  fdc_id?: number | null;
  name: string;
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
  water_g?: number | null;
  micronutrients: Record<string, number>;
  serving_size: number;
  serving_unit: string;
}

export interface MealPlanItem {
  id: string;
  meal_plan_day_id: string;
  food_item_id: string | null;
  fdc_id: string | null;
  recipe_id: string | null;
  quantity: string;
  unit: string;
  nutrient_snapshot: NutrientSnapshot | null;
  ai_suggested: boolean;
  source: "library" | "usda" | "recipe";
}

export interface MealPlanDay {
  id: string;
  meal_plan_id: number;
  day_of_week: "Monday" | "Tuesday" | "Wednesday" | "Thursday" | "Friday" | "Saturday" | "Sunday";
  meal_type: "breakfast" | "am_snack" | "lunch" | "pm_snack" | "dinner";
  flagged: boolean;
}

export interface MealPlan {
  id: string;
  intervention_id: number;
  patient_id: number;
  week_start_date: string;
  generation_type: "manual" | "auto";
  scale_status: "available" | "already_scaled";
  scaled_at: string | null;
  status: "draft" | "active";
  days: MealPlanDay[];
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const itemsBase = (ncpId: string, planId: string, dayId: string) =>
  `/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/days/${dayId}/items`;

// ─── Meal Plan API ────────────────────────────────────────────────────────────

export async function fetchMealPlans(ncpId: string): Promise<MealPlan[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) throw new Error("Failed to fetch meal plans.");

  const data = await res.json();
  return data.data ?? [];
}

export async function createMealPlan(
  ncpId: string,
  payload: { week_start_date: string; generation_type?: string }
): Promise<MealPlan> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || "Failed to create meal plan.");
  }

  const data = await res.json();
  return data.data ?? data;
}

// ─── Meal Plan Items API ──────────────────────────────────────────────────────

/** Fetch all items for a plan in one request — replaces 35 individual calls. */
export async function fetchAllMealPlanItems(
  ncpId: string,
  planId: string
): Promise<MealPlanItem[]> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/items`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('Failed to fetch meal plan items.');
  return (await res.json()).data ?? [];
}

export async function fetchMealPlanItems(
  ncpId: string,
  planId: string,
  dayId: string
): Promise<MealPlanItem[]> {
  const res = await apiFetch(itemsBase(ncpId, planId, dayId), {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) throw new Error("Failed to fetch meal plan items.");

  const data = await res.json();
  return data.data ?? [];
}

export async function addMealPlanItem(
  ncpId: string,
  planId: string,
  dayId: string,
  payload: {
    food_item_id?: string;
    fdc_id?: string;
    recipe_id?: string;
    quantity: number;
    unit: string;
  }
): Promise<MealPlanItem> {
  const res = await apiFetch(itemsBase(ncpId, planId, dayId), {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as { message?: string }).message || "Failed to add meal plan item.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function deleteMealPlan(ncpId: string, planId: string): Promise<void> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok && res.status !== 204) throw new Error('Failed to delete meal plan.');
}

export async function generateMealPlan(
  ncpId: string,
  payload: { week_start_date: string; conditions?: string[]; allergens?: string[]; exclude_snacks?: boolean }
): Promise<MealPlan
  | { insufficient_recipes: true; count: number; message: string }
  | { insufficient_suitable_foods: true; missing_meal_types: string[]; message: string }
  | { snacks_required: true; message: string }
> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/generate`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    if (data && typeof data === "object"
      && ("insufficient_recipes" in data || "insufficient_suitable_foods" in data || "snacks_required" in data)) return data;
    throw new Error((data as { message?: string }).message || "Failed to generate meal plan.");
  }
  return data.data ?? data;
}

export interface MealPlanTemplate {
  id: string;
  name: string;
  description: string | null;
  goal_type: string | null;
  disease_stage: string | null;
  created_at: string;
}

export interface MealPlanTemplateItem {
  id: string;
  quantity: string;
  unit: string;
  food_name: string | null;
  nutrient_snapshot: NutrientSnapshot | null;
  line_order: number;
}

export interface MealPlanTemplateDay {
  id: number;
  day_of_week: string;
  meal_type: string;
  quantity: string;
  unit: string;
  food_name: string | null;
  calories: number | null;
  items: MealPlanTemplateItem[];
}

export interface MealPlanTemplateDetail extends MealPlanTemplate {
  days: MealPlanTemplateDay[];
}

export async function fetchMealPlanTemplates(page = 1): Promise<{ data: MealPlanTemplate[]; meta: PaginationMeta }> {
  const res = await apiFetch(`/api/rnd/meal-plan-templates?page=${page}&per_page=10`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return { data: [], meta: { current_page: page, per_page: 10, total: 0, last_page: 1 } };
  const json = await res.json();
  return { data: json.data ?? [], meta: json.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}

export async function fetchMealPlanTemplate(templateId: string): Promise<MealPlanTemplateDetail | null> {
  const res = await apiFetch(`/api/rnd/meal-plan-templates/${templateId}`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return null;
  return (await res.json()).data ?? null;
}

export async function deleteMealPlanTemplate(templateId: string): Promise<void> {
  const res = await apiFetch(`/api/rnd/meal-plan-templates/${templateId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok && res.status !== 204) throw new Error('Failed to delete template.');
}

export async function saveMealPlanAsTemplate(
  ncpId: string,
  planId: string,
  payload: { name: string; description?: string; goal_type?: string }
): Promise<MealPlanTemplate> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/save-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error('Failed to save template.');
  return (await res.json()).data;
}

export async function createPlanFromTemplate(
  ncpId: string,
  payload: { template_id: string; week_start_date: string }
): Promise<{
  plan: MealPlan;
  compatibility: {
    goal_matches: boolean;
    disease_stage_matches: boolean;
    warning: string | null;
  };
}> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/from-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error('Failed to create plan from template.');
  const body = await res.json();
  return {
    plan: body.data,
    compatibility: body.meta?.template_compatibility ?? {
      goal_matches: true,
      disease_stage_matches: true,
      warning: null,
    },
  };
}

export async function scaleMealPlanToPrescription(
  ncpId: string,
  planId: string,
): Promise<{
  plan: MealPlan;
  scaling: {
    changed_items: number;
    inserted_items: number;
    substituted_items: number;
    problem_days: string[];
    variance: Record<string, Record<string, number | "cannot_validate">>;
  };
}> {
  const res = await apiFetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/scale-to-prescription`, {
    method: "POST",
    headers: { Accept: "application/json" },
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? "Failed to scale meal plan.");

  return { plan: body.data, scaling: body.meta.scaling };
}

export async function updateMealPlanItem(
  ncpId: string,
  planId: string,
  dayId: string,
  itemId: string,
  // DI-03: the client never sends a nutrient snapshot. For recipe ingredient
  // edits, send per-ingredient quantity overrides; the server recomputes the
  // snapshot from trusted food data.
  payload: { quantity?: number; ingredient_overrides?: { id: number; quantity: number }[] }
): Promise<MealPlanItem> {
  const res = await apiFetch(
    `/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/days/${dayId}/items/${itemId}`,
    {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    }
  );
  if (!res.ok) throw new Error('Failed to update meal plan item.');
  return (await res.json()).data;
}

export async function removeMealPlanItem(
  ncpId: string,
  planId: string,
  dayId: string,
  itemId: string
): Promise<void> {
  const res = await apiFetch(`${itemsBase(ncpId, planId, dayId)}/${itemId}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (!res.ok && res.status !== 204) throw new Error("Failed to remove meal plan item.");
}
