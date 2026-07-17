import {
  ALL_MICROS,
  autofillPrescription,
  calcBMR,
  calcBmrWeight,
  calcHollidaySegar,
  calcIBW,
  calcPercentIBW,
  calcSchofield,
  calcTEE,
  calcWorkingWeight,
  microKeys,
  microLimitsFromRx,
  type PatientMetrics,
  type Prescription,
} from "./nutritionCalculations";
import type { PrescriptionFormState } from "./interventionGoalState";

export type CalculationTargetStatus = "matches" | "modified" | "manual" | "missing";

export interface CalculationTraceValue {
  value?: number;
  unit?: string;
  text: string;
}

export interface CalculationContextItem {
  label: string;
  value: string;
  formulaName?: string;
}

export interface CalculationTargetRow {
  key: string;
  label: string;
  unit: string;
  value?: CalculationTraceValue;
  formulaName?: string;
  formula: string;
  calculation: string;
  status: CalculationTargetStatus;
}

export interface CalculationTrace {
  context: CalculationContextItem[];
  targets: CalculationTargetRow[];
  notes: string[];
}

export interface BuildPrescriptionCalculationTraceInput {
  goalType: string;
  stage: string | null;
  goalLabel?: string;
  stageLabel?: string;
  metrics: PatientMetrics;
  prescription: PrescriptionFormState;
  requiredMicros?: string[];
}

const MACRO_ROWS = [
  { key: "energy_kcal", label: "Energy", unit: "kcal" },
  { key: "protein_g", label: "Protein", unit: "g" },
  { key: "carbs_g", label: "Carbohydrates", unit: "g" },
  { key: "fat_g", label: "Fat", unit: "g" },
  { key: "fluid_ml", label: "Fluid", unit: "mL" },
] as const;

const FLUID_FACTOR = 32.5;

export function buildPrescriptionCalculationTrace({
  goalType,
  stage,
  goalLabel,
  stageLabel,
  metrics,
  prescription,
}: BuildPrescriptionCalculationTraceInput): CalculationTrace {
  const calculated = goalType === "custom" ? null : autofillPrescription(goalType, stage, metrics);

  return {
    context: buildContext(goalLabel ?? goalType, stageLabel, metrics),
    targets: [
      ...buildMacroRows(goalType, stage, metrics, prescription, calculated),
      ...buildMicroRows(prescription, calculated),
    ],
    notes: calculated?.note ? [calculated.note] : [],
  };
}

function buildContext(goalLabel: string, stageLabel: string | undefined, metrics: PatientMetrics): CalculationContextItem[] {
  const context: CalculationContextItem[] = [
    { label: "Goal", value: goalLabel },
    ...(stageLabel ? [{ label: "Stage", value: stageLabel }] : []),
    { label: "Weight", value: `${fmt(metrics.weightKg)} kg` },
    { label: "Height", value: `${fmt(metrics.heightCm)} cm` },
    { label: "Age", value: `${fmt(metrics.ageYears)} y` },
    { label: "Sex", value: metrics.sex },
    { label: "Activity", value: fmt(metrics.activityFactor ?? 1.2) },
  ];

  if (metrics.pregnancyLactationStatus && metrics.pregnancyLactationStatus !== "none") {
    context.push({
      label: "Pregnancy / Lactation",
      value: metrics.pregnancyLactationStatus === "pregnant" ? "Pregnant" : "Lactating",
    });
  }

  if (!metrics.isAdult) {
    const bmr = calcSchofield(metrics.weightKg, metrics.ageYears, metrics.sex);
    return [
      ...context,
      { label: "BMR", value: `${fmt(bmr)} kcal`, formulaName: "Schofield" },
      { label: "Fluid", value: `${fmt(calcHollidaySegar(metrics.weightKg))} mL`, formulaName: "Holliday-Segar" },
    ];
  }

  const ibw = calcIBW(metrics.heightCm, metrics.sex);
  const working = calcWorkingWeight(metrics.weightKg, ibw);
  const bmr = calcBMR(calcBmrWeight(metrics.weightKg, ibw), metrics.heightCm, metrics.ageYears, metrics.sex);

  return [
    ...context,
    { label: "IBW", value: `${fmt(ibw)} kg`, formulaName: "Hamwi" },
    { label: "%IBW", value: `${fmt(calcPercentIBW(metrics.weightKg, ibw))}%` },
    { label: "Working weight", value: `${fmt(working)} kg` },
    { label: "Protein weight", value: `${fmt(ibw)} kg` },
    { label: "BMR", value: `${fmt(bmr)} kcal`, formulaName: "Mifflin-St Jeor" },
  ];
}

