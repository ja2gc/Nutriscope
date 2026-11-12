import type { Recipe } from '@/services/foodLibraryService';
import type { NutrientSnapshot } from '@/services/mealPlanService';

export function recipeMenuSnapshot(recipe: Recipe): NutrientSnapshot {
  const yieldAmount = Number(recipe.servings);
  if (!(yieldAmount > 0)) throw new Error('Correct the Menu Item yield before adding it.');
  return {
    name: recipe.name, recipe_category: recipe.category, recipe_yield: yieldAmount,
    calories: Number(recipe.total_calories) / yieldAmount, protein: Number(recipe.total_protein) / yieldAmount,
    carbs: Number(recipe.total_carbs) / yieldAmount, fat: Number(recipe.total_fat) / yieldAmount,
    water_g: Number(recipe.total_water_g ?? 0) / yieldAmount, serving_size: 1, serving_unit: 'serving',
    micronutrients: Object.fromEntries(Object.entries(recipe.micronutrients ?? {}).map(([key, value]) => [key, Number(value) / yieldAmount])),
    prep_notes: recipe.prep_notes, prepared_portion_amount: recipe.prepared_portion_amount, prepared_portion_unit: recipe.prepared_portion_unit,
    recipe_original_ingredients: recipe.ingredients.map(row => ({ ...row, name: row.food_item?.name ?? 'Ingredient' })),
    recipe_ingredients: recipe.ingredients.map(row => ({ id: row.id, name: row.food_item?.name ?? 'Ingredient', quantity: Number(row.quantity), unit: row.unit })),
  };
}

type FoodFluidItem = {
  quantity: string | number;
  nutrient_snapshot: {
    serving_size: number;
    water_g?: number | null;
  } | null;
};

export function formatMealPlanAmount(value: number | string | null | undefined): string {
  const numeric = typeof value === "string" ? Number(value) : value ?? 0;
  if (!Number.isFinite(numeric)) return "0";
  if (numeric > 0 && numeric < 0.1) return "<0.1";
  return new Intl.NumberFormat("en-PH", { maximumFractionDigits: 1, useGrouping: false }).format(numeric);
}

export function foodFluidBalance(items: FoodFluidItem[], targetMl: number) {
  const foodFluidMl = Math.round(items.reduce((total, item) => {
    const snapshot = item.nutrient_snapshot;
    if (!snapshot?.water_g) return total;
    const quantity = Number(item.quantity);
    const scale = snapshot.serving_size > 0 ? quantity / snapshot.serving_size : 1;
    return total + snapshot.water_g * scale;
  }, 0));

  return {
    requiredFluidMl: targetMl,
    foodFluidMl,
    remainingMl: Math.max(0, Math.round(targetMl - foodFluidMl)),
  };
}
