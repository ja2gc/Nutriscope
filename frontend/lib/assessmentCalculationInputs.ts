export interface CalculationInputHelper {
  required: boolean;
  helper: string;
}

export const CALCULATION_INPUT_HELPERS = {
  weight: {
    required: true,
    helper: "Required for BMI, IBW, %IBW, BMR/TEE, fluid, and nutrition prescription.",
  },
  usual_weight: {
    required: true,
    helper: "Required for weight-loss/gain percentage and malnutrition risk scoring.",
  },
  height: {
    required: true,
    helper: "Required for BMI, Hamwi IBW, %IBW, BMR/TEE, and nutrition prescription.",
  },
  physical_activity_level: {
    required: true,
    helper: "Required for TEE-based goals: diabetes, cardiac, weight loss, non-severe weight gain, and custom preview.",
  },
  muac_mm: {
    required: false,
    helper: "Used for MUAC classification when available.",
  },
  waist_cm: {
    required: false,
    helper: "Used with hip circumference for waist-hip ratio.",
  },
  hip_cm: {
    required: false,
    helper: "Used with waist circumference for waist-hip ratio.",
  },
  weight_loss_period: {
    required: false,
    helper: "Helps interpret weight change severity over time.",
  },
  stress_factor: {
    required: false,
    helper: "Reserved for clinical context; high-protein prescription uses flat kcal/kg stress logic.",
  },
  pregnancy_lactation_status: {
    required: false,
    helper: "Applies PDRI energy and protein add-on when pregnant or lactating.",
  },
  edema_present: {
    required: false,
    helper: "Flags weight-based calculations as potentially unreliable.",
  },
} satisfies Record<string, CalculationInputHelper>;
