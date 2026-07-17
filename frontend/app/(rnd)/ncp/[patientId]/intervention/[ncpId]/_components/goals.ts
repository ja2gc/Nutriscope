// Pure intervention-goal catalog (no React) so it can be unit-tested and shared.
// disease_stage values MUST match the backend engine (NutritionPrescriptionService)
// and docs/logic/intervention-goals.md — stages drive stage-specific calculations.

export interface GoalOption {
  value: string;
  label: string;
  description: string;
  stages?: { value: string; label: string }[];
}

export const GOALS: GoalOption[] = [
  {
    value: "renal_diet",
    label: "Renal Diet",
    description: "CKD — restricts protein, sodium, potassium, phosphorus",
    stages: [
      { value: "stage_1", label: "Stage 1 (GFR ≥90)" },
      { value: "stage_2", label: "Stage 2 (GFR 60–89)" },
      { value: "stage_3", label: "Stage 3 (GFR 30–59)" },
      { value: "stage_4", label: "Stage 4 (GFR 15–29)" },
      { value: "stage_5_predialysis", label: "Stage 5 Pre-dialysis" },
      { value: "hemodialysis", label: "Hemodialysis" },
      { value: "peritoneal", label: "Peritoneal Dialysis" },
    ],
  },
  {
    value: "diabetic_control",
    label: "Diabetic Control",
    description: "DM — carbohydrate distribution, glycemic management",
    stages: [
      { value: "stage_1", label: "Stage 1 — Normal weight (T1/T2DM)" },
      { value: "stage_2", label: "Stage 2 — T2DM + overweight (BMI ≥23) · TEE−500" },
      { value: "stage_3", label: "Stage 3 — Coexisting CKD (protein-restricted)" },
    ],
  },
  {
    value: "cardiac_diet",
    label: "Cardiac Diet",
    description: "HTN / cardiac — sodium, fat, cholesterol restriction",
    stages: [
      { value: "mild", label: "Mild" },
      { value: "moderate", label: "Moderate" },
      { value: "severe", label: "Severe" },
    ],
  },
  {
    value: "weight_loss",
    label: "Weight Loss",
    description: "Caloric deficit, protein-sparing approach",
    stages: [
      { value: "overweight", label: "Overweight (BMI 23-24.9)" },
      { value: "class_1", label: "Obese Class I (BMI 25-29.9)" },
      { value: "class_2", label: "Obese Class II (BMI 30-34.9)" },
      { value: "class_3", label: "Obese Class II, severe (BMI >=35)" },
    ],
  },
  {
    value: "weight_gain",
    label: "Weight Gain",
    description: "Caloric surplus with stage-specific recommended targets",
    stages: [
      { value: "mild", label: "Mild (85–90% IBW)" },
      { value: "moderate", label: "Moderate (70–84% IBW)" },
      { value: "severe", label: "Severe (<70% IBW)" },
    ],
  },
  {
    value: "high_protein",
    label: "High Protein",
    description: "Post-surgery, burns, pressure injuries, low albumin",
    stages: [
      { value: "mild_stress", label: "Mild Stress (1.0–1.2 g/kg)" },
      { value: "moderate_stress", label: "Moderate Stress (1.2–1.5 g/kg)" },
      { value: "severe_stress", label: "Severe Stress (1.5–2.0 g/kg)" },
      { value: "burns", label: "Burns >20% BSA (1.5–2.0 g/kg)" },
    ],
  },
  {
    value: "liver_disease",
    label: "Liver Disease",
    description: "Cirrhosis stages, hepatic encephalopathy",
    stages: [
      { value: "compensated", label: "Compensated (no ascites)" },
      { value: "decompensated", label: "Decompensated (ascites)" },
      { value: "encephalopathy_grade_1_2", label: "Encephalopathy Grade I–II" },
      { value: "encephalopathy_grade_3_4", label: "Encephalopathy Grade III–IV" },
    ],
  },
  {
    value: "malnutrition",
    label: "Malnutrition",
    description: "High-calorie, high-protein nutrition support",
    stages: [
      { value: "moderate", label: "Moderate (risk score 2–3)" },
      { value: "severe", label: "Severe (risk score >3)" },
    ],
  },
  {
    value: "custom",
    label: "Custom Plan",
    description: "Manual nutrient targets set by RND",
  },
];