function buildMacroRows(
  goalType: string,
  stage: string | null,
  metrics: PatientMetrics,
  prescription: PrescriptionFormState,
  calculated: Prescription | null,
): CalculationTargetRow[] {
  return MACRO_ROWS.map(({ key, label, unit }) => {
    const prescribed = parseNumber(prescription[key]);
    const expected = calculated ? Number(calculated[key]) : undefined;
    const formula = withPatientAddOn(
      key,
      macroFormula(goalType, stage, key, metrics, calculated),
      metrics,
      expected,
    );

    return {
      key,
      label,
      unit,
      value: valueFromNumber(prescribed ?? expected, unit),
      formulaName: formula.formulaName,
      formula: calculated ? formula.variables : "Manual target",
      calculation: calculated ? formula.values : "Entered by RND; no automatic formula applies.",
      status: statusFor(prescribed, expected),
    };
  });
}

function withPatientAddOn(
  key: (typeof MACRO_ROWS)[number]["key"],
  formula: { formulaName?: string; variables: string; values: string },
  metrics: PatientMetrics,
  expected: number | undefined,
) {
  const status = metrics.pregnancyLactationStatus;
  if (!status || status === "none" || expected == null) return formula;

  const addOn = key === "energy_kcal" ? (status === "pregnant" ? 300 : 500) : key === "protein_g" ? 27 : 0;
  if (addOn === 0) return formula;

  const unit = key === "energy_kcal" ? "kcal" : "g";
  const calculationWithoutResult = formula.values.replace(` = ${expected} ${unit}`, "");
  const addOnLabel = key === "energy_kcal" ? "energy" : "protein";

  return {
    ...formula,
    variables: `${formula.variables} + pregnancy/lactation ${addOnLabel} add-on`,
    values: `${calculationWithoutResult} + ${addOn} = ${expected} ${unit}`,
  };
}

function buildMicroRows(
  prescription: PrescriptionFormState,
  calculated: Prescription | null,
): CalculationTargetRow[] {
  const calculatedLimits = calculated ? microLimitsFromRx(calculated, calculated.energy_kcal) : {};
  const keys = microKeys(Array.from(new Set([
    ...Object.keys(prescription.micronutrient_limits),
    ...Object.keys(calculatedLimits),
  ])));

  return keys.flatMap((key) => {
    const prescribed = prescription.micronutrient_limits[key];
    const expected = calculatedLimits[key];
    const currentValue = valueFromLimit(prescribed) ?? valueFromLimit(expected);
    if (!currentValue) return [];

    const meta = ALL_MICROS.find((micro) => micro.key === key);
    return [{
      key,
      label: meta?.label ?? key,
      unit: prescribed?.unit ?? expected?.unit ?? meta?.unit ?? "",
      value: currentValue,
      formula: calculated ? microFormula(key) : "Manual target",
      calculation: calculated
        ? microCalculation(key, calculated, currentValue.text)
        : "Entered by RND; no automatic formula applies.",
      status: statusFor(limitNumber(prescribed), limitNumber(expected)),
    }];
  });
}

