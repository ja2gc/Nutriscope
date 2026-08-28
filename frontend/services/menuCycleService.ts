import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

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
  po_snapshot?: MenuSnapshot | null;
  po_snapshot_at?: string | null;
  po_snapshot_locked?: boolean;
  has_recipe_override?: boolean;
  recipe?: { id: number; name: string; servings: number; cost: string } | null;
  fs_item?: { id: number; name: string } | null;
}

export interface MenuSnapshot {
  recipe_id?: number;
  fs_item_id?: number;
  name: string;
  prep_notes?: string | null;
  servings?: number;
  population?: number;
  total_cost?: number;
  cost_per_head?: number;
  unit?: string;
  total_quantity?: number;
  ingredient_usage?: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
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
  week_start_date: string | null;
  plan_days?: Partial<Record<Day, boolean>>;
  days_count?: number;
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

export interface FsItemOption { id: number; name: string; category: string | null; unit: string; unit_cost: number }

export interface RecipeProfile {
  recipe_id: number;
  name: string;
  prep_notes: string | null;
  servings: number;
  population: number;
  total_cost: number;
  cost_per_head: number;
  ingredient_usage: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
}

export interface FsItemProfile {
  id: number;
  fs_item_id: number;
  name: string;
  kind: "ingredient";
  category: string | null;
  unit: string;
  unit_cost: number;
  quantity: number;
  population: number;
  servings: number;
  total_quantity: number;
  total_cost: number;
  cost_per_head: number;
  prep_notes: string | null;
  formula: string;
  ingredient_usage: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
}

export type MenuSlotProfile = RecipeProfile | FsItemProfile;

export interface MenuSlotIngredient {
  fs_item_id: string;
  name: string;
  quantity: number;
  unit: string;
  scaled_quantity: number | null;
  scaled_cost: number | null;
  include_in_generated_lists: boolean;
}

export interface MenuSlotRecipe {
  cycle_id: string;
  day: Day;
  meal: Meal;
  source: "master" | "custom" | "locked";
  locked: boolean;
  editable: boolean;
  name: string;
  reference_servings: number;
  planned_servings: number | null;
  purchase_estimate_set: boolean;
  prep_notes: string | null;
  ingredients: MenuSlotIngredient[];
  total_cost: number | null;
  cost_per_head: number | null;
  baseline_total_cost: number;
}

export interface UpdateMenuSlotRecipePayload {
  name: string;
  reference_servings: number;
  planned_servings?: number | null;
  prep_notes: string | null;
  ingredients: Array<Pick<MenuSlotIngredient, "fs_item_id" | "quantity" | "unit">>;
}

export function scaledIngredientQuantity(quantity: number, referenceServings: number, plannedServings: number): number {
  return quantity * plannedServings / Math.max(1, referenceServings);
}

function slotPath(cycleId: string | number, day: Day, meal: Meal): string {
  return `/api/fss/menu-cycles/${cycleId}/slots/${day}/${meal}`;
}

export async function getMenuSlotRecipe(cycleId: string | number, day: Day, meal: Meal): Promise<MenuSlotRecipe> {
  return json<MenuSlotRecipe>(await apiFetch(slotPath(cycleId, day, meal)), "Failed to load menu item details.");
}

export async function updateMenuSlotRecipe(cycleId: string | number, day: Day, meal: Meal, payload: UpdateMenuSlotRecipePayload): Promise<MenuSlotRecipe> {
  return json<MenuSlotRecipe>(await apiFetch(slotPath(cycleId, day, meal), {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to save menu item details.");
}

export async function restoreMenuSlotRecipe(cycleId: string | number, day: Day, meal: Meal): Promise<MenuSlotRecipe> {
  return json<MenuSlotRecipe>(await apiFetch(slotPath(cycleId, day, meal), { method: "DELETE" }), "Failed to restore the original recipe.");
}

export function menuSnapshotToProfile(snapshot: MenuSnapshot): RecipeProfile {
  const population = Number(snapshot.population ?? 0);
  const totalCost = Number(snapshot.total_cost ?? 0);
  const ingredientUsage = snapshot.ingredient_usage ?? (snapshot.total_quantity != null ? [{
    fs_item_id: snapshot.fs_item_id ?? 0,
    name: snapshot.name,
    unit: snapshot.unit ?? "",
    quantity: Number(snapshot.total_quantity),
    cost: totalCost,
  }] : []);

  return {
    recipe_id: snapshot.recipe_id ?? 0,
    name: snapshot.name,
    prep_notes: snapshot.prep_notes ?? null,
    servings: Number(snapshot.servings ?? population),
    population,
    total_cost: totalCost,
    cost_per_head: Number(snapshot.cost_per_head ?? (population > 0 ? totalCost / population : 0)),
    ingredient_usage: ingredientUsage,
  };
}

/** Per-ingredient cost breakdown for a recipe scaled to a day's headcount. */
export async function getRecipeProfile(recipeId: number, population: number): Promise<RecipeProfile> {
  const res = await apiFetch(`/api/fss/food-service-recipes/${recipeId}/profile?population=${population}`);
  return json<RecipeProfile>(res, "Failed to load recipe profile.");
}

export async function getFsItemProfile(fsItemId: number, population: number, quantity = 1): Promise<FsItemProfile> {
  const qs = new URLSearchParams({ population: String(population), quantity: String(quantity) });
  const res = await apiFetch(`/api/fss/fs-items/${fsItemId}/profile?${qs}`);
  return json<FsItemProfile>(res, "Failed to load item profile.");
}

export interface SaveCyclePayload {
  name?: string;
  cycle_days?: number;
  week_start_date?: string | null;
  days?: Array<Pick<MenuDay, "day_of_week" | "meal_type" | "recipe_id" | "fs_item_id" | "quantity" | "servings_override" | "estimate_population" | "is_event" | "event_allocation">>;
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
export async function listRecipeOptions(search: string): Promise<RecipeOption[]> {
  const params = new URLSearchParams({ search, per_page: "5" });
  const res = await apiFetch(`/api/fss/food-service-recipes?${params}`);
  if (!res.ok) return [];
  return (await res.json()).data ?? [];
}

// ─── Single catalog items (picker source) ─────────────────────────────────────
export async function listFsItemOptions(search: string): Promise<FsItemOption[]> {
  const params = new URLSearchParams({ search, per_page: "5" });
  const res = await apiFetch(`/api/fss/fs-items?${params}`);
  if (!res.ok) return [];
  return (await res.json()).data ?? [];
}

// ─── Cycles ───────────────────────────────────────────────────────────────────
export async function listCycles(page = 1): Promise<{ data: CycleListItem[]; meta: PaginationMeta }> {
  const res = await apiFetch(`/api/fss/menu-cycles?page=${page}&per_page=10`);
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? "Failed to load menu cycles.");
  return { data: body.data ?? [], meta: body.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
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

export interface PendingPo {
  id: number;
  po_number: string | null;
  procurement_track: "food" | "supplies";
  waiting_on: string[]; // e.g. ["receipts", "served_population"]
}

export interface FssDashboardSummary {
  meals_to_log_today: number;
  pending_pos: PendingPo[];
  pending_pos_count: number;
  today_service: Array<{ meal_type: string; name: string; prepped: boolean; has_shortfall: boolean }>;
  active_cycle: { id: number; name: string; activation_date: string | null; service_day_count: number } | null;
}

export async function getFssDashboard(): Promise<FssDashboardSummary | null> {
  const res = await apiFetch("/api/fss/dashboard/summary");
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
export async function listTemplates(page = 1): Promise<{ data: TemplateListItem[]; meta: PaginationMeta }> {
  const res = await apiFetch(`/api/fss/menu-cycle-templates?page=${page}&per_page=10`);
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? "Failed to load templates.");
  return { data: body.data ?? [], meta: body.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
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
