import {
  ALL_MICROS,
  autofillPrescription,
  calcAjBW,
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

export type CalculationTargetStatus =
  | "matches"
  | "modified"
  | "manual"
  | "missing"
  | "flagged";

export interface CalculationTraceValue {
  value?: number;
  unit?: string;
  text: string;
}

export interface CalculationTraceItem {
  label: string;
  formula: string;
  calculation: string;
  value: string;
}

export interface CalculationTargetRow {
  key: string;
  label: string;
  unit: string;
  prescribed?: CalculationTraceValue;
  calculated?: CalculationTraceValue;
  formula: string;
  calculation: string;
  status: CalculationTargetStatus;
}

export interface CalculationTrace {
  inputs: CalculationTraceItem[];
  weights: CalculationTraceItem[];
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

const FLUID_FACTOR_ML_PER_KG = 32.5;

export function buildPrescriptionCalculationTrace({
  goalType,
  stage,
  goalLabel,
  stageLabel,
  metrics,
  prescription,
  requiredMicros = [],
}: BuildPrescriptionCalculationTraceInput): CalculationTrace {
  const isCustom = goalType === "custom";
  const calculated = isCustom ? null : autofillPrescription(goalType, stage, metrics);

  return {
    inputs: buildInputRows(goalLabel ?? goalType, stageLabel, metrics),
    weights: buildWeightRows(metrics),
    targets: [
      ...buildMacroRows(goalType, stage, metrics, prescription, calculated),
      ...buildMicroRows(goalType, prescription, calculated, requiredMicros),
    ],
    notes: buildNotes(calculated),
  };
}

function buildInputRows(goalLabel: string, stageLabel: string | undefined, metrics: PatientMetrics): CalculationTraceItem[] {
  const rows: CalculationTraceItem[] = [
    { label: "Goal", formula: "Selected intervention", calculation: goalLabel, value: goalLabel },
  ];

  if (stageLabel) {
    rows.push({ label: "Stage", formula: "Selected disease stage", calculation: stageLabel, value: stageLabel });
  }

  rows.push(
    {
      label: "Weight",
      formula: "Assessment weight used by engine",
      calculation: `${fmt(metrics.weightKg)} kg`,
      value: `${fmt(metrics.weightKg)} kg`,
    },
    {
      label: "Height / Age / Sex",
      formula: "Patient demographics",
      calculation: `${fmt(metrics.heightCm)} cm / ${fmt(metrics.ageYears)} y / ${metrics.sex}`,
      value: `${fmt(metrics.heightCm)} cm, ${fmt(metrics.ageYears)} y, ${metrics.sex}`,
    },
    {
      label: "Activity",
      formula: "PAL factor",
      calculation: `${fmt(metrics.activityFactor ?? 1.2)}`,
      value: `${fmt(metrics.activityFactor ?? 1.2)}`,
    },
  );

  if (metrics.pregnancyLactationStatus && metrics.pregnancyLactationStatus !== "none") {
    rows.push({
      label: "Pregnancy / Lactation",
      formula: "PDRI add-on",
      calculation: metrics.pregnancyLactationStatus === "lactating"
        ? "+500 kcal, +27 g protein"
        : "+300 kcal, +27 g protein",
      value: metrics.pregnancyLactationStatus,
    });
  }

  return rows;
}

function buildWeightRows(metrics: PatientMetrics): CalculationTraceItem[] {
  if (!metrics.isAdult) {
    const bmr = calcSchofield(metrics.weightKg, metrics.ageYears, metrics.sex);
    const tee = calcTEE(bmr, metrics.activityFactor ?? 1.2);
    return [
      {
        label: "Schofield BMR",
        formula: "Age/sex weight equation",
        calculation: `Schofield(${fmt(metrics.weightKg)} kg, ${fmt(metrics.ageYears)} y) = ${fmt(bmr)} kcal`,
        value: `${fmt(bmr)} kcal`,
      },
      {
        label: "Growth energy",
        formula: "TEE + growth allowance",
        calculation: `${fmt(bmr)} x ${fmt(metrics.activityFactor ?? 1.2)} + growth = ${fmt(tee)}+ kcal`,
        value: "Age-banded",
      },
      {
        label: "Fluid",
        formula: "Holliday-Segar",
        calculation: `${fmt(calcHollidaySegar(metrics.weightKg))} mL/day`,
        value: `${fmt(calcHollidaySegar(metrics.weightKg))} mL`,
      },
    ];
  }

  const ibw = calcIBW(metrics.heightCm, metrics.sex);
  const percentIbw = calcPercentIBW(metrics.weightKg, ibw);
  const working = calcWorkingWeight(metrics.weightKg, ibw);
  const bmrWeight = calcBmrWeight(metrics.weightKg, ibw);
  const bmr = calcBMR(bmrWeight, metrics.heightCm, metrics.ageYears, metrics.sex);
  const rows: CalculationTraceItem[] = [
    {
      label: "IBW",
      formula: "Hamwi, floor 30 kg",
      calculation: `${metrics.sex}: height ${fmt(metrics.heightCm)} cm -> ${fmt(ibw)} kg`,
      value: `${fmt(ibw)} kg`,
    },
    {
      label: "%IBW",
      formula: "weight / IBW x 100",
      calculation: `${fmt(metrics.weightKg)} / ${fmt(ibw)} x 100 = ${fmt(percentIbw)}%`,
      value: `${fmt(percentIbw)}%`,
    },
  ];

  if (percentIbw > 120) {
    const ajbw = calcAjBW(metrics.weightKg, ibw);
    rows.push({
      label: "AjBW",
      formula: "IBW + 0.25 x (actual - IBW)",
      calculation: `${fmt(ibw)} + 0.25 x (${fmt(metrics.weightKg)} - ${fmt(ibw)}) = ${fmt(ajbw)} kg`,
      value: `${fmt(ajbw)} kg`,
    });
  }

  rows.push(
    {
      label: "Working weight",
      formula: "%IBW > 120 ? AjBW : actual",
      calculation: `${fmt(percentIbw)}% ${percentIbw > 120 ? ">" : "<="} 120 -> ${fmt(working)} kg`,
      value: `${fmt(working)} kg`,
    },
    {
      label: "Protein weight",
      formula: "Adult protein targets use IBW",
      calculation: `${fmt(ibw)} kg`,
      value: `${fmt(ibw)} kg`,
    },
    {
      label: "BMR",
      formula: "Mifflin-St Jeor",
      calculation: `${fmt(bmrWeight)} kg, ${fmt(metrics.heightCm)} cm, ${fmt(metrics.ageYears)} y -> ${fmt(bmr)} kcal`,
      value: `${fmt(bmr)} kcal`,
    },
  );

  return rows;
}

function buildMacroRows(
  goalType: string,
  stage: string | null,
  metrics: PatientMetrics,
  prescription: PrescriptionFormState,
  calculated: Prescription | null,
): CalculationTargetRow[] {
  return MACRO_ROWS.map(({ key, label, unit }) => {
    const prescribedNumber = parseNumber(prescription[key]);
    const calculatedNumber = calculated ? Number(calculated[key]) : undefined;
    const formulaInfo = formulaForMacro(goalType, stage, key, metrics);

    return {
      key,
      label,
      unit,
      prescribed: valueFromNumber(prescribedNumber, unit),
      calculated: valueFromNumber(calculatedNumber, unit),
      formula: calculated ? formulaInfo.formula : "Manual target",
      calculation: calculated ? formulaInfo.calculation : "No formula applies to custom goals.",
      status: statusFor(prescribedNumber, calculatedNumber),
    };
  });
}

function buildMicroRows(
  goalType: string,
  prescription: PrescriptionFormState,
  calculated: Prescription | null,
  requiredMicros: string[],
): CalculationTargetRow[] {
  const calculatedLimits = calculated ? microLimitsFromRx(calculated, calculated.energy_kcal) : {};
  const keys = microKeys(Array.from(new Set([
    ...requiredMicros,
    ...prescription.displayed_nutrients,
    ...Object.keys(prescription.micronutrient_limits),
    ...Object.keys(calculatedLimits),
  ])));

  return keys.map((key) => {
    const meta = ALL_MICROS.find((micro) => micro.key === key);
    const prescribed = prescription.micronutrient_limits[key];
    const expected = calculatedLimits[key];
    const flagOnly = requiredMicros.includes(key) && !expected;

    return {
      key,
      label: meta?.label ?? key,
      unit: prescribed?.unit ?? expected?.unit ?? meta?.unit ?? "",
      prescribed: valueFromLimit(prescribed),
      calculated: flagOnly
        ? { text: "Flagged for monitoring", unit: meta?.unit }
        : valueFromLimit(expected),
      formula: expected ? microFormula(key) : flagOnly ? "Goal-required monitoring flag" : "Manual target",
      calculation: expected
        ? microCalculation(key, calculated)
        : flagOnly
          ? `${goalType} requires ${meta?.label ?? key} monitoring.`
          : "No calculated micronutrient target for selected goal.",
      status: flagOnly ? "flagged" : statusFor(limitNumber(prescribed), limitNumber(expected)),
    };
  });
}

function formulaForMacro(
  goalType: string,
  stage: string | null,
  key: (typeof MACRO_ROWS)[number]["key"],
  metrics: PatientMetrics,
): { formula: string; calculation: string } {
  const shared = sharedNumbers(metrics);
  if (key === "protein_g") return proteinFormula(goalType, stage, shared);
  if (key === "fluid_ml") return fluidFormula(goalType, stage, shared);
  if (key === "carbs_g") return { formula: "Carbs = energy - protein kcal - fat kcal", calculation: "Remaining kcal / 4." };
  if (key === "fat_g") return { formula: `Fat = energy x ${fmt(fatPctFor(goalType, stage) * 100)}% / 9`, calculation: "Goal-specific fat percentage converted to grams." };

  if (!metrics.isAdult) {
    return {
      formula: "Schofield BMR x PAL + growth allowance",
      calculation: "Pediatric baseline engine applies age-banded growth allowance.",
    };
  }

  switch (goalType) {
    case "renal_diet":
      return { formula: "Energy = working weight x 30 kcal/kg", calculation: `${fmt(shared.working)} x 30 = ${Math.round(shared.working * 30)} kcal` };
    case "diabetic_control":
      return stage === "stage_2"
        ? { formula: "Energy = max(TEE - 500, sex-specific floor)", calculation: `${fmt(shared.tee)} - 500 = ${Math.round(shared.tee - 500)} kcal` }
        : { formula: "Energy = TEE", calculation: `${fmt(shared.bmr)} x ${fmt(metrics.activityFactor ?? 1.2)} = ${Math.round(shared.tee)} kcal` };
    case "cardiac_diet":
      return { formula: "Energy = TEE", calculation: `${fmt(shared.bmr)} x ${fmt(metrics.activityFactor ?? 1.2)} = ${Math.round(shared.tee)} kcal` };
    case "weight_loss": {
      const deficit = ({ overweight: 375, class_1: 500, class_2: 625, class_3: 875 } as Record<string, number>)[stage ?? "class_1"] ?? 500;
      return { formula: "Energy = max(TEE - deficit, sex-specific floor)", calculation: `${fmt(shared.tee)} - ${deficit} = ${Math.round(shared.tee - deficit)} kcal` };
    }
    case "weight_gain":
      return stage === "severe"
        ? { formula: "Refeeding start = working weight x 7.5 kcal/kg", calculation: `${fmt(shared.working)} x 7.5 = ${Math.round(shared.working * 7.5)} kcal` }
        : { formula: "Energy = TEE + stage surplus", calculation: `${fmt(shared.tee)} + ${stage === "mild" ? 400 : 625} kcal` };
    case "high_protein": {
      const kcalPerKg = stage === "burns" ? 32.5 : 27.5;
      return { formula: `Energy = working weight x ${kcalPerKg} kcal/kg`, calculation: `${fmt(shared.working)} x ${kcalPerKg} = ${Math.round(shared.working * kcalPerKg)} kcal` };
    }
    case "liver_disease":
      return { formula: "Energy = working weight x 37.5 kcal/kg", calculation: `${fmt(shared.working)} x 37.5 = ${Math.round(shared.working * 37.5)} kcal` };
    case "malnutrition":
      return stage === "severe"
        ? { formula: "Refeeding start = working weight x 7.5 kcal/kg", calculation: `${fmt(shared.working)} x 7.5 = ${Math.round(shared.working * 7.5)} kcal` }
        : { formula: "Energy = working weight x 32.5 kcal/kg", calculation: `${fmt(shared.working)} x 32.5 = ${Math.round(shared.working * 32.5)} kcal` };
    default:
      return { formula: "Energy = TEE", calculation: `${fmt(shared.bmr)} x ${fmt(metrics.activityFactor ?? 1.2)} = ${Math.round(shared.tee)} kcal` };
  }
}

function proteinFormula(goalType: string, stage: string | null, shared: ReturnType<typeof sharedNumbers>) {
  if (!shared.isAdult) return { formula: "Protein = weight x age-banded g/kg", calculation: "Pediatric age-banded protein factor." };
  const factors: Record<string, number> = {
    renal_diet: ({ stage_1: 0.8, stage_2: 0.8, stage_3: 0.7, stage_4: 0.6, stage_5_predialysis: 0.6, hemodialysis: 1.2, peritoneal: 1.35 } as Record<string, number>)[stage ?? "stage_1"] ?? 0.8,
    diabetic_control: stage === "stage_3" ? 0.8 : 0.9,
    cardiac_diet: 0.8,
    weight_loss: 1.4,
    weight_gain: stage === "severe" ? 1.0 : 1.6,
    high_protein: ({ mild_stress: 1.1, moderate_stress: 1.35, severe_stress: 1.75, burns: 1.75 } as Record<string, number>)[stage ?? "mild_stress"] ?? 1.1,
    liver_disease: 1.35,
    malnutrition: stage === "severe" ? 1.0 : 1.35,
  };
  const factor = factors[goalType] ?? 0.8;
  return { formula: `Protein = IBW x ${factor} g/kg`, calculation: `${fmt(shared.ibw)} x ${factor} = ${Math.round(shared.ibw * factor)} g` };
}

function fluidFormula(goalType: string, stage: string | null, shared: ReturnType<typeof sharedNumbers>) {
  if (goalType === "renal_diet" && stage === "hemodialysis") return { formula: "Fluid = 750 mL base", calculation: "Add prior-day urine output clinically." };
  if (goalType === "renal_diet" && stage === "peritoneal") return { formula: "Fluid = 1000 mL default", calculation: "Individualize to residual renal function and losses." };
  if (goalType === "cardiac_diet" && stage === "moderate") return { formula: "Fluid = 2000 mL", calculation: "Cardiac moderate restriction default." };
  if (goalType === "cardiac_diet" && stage === "severe") return { formula: "Fluid = 1500 mL", calculation: "Cardiac severe restriction default." };
  return { formula: "Fluid = working weight x 32.5 mL/kg", calculation: `${fmt(shared.working)} x ${FLUID_FACTOR_ML_PER_KG} = ${Math.round(shared.working * FLUID_FACTOR_ML_PER_KG)} mL` };
}

function sharedNumbers(metrics: PatientMetrics) {
  if (!metrics.isAdult) {
    const schofield = calcSchofield(metrics.weightKg, metrics.ageYears, metrics.sex);
    return {
      isAdult: false as const,
      ibw: metrics.weightKg,
      working: metrics.weightKg,
      bmr: schofield,
      tee: calcTEE(schofield, metrics.activityFactor ?? 1.2),
    };
  }
  const ibw = calcIBW(metrics.heightCm, metrics.sex);
  const working = calcWorkingWeight(metrics.weightKg, ibw);
  const bmrWeight = calcBmrWeight(metrics.weightKg, ibw);
  const bmr = calcBMR(bmrWeight, metrics.heightCm, metrics.ageYears, metrics.sex);
  return {
    isAdult: true as const,
    ibw,
    working,
    bmr,
    tee: calcTEE(bmr, metrics.activityFactor ?? 1.2),
  };
}

function fatPctFor(goalType: string, stage: string | null): number {
  if (goalType === "diabetic_control") return 0.28;
  if (goalType === "cardiac_diet") return stage === "severe" ? 0.24 : stage === "moderate" ? 0.26 : 0.28;
  if (["weight_loss", "weight_gain", "high_protein", "liver_disease", "malnutrition"].includes(goalType)) return 0.275;
  if (!stage && goalType === "custom") return 0;
  return 0.25;
}

function microFormula(key: string): string {
  if (key === "free_sugars") return "Free sugars max = energy x 10% / 4";
  if (key === "fiber") return "Fiber minimum from goal target";
  if (key === "sodium") return "Sodium maximum from goal target";
  if (key === "cholesterol") return "Cholesterol maximum from goal target";
  return "Micronutrient target from goal";
}

function microCalculation(key: string, calculated: Prescription | null): string {
  if (!calculated) return "No calculated target.";
  if (key === "free_sugars" && calculated.free_sugar_max_pct != null) {
    return `${calculated.energy_kcal} kcal x ${fmt(calculated.free_sugar_max_pct * 100)}% / 4 = ${Math.round((calculated.energy_kcal * calculated.free_sugar_max_pct) / 4)} g`;
  }
  return "Goal-specific nutrient limit.";
}

function buildNotes(calculated: Prescription | null): string[] {
  if (!calculated) return ["Custom goal: RND enters all targets manually."];
  const notes = [calculated.note].filter((note): note is string => Boolean(note));
  if (calculated.feeding_phase === "refeeding_start" && calculated.target_energy_kcal_range) {
    notes.push(`Refeeding phase: target full-energy range ${calculated.target_energy_kcal_range[0]}-${calculated.target_energy_kcal_range[1]} kcal/day by day 4-7 if clinically stable.`);
  }
  return notes;
}

function statusFor(prescribed: number | undefined, calculated: number | undefined): CalculationTargetStatus {
  if (calculated == null && prescribed == null) return "missing";
  if (calculated == null && prescribed != null) return "manual";
  if (calculated != null && prescribed == null) return "missing";
  return Math.abs(Number(prescribed) - Number(calculated)) <= 1 ? "matches" : "modified";
}

function valueFromNumber(value: number | undefined, unit: string): CalculationTraceValue | undefined {
  if (value == null || Number.isNaN(value)) return undefined;
  return { value, unit, text: `${fmt(value)} ${unit}` };
}

function valueFromLimit(limit: { max?: number; min?: number; unit: string } | undefined): CalculationTraceValue | undefined {
  if (!limit) return undefined;
  if (limit.max != null) return { value: limit.max, unit: limit.unit, text: `<= ${fmt(limit.max)} ${limit.unit}` };
  if (limit.min != null) return { value: limit.min, unit: limit.unit, text: `>= ${fmt(limit.min)} ${limit.unit}` };
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
  return Number.isInteger(value)
    ? String(value)
    : value.toLocaleString("en-US", { maximumFractionDigits: 1 });
}
