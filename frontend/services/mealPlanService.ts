// ─── Types ───────────────────────────────────────────────────────────────────

export interface NutrientSnapshot {
  fdc_id?: number | null;
  name: string;
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
  micronutrients: Record<string, number>;
  serving_size: number;
  serving_unit: string;
}

export interface MealPlanItem {
  id: number;
  meal_plan_day_id: number;
  food_item_id: number | null;
  fdc_id: string | null;
  recipe_id: number | null;
  quantity: string;
  unit: string;
  nutrient_snapshot: NutrientSnapshot | null;
  ai_suggested: boolean;
  source: "library" | "usda" | "recipe";
}

export interface MealPlanDay {
  id: number;
  meal_plan_id: number;
  day_of_week: "Monday" | "Tuesday" | "Wednesday" | "Thursday" | "Friday" | "Saturday" | "Sunday";
  meal_type: "breakfast" | "am_snack" | "lunch" | "pm_snack" | "dinner";
  flagged: boolean;
}

export interface MealPlan {
  id: number;
  intervention_id: number;
  patient_id: number;
  week_start_date: string;
  generation_type: "manual" | "auto";
  status: "draft" | "active";
  days: MealPlanDay[];
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const itemsBase = (ncpId: string, planId: number, dayId: number) =>
  `/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/days/${dayId}/items`;

// ─── Meal Plan API ────────────────────────────────────────────────────────────

export async function fetchMealPlans(ncpId: string): Promise<MealPlan[]> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans`, {
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
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans`, {
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
  planId: number
): Promise<MealPlanItem[]> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/items`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('Failed to fetch meal plan items.');
  return (await res.json()).data ?? [];
}

export async function fetchMealPlanItems(
  ncpId: string,
  planId: number,
  dayId: number
): Promise<MealPlanItem[]> {
  const res = await fetch(itemsBase(ncpId, planId, dayId), {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) throw new Error("Failed to fetch meal plan items.");

  const data = await res.json();
  return data.data ?? [];
}

export async function addMealPlanItem(
  ncpId: string,
  planId: number,
  dayId: number,
  payload: {
    food_item_id?: number;
    fdc_id?: string;
    recipe_id?: number;
    quantity: number;
    unit: string;
  }
): Promise<MealPlanItem> {
  const res = await fetch(itemsBase(ncpId, planId, dayId), {
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

export async function deleteMealPlan(ncpId: string, planId: number): Promise<void> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok && res.status !== 204) throw new Error('Failed to delete meal plan.');
}

export async function generateMealPlan(
  ncpId: string,
  payload: { week_start_date: string; conditions?: string[]; allergens?: string[] }
): Promise<MealPlan | { insufficient_recipes: true; count: number }> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/generate`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) return data;
  return data.data ?? data;
}

export interface MealPlanTemplate {
  id: number;
  name: string;
  description: string | null;
  goal_type: string | null;
  created_at: string;
}

export interface MealPlanTemplateDay {
  id: number;
  day_of_week: string;
  meal_type: string;
  quantity: string;
  unit: string;
  food_name: string | null;
  calories: number | null;
}

export interface MealPlanTemplateDetail extends MealPlanTemplate {
  days: MealPlanTemplateDay[];
}

export async function fetchMealPlanTemplates(): Promise<MealPlanTemplate[]> {
  const res = await fetch('/api/rnd/meal-plan-templates', { headers: { Accept: 'application/json' } });
  if (!res.ok) return [];
  return (await res.json()).data ?? [];
}

export async function fetchMealPlanTemplate(templateId: number): Promise<MealPlanTemplateDetail | null> {
  const res = await fetch(`/api/rnd/meal-plan-templates/${templateId}`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return null;
  return (await res.json()).data ?? null;
}

export async function deleteMealPlanTemplate(templateId: number): Promise<void> {
  const res = await fetch(`/api/rnd/meal-plan-templates/${templateId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok && res.status !== 204) throw new Error('Failed to delete template.');
}

export async function saveMealPlanAsTemplate(
  ncpId: string,
  planId: number,
  payload: { name: string; description?: string; goal_type?: string }
): Promise<MealPlanTemplate> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/save-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error('Failed to save template.');
  return (await res.json()).data;
}

export async function createPlanFromTemplate(
  ncpId: string,
  payload: { template_id: number; week_start_date: string }
): Promise<MealPlan> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/from-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error('Failed to create plan from template.');
  return (await res.json()).data;
}

export async function removeMealPlanItem(
  ncpId: string,
  planId: number,
  dayId: number,
  itemId: number
): Promise<void> {
  const res = await fetch(`${itemsBase(ncpId, planId, dayId)}/${itemId}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (!res.ok && res.status !== 204) throw new Error("Failed to remove meal plan item.");
}
