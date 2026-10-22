import { microKeys, microLimitsFromRx } from "@/lib/nutritionCalculations";
import type { CalculationOptions, CalculationSnapshot } from "@/services/interventionService";

export interface PrescriptionFormState {
  recalculate?: boolean;
  energy_adjustment_mode?: "none" | "surplus" | "deficit";
  energy_adjustment_kcal?: string;
  fluid_mode?: "estimated" | "restricted" | null;
  calculation_options?: CalculationOptions;
  calculation_snapshot?: CalculationSnapshot | null;
  weight_target?: { target?: number | string; min?: number | string; max?: number | string };
  nutrient_operators?: Record<string, 'min' | 'max'>;
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  micronutrient_limits: Record<string, { target?: number; max?: number; min?: number; unit: string }>;
  displayed_nutrients: string[];
}

export function weightTargetError(weight: PrescriptionFormState['weight_target']): string | null {
  if (!weight) return null;
  const fields = weight.min != null || weight.max != null ? [weight.min, weight.max] : [weight.target];
  if (fields.some(value => !/^\d{1,3}(?:\.\d)?$/.test(String(value ?? '')) || Number(value) < 1 || Number(value) > 400)) {
    return 'Enter target weight from 1 to 400 kg, using at most one decimal place.';
  }
  return fields.length === 2 && Number(fields[0]) > Number(fields[1]) ? 'Target weight minimum must not exceed maximum.' : null;
}

export interface GoalAutofillResult {
  energy_kcal: number | null;
  protein_g: number | null;
  carbs_g: number | null;
  fat_g: number | null;
  fluid_ml: number | null;
  fluid_mode?: "estimated" | "restricted";
  nutrient_operators?: Record<string, "min" | "max">;
  calculation_snapshot?: CalculationSnapshot;
  sodium_max_mg?: number;
  fiber_g?: number;
  cholesterol_max_mg?: number;
  free_sugar_max_pct?: number;
}

export const emptyPrescriptionForm = (): PrescriptionFormState => ({
  energy_adjustment_mode: "none", energy_adjustment_kcal: "", fluid_mode: "estimated",
  calculation_options: {}, calculation_snapshot: null,
  energy_kcal: "",
  protein_g: "",
  carbs_g: "",
  fat_g: "",
  fluid_ml: "",
  micronutrient_limits: {},
  displayed_nutrients: [],
});

export function nutrientKeysWithValues(
  limits: PrescriptionFormState["micronutrient_limits"],
): string[] {
  return microKeys(Object.entries(limits)
    .filter(([, limit]) => Number.isFinite(limit.target) || Number.isFinite(limit.min) || Number.isFinite(limit.max))
    .map(([key]) => key));
}

export function buildGoalPrescriptionForm(
  goalType: string,
  result: GoalAutofillResult | null,
): PrescriptionFormState {
  if (!result) {
    return emptyPrescriptionForm();
  }

  const micronutrient_limits = microLimitsFromRx(result as Parameters<typeof microLimitsFromRx>[0], result.energy_kcal ?? 0);
  const displayed_nutrients = nutrientKeysWithValues(micronutrient_limits);

  return {
    energy_kcal: result.energy_kcal == null ? "" : String(result.energy_kcal),
    protein_g: result.protein_g == null ? "" : String(result.protein_g),
    carbs_g: result.carbs_g == null ? "" : String(result.carbs_g),
    fat_g: result.fat_g == null ? "" : String(result.fat_g),
    fluid_ml: result.fluid_ml == null ? "" : String(result.fluid_ml),
    fluid_mode: result.fluid_mode,
    nutrient_operators: result.nutrient_operators,
    calculation_snapshot: result.calculation_snapshot,
    micronutrient_limits,
    displayed_nutrients,
  };
}