function macroFormula(
  goalType: string,
  stage: string | null,
  key: (typeof MACRO_ROWS)[number]["key"],
  metrics: PatientMetrics,
  calculated: Prescription | null,
): { formulaName?: string; variables: string; values: string } {
  const shared = sharedNumbers(metrics);
  const expected = calculated?.[key] ?? 0;

  if (key === "protein_g") {
    if (!metrics.isAdult) {
      return { variables: "Body Weight × age-based g/kg factor", values: `${fmt(metrics.weightKg)} × age-based factor = ${expected} g` };
    }
    const factor = proteinFactor(goalType, stage);
    return { variables: "IBW × g/kg factor", values: `${fmt(shared.ibw)} × ${factor} = ${expected} g` };
  }

  if (key === "carbs_g") {
    const energy = calculated?.energy_kcal ?? 0;
    const protein = calculated?.protein_g ?? 0;
    const fat = calculated?.fat_g ?? 0;
    return {
      variables: "(Energy − Protein kcal − Fat kcal) ÷ 4",
      values: `(${energy} − (${protein} × 4) − (${fat} × 9)) ÷ 4 = ${expected} g`,
    };
  }

  if (key === "fat_g") {
    const pct = metrics.isAdult ? fatPctFor(goalType, stage) : 0.3;
    return {
      variables: "Energy × fat% ÷ 9",
      values: `${calculated?.energy_kcal ?? 0} × ${fmt(pct * 100)}% ÷ 9 = ${expected} g`,
    };
  }

  if (key === "fluid_ml") {
    if (!metrics.isAdult) {
      return { formulaName: "Holliday-Segar", variables: "Pediatric maintenance fluid equation", values: `${fmt(metrics.weightKg)} kg = ${expected} mL` };
    }
    if (goalType === "renal_diet" && stage === "hemodialysis") {
      return { variables: "Base fluid allowance + prior-day urine output", values: `750 mL base = ${expected} mL before clinical adjustment` };
    }
    if (goalType === "renal_diet" && stage === "peritoneal") {
      return { variables: "Goal-specific fluid allowance", values: `${expected} mL` };
    }
    if (goalType === "cardiac_diet" && ["moderate", "severe"].includes(stage ?? "")) {
      return { variables: "Goal-specific fluid allowance", values: `${expected} mL` };
    }
    return { variables: "Working Weight × mL/kg factor", values: `${fmt(shared.working)} × ${FLUID_FACTOR} = ${expected} mL` };
  }

  if (!metrics.isAdult) {
    return { formulaName: "Schofield", variables: "(BMR × PAL) + growth allowance", values: `(${fmt(shared.bmr)} × ${fmt(metrics.activityFactor ?? 1.2)}) + growth allowance = ${expected} kcal` };
  }

  switch (goalType) {
    case "renal_diet":
      return flatEnergy(shared.working, 30, expected);
    case "diabetic_control":
      return stage === "stage_2"
        ? { variables: "max((BMR × PAL) − calorie deficit, caloric floor)", values: `max((${fmt(shared.bmr)} × ${fmt(metrics.activityFactor ?? 1.2)}) − 500, caloric floor) = ${expected} kcal` }
        : teeEnergy(shared.bmr, metrics.activityFactor ?? 1.2, expected);
    case "cardiac_diet":
      return teeEnergy(shared.bmr, metrics.activityFactor ?? 1.2, expected);
    case "weight_loss": {
      const deficit = ({ overweight: 375, class_1: 500, class_2: 625, class_3: 875 } as Record<string, number>)[stage ?? "class_1"] ?? 500;
      return { variables: "max((BMR × PAL) − calorie deficit, caloric floor)", values: `max((${fmt(shared.bmr)} × ${fmt(metrics.activityFactor ?? 1.2)}) − ${deficit}, caloric floor) = ${expected} kcal` };
    }
    case "weight_gain":
      return stage === "severe"
        ? flatEnergy(shared.working, 32.5, expected)
        : { variables: "(BMR × PAL) + calorie surplus", values: `(${fmt(shared.bmr)} × ${fmt(metrics.activityFactor ?? 1.2)}) + ${stage === "mild" ? 400 : 625} = ${expected} kcal` };
    case "high_protein":
      return flatEnergy(shared.working, stage === "burns" ? 32.5 : 27.5, expected);
    case "liver_disease":
      return flatEnergy(shared.working, 37.5, expected);
    case "malnutrition":
      return flatEnergy(shared.working, 32.5, expected);
    default:
      return teeEnergy(shared.bmr, metrics.activityFactor ?? 1.2, expected);
  }
}

function flatEnergy(weight: number, factor: number, expected: number) {
  return { variables: "Working Weight × kcal/kg factor", values: `${fmt(weight)} × ${factor} = ${expected} kcal` };
}

