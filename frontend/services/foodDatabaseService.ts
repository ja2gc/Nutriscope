// ─── Types ───────────────────────────────────────────────────────────────────

export interface FoodItem {
  id: number;
  name: string;
  category: string | null;
  usda_fdc_id: number | null;
  calories: string;
  protein: string | null;
  carbs: string | null;
  fat: string | null;
  micronutrients: Record<string, number>;
  allergens: string[];
  unit_price: string | null;
  serving_unit: string | null;
  serving_size: string | null;
  created_at: string;
  updated_at: string;
}

export interface RecipeIngredient {
  id: number;
  food_item_id: number;
  quantity: string;
  unit: string;
  food_item: {
    id: number;
    name: string;
    calories: string;
    protein: string | null;
    carbs: string | null;
    fat: string | null;
    serving_size: string | null;
    serving_unit: string | null;
  } | null;
}

export interface Recipe {
  id: number;
  rnd_user_id: number;
  name: string;
  category: string | null;
  prep_notes: string | null;
  servings: number | null;
  total_calories: string | null;
  total_protein: string | null;
  total_carbs: string | null;
  total_fat: string | null;
  cost: string | null;
  micronutrients: Record<string, number>;
  ingredients: RecipeIngredient[];
  created_at: string;
  updated_at: string;
}

export interface UsdaSearchResult {
  fdc_id: number;
  name: string;
  category: string | null;
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  links?: { first: string; last: string; prev: string | null; next: string | null };
  meta?: {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
  };
}

export type FoodItemPayload = {
  name: string;
  calories: number;
  category?: string | null;
  protein?: number | null;
  carbs?: number | null;
  fat?: number | null;
  allergens?: string[];
  micronutrients?: Record<string, number>;
  unit_price?: number | null;
  serving_unit?: string | null;
  serving_size?: number | null;
  usda_fdc_id?: number | null;
};

export type RecipeIngredientPayload = {
  food_item_id: number;
  quantity: number;
  unit: string;
};

export type RecipePayload = {
  name: string;
  category?: string | null;
  prep_notes?: string | null;
  servings?: number | null;
  ingredients: RecipeIngredientPayload[];
};

// ─── Food Item API ────────────────────────────────────────────────────────────

export async function fetchFoodItems(
  search?: string,
  category?: string,
  page: number = 1
): Promise<PaginatedResponse<FoodItem>> {
  const params = new URLSearchParams();
  if (search) params.append("search", search);
  if (category && category !== "all") params.append("category", category);
  params.append("page", page.toString());

  const res = await fetch(`/api/rnd/food-items?${params}`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to fetch food items.");
  }

  return res.json();
}

export async function fetchFoodItemById(id: number | string): Promise<FoodItem> {
  const res = await fetch(`/api/rnd/food-items/${id}`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to fetch food item.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function createFoodItem(payload: FoodItemPayload): Promise<FoodItem> {
  const res = await fetch("/api/rnd/food-items", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to create food item.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function updateFoodItem(
  id: number | string,
  payload: Partial<FoodItemPayload>
): Promise<FoodItem> {
  const res = await fetch(`/api/rnd/food-items/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to update food item.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function deleteFoodItem(id: number | string): Promise<void> {
  const res = await fetch(`/api/rnd/food-items/${id}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (!res.ok && res.status !== 204) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to delete food item.");
  }
}

// ─── Recipe API ───────────────────────────────────────────────────────────────

export async function fetchRecipes(
  search?: string,
  category?: string,
  page: number = 1
): Promise<PaginatedResponse<Recipe>> {
  const params = new URLSearchParams();
  if (search) params.append("search", search);
  if (category && category !== "all") params.append("category", category);
  params.append("page", page.toString());

  const res = await fetch(`/api/rnd/recipes?${params}`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to fetch recipes.");
  }

  return res.json();
}

export async function fetchRecipeById(id: number | string): Promise<Recipe> {
  const res = await fetch(`/api/rnd/recipes/${id}`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to fetch recipe.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function createRecipe(payload: RecipePayload): Promise<Recipe> {
  const res = await fetch("/api/rnd/recipes", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to create recipe.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function updateRecipe(
  id: number | string,
  payload: Partial<RecipePayload>
): Promise<Recipe> {
  const res = await fetch(`/api/rnd/recipes/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to update recipe.");
  }

  const data = await res.json();
  return data.data ?? data;
}

export async function deleteRecipe(id: number | string): Promise<void> {
  const res = await fetch(`/api/rnd/recipes/${id}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (!res.ok && res.status !== 204) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to delete recipe.");
  }
}

// ─── USDA API ─────────────────────────────────────────────────────────────────

export async function searchUsda(query: string): Promise<UsdaSearchResult[]> {
  const res = await fetch(`/api/rnd/usda/search?query=${encodeURIComponent(query)}`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "USDA search failed.");
  }

  const data = await res.json();
  return data.data ?? [];
}

export async function importUsdaFood(fdcId: number): Promise<FoodItem> {
  const res = await fetch(`/api/rnd/usda/import/${fdcId}`, {
    method: "POST",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Import failed.");
  }

  const data = await res.json();
  return data.data ?? data;
}
