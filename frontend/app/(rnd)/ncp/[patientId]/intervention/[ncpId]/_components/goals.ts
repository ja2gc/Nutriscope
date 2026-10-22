export interface GoalOption {
  value: string;
  label: string;
  description: string;
  stages?: { value: string; label: string }[];
}

export const GOALS: GoalOption[] = [
  { value: "general_diet", label: "General Diet", description: "Balanced nutrition and individual energy needs" },
  { value: "diabetic_control", label: "Diabetic Control", description: "Carbohydrate portions and glucose management",
    stages: [{ value: "type_1", label: "Type 1 Diabetes" }, { value: "type_2", label: "Type 2 Diabetes" }] },
  { value: "renal_diet", label: "Renal Diet", description: "Protein, minerals and fluids based on treatment",
    stages: [{ value: "ckd_1_2", label: "CKD Stages 1–2" }, { value: "ckd_3_5_non_dialysis", label: "CKD Stages 3–5 Without Dialysis" }, { value: "hemodialysis", label: "Hemodialysis" }] },
  { value: "cardiac_diet", label: "Cardiac Diet", description: "Heart health and prescribed sodium or fluid limits",
    stages: [{ value: "hypertension", label: "Hypertension" }, { value: "dyslipidemia", label: "Dyslipidemia" }, { value: "heart_failure", label: "Heart Failure" }] },
  { value: "high_protein", label: "High Protein", description: "Assessed protein and energy needs for recovery",
    stages: [{ value: "postoperative_recovery", label: "Postoperative Recovery" }, { value: "wound_healing", label: "Wound Healing" }] },
  { value: "liver_disease", label: "Liver Disease Diet", description: "Cirrhosis care based on nutritional status",
    stages: [{ value: "compensated", label: "Compensated Cirrhosis" }, { value: "decompensated", label: "Decompensated Cirrhosis" }] },
  { value: "custom", label: "Custom Plan", description: "Individual targets entered by the RND" },
];

export const LEGACY_GOALS: GoalOption[] = [
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



export function goalsForPatient(isPediatric: boolean): GoalOption[] {
  return isPediatric ? GOALS.filter(goal => ["general_diet", "diabetic_control", "custom"].includes(goal.value)) : GOALS;
}

export function goalLabelFor(value?: string | null): string | undefined {
  return GOALS.find(goal => goal.value === value)?.label ?? LEGACY_GOALS.find(goal => goal.value === value)?.label;
}

export function typeLabelFor(goal?: string | null, type?: string | null): string | undefined {
  return GOALS.find(item => item.value === goal)?.stages?.find(item => item.value === type)?.label
    ?? LEGACY_GOALS.find(item => item.value === goal)?.stages?.find(item => item.value === type)?.label;
}

export function visibleStagesForGoal(goalValue: string): GoalOption["stages"] | null {
  return GOALS.find(goal => goal.value === goalValue)?.stages ?? null;
}
