import type { Recipe, RecipePayload } from '@/services/foodLibraryService';
import type { NutrientSnapshot } from '@/services/mealPlanService';
import { recallItemFromRecipe } from './foodRecall';
import { foodFluidBalance } from './mealPlanNutrition';

export interface FormulaItem {
  id: string;
  recipe_id: string | null;
  portion_multiplier: number;
  nutrient_snapshot: NutrientSnapshot;
  original_snapshot: NutrientSnapshot;
  source_template_id?: string;
  source_template_item_id?: string;
  source_intervention_id?: string;
  source_intervention_item_id?: string;
  new_formula?: RecipePayload;
  reset_recipe?: boolean;
}

export function formulaNutrients(item: FormulaItem) {
  const snapshot = item.nutrient_snapshot;
  const factor = Number(snapshot.recipe_yield) * item.portion_multiplier;
  return {
    calories: snapshot.calories * factor, protein: snapshot.protein * factor,
    carbs: snapshot.carbs * factor, fat: snapshot.fat * factor,
    water_g: snapshot.water_g == null ? null : snapshot.water_g * factor,
    fluid_ml: snapshot.water_g == null ? null : foodFluidBalance([{ quantity: factor, nutrient_snapshot: { serving_size: 1, water_g: snapshot.water_g } }], 0).foodFluidMl,
    micronutrients: Object.fromEntries(Object.entries(snapshot.micronutrients ?? {}).map(([key, value]) => [key, value * factor])),
  };
}

export function formatFormulaAmount(value: number): string {
  return new Intl.NumberFormat('en-PH', { maximumFractionDigits: 12, useGrouping: false }).format(value);
}

export function formulaFromRecipe(recipe: Recipe): FormulaItem {
  const item = recallItemFromRecipe(recipe);
  if (!item) throw new Error('Correct the formula yield and measured ingredient baselines before adding it.');
  return { id: crypto.randomUUID(), recipe_id: recipe.id, portion_multiplier: 1,
    nutrient_snapshot: item.nutrient_snapshot, original_snapshot: structuredClone(item.nutrient_snapshot) };
}
export function formulaItemsPayload(items: FormulaItem[]) {
  return items.map(({ id, recipe_id, portion_multiplier, nutrient_snapshot, source_template_id, source_template_item_id, source_intervention_id, source_intervention_item_id, new_formula, reset_recipe }) => ({
    id, recipe_id, portion_multiplier, ...(source_template_id ? { source_template_id, source_template_item_id } : {}),
    ...(source_intervention_id ? { source_intervention_id, source_intervention_item_id } : {}),
    ...(new_formula ? { new_formula } : {}), ...(reset_recipe ? { reset_recipe } : {}),
    ingredients: (nutrient_snapshot.recipe_original_ingredients ?? []).map(row => ({ food_item_id: row.food_item?.id ?? '', quantity: Number(row.quantity), unit: row.unit })),
  }));
}
