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
