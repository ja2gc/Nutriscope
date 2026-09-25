export interface CalculationInputHelper {
  required: boolean;
}

export const CALCULATION_INPUT_HELPERS = {
  weight: { required: true },
  usual_weight: { required: true },
  height: { required: true },
  physical_activity_level: { required: true },
  muac_mm: { required: false },
  waist_cm: { required: false },
  hip_cm: { required: false },
  weight_change_period_value: { required: false },
  weight_change_period_unit: { required: false },
  pregnancy_lactation_status: { required: false },
  edema_present: { required: false },
} satisfies Record<string, CalculationInputHelper>;
