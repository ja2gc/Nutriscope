import { CATALOG_UNIT_OPTIONS } from "./units";

const mass: Record<string, number> = { mg: 0.001, g: 1, gram: 1, grams: 1, kg: 1000, oz: 28.349523125, lb: 453.59237 };
const volume: Record<string, number> = { ml: 1, milliliter: 1, millilitre: 1, cc: 1, l: 1000, liter: 1000, litre: 1000, tsp: 4.92892, teaspoon: 4.92892, tbsp: 14.7868, tablespoon: 14.7868, cup: 236.588 };
const recipeUnits = [...CATALOG_UNIT_OPTIONS.filter((unit) => unit !== "sack"), "cup", "oz", "tbsp", "tsp"];
const normalize = (unit: string) => unit.trim().toLowerCase();

export function foodServiceUnitFactor(from: string, to: string): number | null {
  const source = normalize(from);
  const target = normalize(to);
  if (!source || !target) return null;
  if (source === target) return 1;
  if (source in mass && target in mass) return mass[source] / mass[target];
  if (source in volume && target in volume) return volume[source] / volume[target];
  return null;
}

export function compatibleRecipeUnits(baseUnit: string, autoGrocery: boolean): string[] {
  if (!autoGrocery) return [...new Set([baseUnit, ...recipeUnits].filter(Boolean))];
  return [...new Set([baseUnit, ...recipeUnits].filter((unit) => foodServiceUnitFactor(unit, baseUnit) !== null))];
}

export function foodServiceIngredientCost(
  item: { base_unit: string; unit_cost: number; include_in_generated_lists: boolean },
  quantity: number,
  recipeUnit: string,
): number {
  if (!item.include_in_generated_lists || !Number.isFinite(quantity) || quantity <= 0) return 0;
  const factor = foodServiceUnitFactor(recipeUnit, item.base_unit);
  return factor === null ? 0 : quantity * factor * item.unit_cost;
}
