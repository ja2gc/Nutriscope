import { foodServiceUnitFactor } from "./foodServiceUnits";

const massUnits = ["mg", "g", "kg", "oz", "lb"];
const volumeUnits = ["mL", "L", "tsp", "tbsp", "cup"];
export const CLINICAL_BASELINE_UNITS = [...massUnits, ...volumeUnits, "piece"];
export const CLINICAL_UNIT_HINT = "Mass: mg, g, kg, oz, lb. Volume: mL, L, tsp, tbsp, cup. Convert only within the same family. Piece and existing count units match only themselves. A new piece baseline needs known nutrients for one measured piece; never assume a gram or liquid equivalent.";

export function canonicalClinicalUnit(unit: string): string {
  const normalized = unit.trim().toLowerCase();
  const aliases: Record<string, string> = {
    gram: "g", grams: "g", milliliter: "mL", millilitre: "mL", cc: "mL", ml: "mL",
    liter: "L", litre: "L", l: "L", teaspoon: "tsp", tablespoon: "tbsp",
    pc: "piece", pcs: "piece", pieces: "piece",
  };
  return aliases[normalized] ?? normalized;
}

export function clinicalRecipeUnits(baselineUnit: string): string[] {
  const unit = canonicalClinicalUnit(baselineUnit);
  if (!unit) return [];
  if (massUnits.includes(unit)) return [...massUnits];
  if (volumeUnits.includes(unit)) return [...volumeUnits];
  return [unit];
}

export function clinicalIngredientFactor(quantity: number, unit: string, baselineSize: number, baselineUnit: string): number | null {
  if (!Number.isFinite(quantity) || quantity <= 0 || !Number.isFinite(baselineSize) || baselineSize <= 0) return null;
  const conversion = foodServiceUnitFactor(canonicalClinicalUnit(unit), canonicalClinicalUnit(baselineUnit));
  if (conversion === null) return null;
  const factor = quantity * conversion / baselineSize;
  return Number.isFinite(factor) && factor > 0 ? factor : null;
}

export function clinicalIngredientMicronutrients(rows: { food: { serving_size?: string | number | null; serving_unit?: string | null; micronutrients?: Record<string, number> | null } | null; quantity: string; unit: string }[]): Record<string, number> {
  const totals: Record<string, number> = {};
  for (const row of rows) {
    if (!row.food) continue;
    const factor = clinicalIngredientFactor(Number(row.quantity), row.unit, Number(row.food.serving_size), row.food.serving_unit ?? '');
    if (factor === null) continue;
    for (const [key, value] of Object.entries(row.food.micronutrients ?? {})) totals[key] = (totals[key] ?? 0) + value * factor;
  }
  return totals;
}
