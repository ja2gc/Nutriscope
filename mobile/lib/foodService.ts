import api from './api';
import { MOBILE_PAGE_SIZE, PaginatedResponse } from './pagination';

export interface MenuDay {
  id: number;
  day_of_week: string;
  meal_type: string;
  recipe_id: string | null;
  fs_item_id: string | null;
  estimate_population: number | null;
  servings_override: number | null;
  quantity: number | null;
  po_snapshot?: MenuSnapshot | null;
  po_snapshot_at?: string | null;
  po_snapshot_locked?: boolean;
  snapshot_purchase_order_id?: number | null;
  recipe?: { id: string; name: string; servings: number } | null;
  fs_item?: { id: string; name: string } | null;
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
  quantity?: number;
  total_quantity?: number;
  ingredient_usage?: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
}

export interface MenuCycle {
  id: string;
  name: string;
  is_active: boolean;
  status: string;
  week_start_date: string | null;
  days?: MenuDay[];
}

export interface MealPrepLog {
  id: string;
  service_date: string;
  served_population: number | null;
  population: number | null;
  status: string;
}

export const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
export const MEALS = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];
export const MEAL_LABELS: Record<string, string> = {
  breakfast: 'Breakfast', am_snack: 'AM Snack', lunch: 'Lunch', pm_snack: 'PM Snack', dinner: 'Dinner',
};

export async function listMenuCycles(page: number, active = false): Promise<PaginatedResponse<MenuCycle>> {
  const res = await api.get<PaginatedResponse<MenuCycle>>('/api/fss/menu-cycles', {
    params: { page, per_page: active ? 1 : MOBILE_PAGE_SIZE, ...(active ? { active: 1 } : {}) },
  });
  return res.data;
}

export async function getMenuCycle(id: string): Promise<MenuCycle> {
  const res = await api.get<{ data: MenuCycle }>(`/api/fss/menu-cycles/${id}`);
  return res.data.data;
}

export interface MenuSlotProfile {
  cycle_id: string;
  day: string;
  meal: string;
  source: 'master' | 'custom' | 'locked';
  locked: boolean;
  editable: boolean;
  name: string;
  reference_servings: number;
  planned_servings: number | null;
  purchase_estimate_set: boolean;
  prep_notes: string | null;
  ingredients: {
    fs_item_id: string | null;
    name: string;
    quantity: number;
    unit: string;
    scaled_quantity: number | null;
    scaled_cost: number | null;
    include_in_generated_lists: boolean;
  }[];
  total_cost: number | null;
  cost_per_head: number | null;
}

export async function getMenuSlotProfile(menuCycleId: string, day: string, meal: string): Promise<MenuSlotProfile> {
  const res = await api.get<{ data: MenuSlotProfile }>(
    `/api/fss/menu-cycles/${encodeURIComponent(menuCycleId)}/slots/${encodeURIComponent(day)}/${encodeURIComponent(meal)}`,
  );
  return res.data.data;
}

export async function listMealPrep(menuCycleId: string): Promise<MealPrepLog[]> {
  const res = await api.get<{ data: MealPrepLog[] }>('/api/fss/meal-prep-logs', {
    params: { menu_cycle_id: menuCycleId },
  });
  return res.data.data;
}

/** Backfill a service day's served population (FSS or RND). */
export async function setServedPopulation(menuCycleId: string, service_date: string, served_population: number): Promise<MealPrepLog> {
  const res = await api.patch<{ data: MealPrepLog }>(
    `/api/fss/menu-cycles/${menuCycleId}/served-population`,
    { service_date, served_population },
  );
  return res.data.data;
}
