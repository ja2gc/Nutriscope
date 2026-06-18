import { apiFetch } from "@/lib/apiFetch";

export const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as const;
export const MEALS = ["breakfast", "am_snack", "lunch", "pm_snack", "dinner"] as const;
export type Day = (typeof DAYS)[number];
export type Meal = (typeof MEALS)[number];

export const MEAL_LABELS: Record<Meal, string> = {
  breakfast: "Breakfast",
  am_snack: "AM Snack",
  lunch: "Lunch",
  pm_snack: "PM Snack",
  dinner: "Dinner",
};

export interface MenuDay {
  id?: number;
  day_of_week: Day;
  meal_type: Meal;
  recipe_id: number | null;
  fs_item_id: number | null;
  quantity: number;
  servings_override: number | null;
  estimate_population: number | null; // headcount for this day (drives scaling)
  is_event: boolean;
  event_allocation: number | null;
  recipe?: { id: number; name: string; servings: number; cost: string } | null;
  fs_item?: { id: number; name: string } | null;
}

export interface MenuCycle {
  id: number;
  name: string;
  cycle_days: number;
  status: string;
  is_active: boolean;
  week_start_date: string | null;
  days?: MenuDay[];
}

export interface CycleListItem {
  id: number;
  name: string;
  status: string;
  is_active: boolean;
  updated_at: string;
}

export interface ComputeDay { cost: number; cost_per_head: number; budget_status?: "ok" | "warning" | "over" }
export interface ComputeResult {
  population: number; // total head-days across the cycle
  total_cost: number;
  cost_per_head: number;
  budget_per_head_day: number | null; // cap from the Budget covering this cycle's week
  within_budget: boolean | null;
  days: Record<string, ComputeDay>;
  ingredient_usage: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
}

export interface RecipeOption { id: number; name: string; category: string | null; servings: number; cost?: string }

export interface RecipeProfile {
  recipe_id: number;
  name: string;
  servings: number;
  population: number;
  total_cost: number;
  cost_per_head: number;
  ingredient_usage: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
}

/** Per-ingredient cost breakdown for a recipe scaled to a day's headcount. */
export async function getRecipeProfile(recipeId: number, population: number): Promise<RecipeProfile> {
  const res = await apiFetch(`/api/fss/food-service-recipes/${recipeId}/profile?population=${population}`);
  return json<RecipeProfile>(res, "Failed to load recipe profile.");
}

export interface SaveCyclePayload {
  name: string;
  cycle_days?: number;
  week_start_date?: string | null;
  days?: Array<Pick<MenuDay, "day_of_week" | "meal_type" | "recipe_id" | "fs_item_id" | "quantity" | "estimate_population" | "is_event" | "event_allocation">>;
}

export interface TemplateListItem { id: number; name: string; description: string | null; cycle_days: number; days_count: number; updated_at: string }
export interface TemplateDetail {
  id: number; name: string; description: string | null; cycle_days: number;
  days: Array<{ day_of_week: Day; meal_type: Meal; recipe_id: number | null; fs_item_id: number | null; quantity: number; recipe?: { id: number; name: string } | null }>;
}

async function json<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

// ─── Recipes (picker source) ──────────────────────────────────────────────────
export async function listRecipeOptions(): Promise<RecipeOption[]> {
  const res = await apiFetch("/api/fss/food-service-recipes");
  if (!res.ok) return [];
  return (await res.json()).data ?? [];
}

// ─── Cycles ───────────────────────────────────────────────────────────────────
export async function listCycles(): Promise<CycleListItem[]> {
  const res = await apiFetch("/api/fss/menu-cycles");
  return json<CycleListItem[]>(res, "Failed to load menu cycles.");
}

export async function getCycle(id: number): Promise<MenuCycle> {
  const res = await apiFetch(`/api/fss/menu-cycles/${id}`);
  return json<MenuCycle>(res, "Failed to load menu cycle.");
}

export async function saveCycle(id: number | null, payload: SaveCyclePayload): Promise<MenuCycle> {
  const res = await apiFetch(id ? `/api/fss/menu-cycles/${id}` : "/api/fss/menu-cycles", {
    method: id ? "PATCH" : "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return json<MenuCycle>(res, "Failed to save menu cycle.");
}

export async function deleteCycle(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/menu-cycles/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete menu cycle.");
}

export interface CostToday {
  cycle: string;
  date: string;
  weekday: string;
  cost_per_head: number | null; // actual cost to make today's menu, per head
  limit_per_head: number | null; // settable cap
  within_budget: boolean | null;
  population: number;
  has_menu_today: boolean;
}

export async function getCostToday(): Promise<CostToday | null> {
  const res = await apiFetch("/api/fss/menu-cycles/cost-today");
  if (!res.ok) return null;
  return (await res.json()).data ?? null;
}

export async function computeCycle(id: number): Promise<ComputeResult> {
  const res = await apiFetch(`/api/fss/menu-cycles/${id}/compute`);
  return json<ComputeResult>(res, "Failed to compute cycle.");
}

export async function activateCycle(id: number): Promise<MenuCycle> {
  const res = await apiFetch(`/api/fss/menu-cycles/${id}/activate`, { method: "PATCH" });
  return json<MenuCycle>(res, "Failed to activate.");
}

export async function saveCycleAsTemplate(id: number, name: string, description?: string): Promise<{ id: number }> {
  const res = await apiFetch(`/api/fss/menu-cycles/${id}/save-template`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, description }),
  });
  return json<{ id: number }>(res, "Failed to save template.");
}

// ─── Templates ──────────────────────────────────────────────────────────────────
export async function listTemplates(): Promise<TemplateListItem[]> {
  const res = await apiFetch("/api/fss/menu-cycle-templates");
  return json<TemplateListItem[]>(res, "Failed to load templates.");
}

export async function getTemplate(id: number): Promise<TemplateDetail> {
  const res = await apiFetch(`/api/fss/menu-cycle-templates/${id}`);
  return json<TemplateDetail>(res, "Failed to load template.");
}

export async function instantiateTemplate(id: number, payload: { name?: string; week_start_date?: string | null }): Promise<{ id: number; name: string }> {
  const res = await apiFetch(`/api/fss/menu-cycle-templates/${id}/instantiate`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return json<{ id: number; name: string }>(res, "Failed to create cycle from template.");
}

export async function deleteTemplate(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/menu-cycle-templates/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete template.");
}
