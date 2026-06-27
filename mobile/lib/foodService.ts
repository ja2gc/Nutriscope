import api from './api';

export interface MenuDay {
  id: number;
  day_of_week: string;
  meal_type: string;
  recipe_id: number | null;
  fs_item_id: number | null;
  estimate_population: number | null;
  servings_override: number | null;
  quantity: number | null;
  recipe?: { id: number; name: string; servings: number } | null;
  fs_item?: { id: number; name: string } | null;
}

export interface MenuCycle {
  id: number;
  name: string;
  is_active: boolean;
  status: string;
  week_start_date: string | null;
  days?: MenuDay[];
}

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
  kind: string;
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

export interface MealPrepLog {
  id: number;
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

export async function listMenuCycles(): Promise<MenuCycle[]> {
  const res = await api.get<{ data: MenuCycle[] }>('/api/fss/menu-cycles');
  return res.data.data;
}

export async function getMenuCycle(id: number): Promise<MenuCycle> {
  const res = await api.get<{ data: MenuCycle }>(`/api/fss/menu-cycles/${id}`);
  return res.data.data;
}

export async function getRecipeProfile(recipeId: number, population: number): Promise<RecipeProfile> {
  const res = await api.get<{ data: RecipeProfile }>(
    `/api/fss/food-service-recipes/${recipeId}/profile`,
    { params: { population } },
  );
  return res.data.data;
}

export async function getFsItemProfile(fsItemId: number, population: number, quantity: number): Promise<FsItemProfile> {
  const res = await api.get<{ data: FsItemProfile }>(
    `/api/fss/fs-items/${fsItemId}/profile`,
    { params: { population, quantity } },
  );
  return res.data.data;
}

export async function listMealPrep(menuCycleId: number): Promise<MealPrepLog[]> {
  const res = await api.get<{ data: MealPrepLog[] }>('/api/fss/meal-prep-logs', {
    params: { menu_cycle_id: menuCycleId },
  });
  return res.data.data;
}

/** Backfill a service day's served population (FSS or RND). */
export async function setServedPopulation(menuCycleId: number, service_date: string, served_population: number): Promise<MealPrepLog> {
  const res = await api.patch<{ data: MealPrepLog }>(
    `/api/fss/menu-cycles/${menuCycleId}/served-population`,
    { service_date, served_population },
  );
  return res.data.data;
}