function teeEnergy(bmr: number, pal: number, expected: number) {
  return { variables: "BMR × PAL", values: `${fmt(bmr)} × ${fmt(pal)} = ${expected} kcal` };
}

function proteinFactor(goalType: string, stage: string | null): number {
  const factors: Record<string, number> = {
    renal_diet: ({ stage_1: 0.8, stage_2: 0.8, stage_3: 0.7, stage_4: 0.6, stage_5_predialysis: 0.6, hemodialysis: 1.2, peritoneal: 1.35 } as Record<string, number>)[stage ?? "stage_1"] ?? 0.8,
    diabetic_control: stage === "stage_3" ? 0.8 : 0.9,
    cardiac_diet: 0.8,
    weight_loss: 1.4,
    weight_gain: stage === "severe" ? 1 : 1.6,
    high_protein: ({ mild_stress: 1.1, moderate_stress: 1.35, severe_stress: 1.75, burns: 1.75 } as Record<string, number>)[stage ?? "mild_stress"] ?? 1.1,
    liver_disease: 1.35,
    malnutrition: stage === "severe" ? 1 : 1.35,
  };
  return factors[goalType] ?? 0.8;
}

function sharedNumbers(metrics: PatientMetrics) {
  if (!metrics.isAdult) {
    const bmr = calcSchofield(metrics.weightKg, metrics.ageYears, metrics.sex);
    return { ibw: metrics.weightKg, working: metrics.weightKg, bmr, tee: calcTEE(bmr, metrics.activityFactor ?? 1.2) };
  }
  const ibw = calcIBW(metrics.heightCm, metrics.sex);
  const bmr = calcBMR(calcBmrWeight(metrics.weightKg, ibw), metrics.heightCm, metrics.ageYears, metrics.sex);
  return { ibw, working: calcWorkingWeight(metrics.weightKg, ibw), bmr, tee: calcTEE(bmr, metrics.activityFactor ?? 1.2) };
}

function fatPctFor(goalType: string, stage: string | null): number {
  if (goalType === "diabetic_control") return 0.28;
  if (goalType === "cardiac_diet") return stage === "severe" ? 0.24 : stage === "moderate" ? 0.26 : 0.28;
  if (["weight_loss", "weight_gain", "high_protein", "liver_disease", "malnutrition"].includes(goalType)) return 0.275;
  return 0.25;
}

function microFormula(key: string): string {
  if (key === "free_sugars") return "Energy × free sugar limit% ÷ 4";
  return "Goal-specific recommended target";
}

function microCalculation(key: string, calculated: Prescription | null, fallback: string): string {
  if (key === "free_sugars" && calculated?.free_sugar_max_pct != null) {
    return `${calculated.energy_kcal} × ${fmt(calculated.free_sugar_max_pct * 100)}% ÷ 4 = ${Math.round((calculated.energy_kcal * calculated.free_sugar_max_pct) / 4)} g`;
  }
  return fallback;
}

function statusFor(prescribed: number | undefined, calculated: number | undefined): CalculationTargetStatus {
  if (calculated == null && prescribed == null) return "missing";
  if (calculated == null) return "manual";
  if (prescribed == null) return "matches";
  return Math.abs(prescribed - calculated) <= 1 ? "matches" : "modified";
}

function valueFromNumber(value: number | undefined, unit: string): CalculationTraceValue | undefined {
  if (value == null || Number.isNaN(value)) return undefined;
  return { value, unit, text: `${fmt(value)} ${unit}` };
}

function valueFromLimit(limit: { max?: number; min?: number; unit: string } | undefined): CalculationTraceValue | undefined {
  if (limit?.max != null) return { value: limit.max, unit: limit.unit, text: `≤ ${fmt(limit.max)} ${limit.unit}` };
  if (limit?.min != null) return { value: limit.min, unit: limit.unit, text: `≥ ${fmt(limit.min)} ${limit.unit}` };
  return undefined;
}

function limitNumber(limit: { max?: number; min?: number; unit: string } | undefined): number | undefined {
  return limit?.max ?? limit?.min;
}

function parseNumber(value: string): number | undefined {
  if (value.trim() === "") return undefined;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : undefined;
}

function fmt(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toLocaleString("en-US", { maximumFractionDigits: 1 });
}
