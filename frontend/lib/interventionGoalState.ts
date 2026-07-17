import { microKeys, microLimitsFromRx } from "@/lib/nutritionCalculations";

export interface PrescriptionFormState {
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  micronutrient_limits: Record<string, { max?: number; min?: number; unit: string }>;
  displayed_nutrients: string[];
}

export interface GoalAutofillResult {
  energy_kcal: number;
  protein_g: number;
  carbs_g: number;
  fat_g: number;
  fluid_ml: number;
  sodium_max_mg?: number;
  fiber_g?: number;
  cholesterol_max_mg?: number;
  free_sugar_max_pct?: number;
}

export const emptyPrescriptionForm = (): PrescriptionFormState => ({
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
    .filter(([, limit]) => Number.isFinite(limit.min) || Number.isFinite(limit.max))
    .map(([key]) => key));
}

export function buildGoalPrescriptionForm(
  goalType: string,
  result: GoalAutofillResult | null,
): PrescriptionFormState {
  if (!result) {
    return emptyPrescriptionForm();
  }

  const micronutrient_limits = microLimitsFromRx(result, result.energy_kcal);
  const displayed_nutrients = nutrientKeysWithValues(micronutrient_limits);

  return {
    energy_kcal: String(result.energy_kcal),
    protein_g: String(result.protein_g),
    carbs_g: String(result.carbs_g),
    fat_g: String(result.fat_g),
    fluid_ml: String(result.fluid_ml),
    micronutrient_limits,
    displayed_nutrients,
  };
}
