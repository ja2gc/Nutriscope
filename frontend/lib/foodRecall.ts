import { canonicalClinicalUnit, clinicalIngredientFactor, clinicalRecipeNutrients } from './clinicalUnits';
import type { NutrientSnapshot } from '@/services/mealPlanService';
import type { FoodItem, Recipe } from '@/services/foodLibraryService';
import { ALL_MICROS } from './nutritionCalculations';

export const INTAKE_MACROS = [
  { key: 'energy_kcal', label: 'Energy', unit: 'kcal', max: 10000 },
  { key: 'protein_g', label: 'Protein', unit: 'g', max: 1000 },
  { key: 'carbs_g', label: 'Carbohydrate', unit: 'g', max: 1000 },
  { key: 'fat_g', label: 'Fat', unit: 'g', max: 1000 },
  { key: 'fluid_ml', label: 'Fluid', unit: 'mL', max: 10000 },
];
export const INTAKE_FIELDS = [...INTAKE_MACROS, ...ALL_MICROS.map(field => ({ ...field, key: `micro_${field.key}`, max: 100000 }))];

export type RecallIngredient = { food_item_id: string; quantity: number; unit: string };
export type FoodRecallItem = {
  id: string;
  food_item_id: string | null;
  recipe_id: string | null;
  quantity: number;
  unit: string;
  nutrient_snapshot: NutrientSnapshot;
  ingredients?: RecallIngredient[];
};
export type FoodRecallPlan = {
  days_count: number;
  displayed_micros: string[];
  days: { kind?: 'weekday' | 'weekend'; items: FoodRecallItem[] }[];
};

export function emptyFoodRecall(days = 0, micros: string[] = []): FoodRecallPlan {
  return { days_count: days, displayed_micros: [...micros], days: Array.from({ length: days }, () => ({ items: [] })) };
}

export function foodRecallDayLabel(plan: FoodRecallPlan, index: number) {
  const kind = plan.days[index]?.kind ?? 'weekday';
  const number = plan.days.slice(0, index + 1).filter(day => (day.kind ?? 'weekday') === kind).length;
  return `${kind === 'weekday' ? 'Weekday' : 'Weekend'} ${number}`;
}

export function foodRecallCoverage(plan?: FoodRecallPlan | null) {
  if (!plan?.days.length) return null;
  const weekdays = plan.days.filter(day => day.kind !== 'weekend').length;
  const weekends = plan.days.length - weekdays;
  return [weekdays ? `${weekdays} ${weekdays === 1 ? 'weekday' : 'weekdays'}` : '', weekends ? `${weekends} ${weekends === 1 ? 'weekend' : 'weekends'}` : ''].filter(Boolean).join(' · ');
}

export function addFoodRecallDay(plan: FoodRecallPlan, kind: 'weekday' | 'weekend') {
  const index = kind === 'weekend' ? plan.days.length : plan.days.findIndex(day => day.kind === 'weekend');
  const inserted = index < 0 ? plan.days.length : index;
  const days = [...plan.days];
  days.splice(inserted, 0, { kind, items: [] });
  return { plan: { ...plan, days_count: days.length, days }, index: inserted };
}

export function foodRecallPayload(plan: FoodRecallPlan | null | undefined) {
  if (!plan) return plan;
  return { days_count: plan.days_count, displayed_micros: plan.displayed_micros,
    days: plan.days.map(day => ({ ...(day.kind ? { kind: day.kind } : {}), items: day.items.map(({ id, food_item_id, recipe_id, quantity, unit, ingredients }) => ({
      id, food_item_id, recipe_id, quantity, unit, ...(ingredients ? { ingredients } : {}),
    })) })),
  };
}

export function foodRecallItemFactor(item: FoodRecallItem) {
  const snapshot = item.nutrient_snapshot;
  return item.recipe_id && item.unit !== 'serving'
    ? clinicalIngredientFactor(item.quantity, item.unit, Number(snapshot.prepared_portion_amount), snapshot.prepared_portion_unit ?? '')
    : clinicalIngredientFactor(item.quantity, item.unit, snapshot.serving_size, snapshot.serving_unit);
}

