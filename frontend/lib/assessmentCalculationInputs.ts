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
  weight_loss_period: { required: false },
  stress_factor: { required: false },
  pregnancy_lactation_status: { required: false },
  edema_present: { required: false },
} satisfies Record<string, CalculationInputHelper>;