export function foodRecallItemNutrients(item: FoodRecallItem) {
  const snapshot = item.nutrient_snapshot;
  const factor = foodRecallItemFactor(item);
  if (factor === null) return null;
  return {
    calories: snapshot.calories * factor, protein: snapshot.protein * factor, carbs: snapshot.carbs * factor, fat: snapshot.fat * factor,
    water_g: snapshot.water_g == null ? null : snapshot.water_g * factor,
    micronutrients: Object.fromEntries(Object.entries(snapshot.micronutrients ?? {}).map(([key, value]) => [key, value * factor])),
  };
}

export function foodRecallDayTotals(items: FoodRecallItem[]) {
  const totals = { calories: 0, protein: 0, carbs: 0, fat: 0, water_g: 0, micronutrients: {} as Record<string, number> };
  for (const item of items) {
    const nutrients = foodRecallItemNutrients(item);
    if (!nutrients) return null;
    for (const key of ['calories', 'protein', 'carbs', 'fat', 'water_g'] as const) totals[key] += nutrients[key] ?? 0;
    for (const [key, value] of Object.entries(nutrients.micronutrients)) totals.micronutrients[key] = (totals.micronutrients[key] ?? 0) + value;
  }
  return totals;
}

export function foodRecallAverage(plan: FoodRecallPlan) {
  if (!plan.days.length || plan.days.length !== plan.days_count || plan.days.some(day => !day.items.length)) return null;
  const total = foodRecallDayTotals(plan.days.flatMap(day => day.items));
  if (!total) return null;
  return { calories: total.calories / plan.days_count, protein: total.protein / plan.days_count,
    carbs: total.carbs / plan.days_count, fat: total.fat / plan.days_count, water_g: total.water_g / plan.days_count,
    micronutrients: Object.fromEntries(Object.entries(total.micronutrients).map(([key, value]) => [key, value / plan.days_count])),
  };
}

export function recipeRecallSnapshot(baseline: NutrientSnapshot, ingredients: RecallIngredient[]): NutrientSnapshot | null {
  const frozen = baseline.recipe_original_ingredients ?? [];
  const rows = ingredients.map((row, index) => {
    const source = frozen.find(original => original.food_item?.id === row.food_item_id);
    return source ? { ...source, id: index + 1, quantity: row.quantity, unit: row.unit } : null;
  });
  const totals = clinicalRecipeNutrients(rows.map(row => ({ quantity: row?.quantity ?? 0, unit: row?.unit ?? '', food: row?.food_item ?? null })), baseline.recipe_yield ?? 0);
  if (!totals || rows.some(row => !row)) return null;
  const measured = rows.filter(row => row !== null);
  return { ...baseline, ...totals, serving_size: 1, serving_unit: 'serving',
    recipe_original_ingredients: measured,
    recipe_ingredients: measured.map(row => ({ id: row.id, name: row.name, quantity: row.quantity, unit: row.unit })),
  };
}

export function recallItemFromFood(food: FoodItem): FoodRecallItem {
  return { id: crypto.randomUUID(), food_item_id: food.id, recipe_id: null,
    quantity: Number(food.serving_size), unit: canonicalClinicalUnit(food.serving_unit ?? ''), nutrient_snapshot: {
      name: food.name, calories: Number(food.calories), protein: Number(food.protein), carbs: Number(food.carbs), fat: Number(food.fat),
      water_g: food.water_g, micronutrients: food.micronutrients ?? {}, serving_size: Number(food.serving_size), serving_unit: food.serving_unit ?? '',
    } };
}

export function recallItemFromRecipe(recipe: Recipe): FoodRecallItem | null {
  const baseline: NutrientSnapshot = { name: recipe.name, calories: 0, protein: 0, carbs: 0, fat: 0, micronutrients: {},
    serving_size: 1, serving_unit: 'serving', recipe_yield: recipe.servings ?? 0, prep_notes: recipe.prep_notes,
    prepared_portion_amount: recipe.prepared_portion_amount, prepared_portion_unit: recipe.prepared_portion_unit,
    recipe_original_ingredients: recipe.ingredients.map(row => ({ ...row, quantity: Number(row.quantity), name: row.food_item?.name ?? '' })),
  };
  const ingredients = recipe.ingredients.map(row => ({ food_item_id: row.food_item?.id ?? '', quantity: Number(row.quantity), unit: row.unit }));
  const snapshot = recipeRecallSnapshot(baseline, ingredients);
  if (!snapshot) return null;
  return { id: crypto.randomUUID(), recipe_id: recipe.id, food_item_id: null, quantity: 1, unit: 'serving', nutrient_snapshot: snapshot };
}
