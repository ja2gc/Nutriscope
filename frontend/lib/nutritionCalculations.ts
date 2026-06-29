// Nutrition prescription engine — FRONTEND MIRROR (live preview only).
// AUTHORITATIVE source of truth is the backend NutritionPrescriptionService (PHP).
// Both engines implement docs/logic/prescription-targets.json EXACTLY; the 90 frozen
// golden cases in that file are asserted in both runtimes to prevent drift.
// Persisted prescriptions MUST come from the backend autofill endpoint — this module
// exists so the RND sees an instant value while typing.

export type Sex = 'Male' | 'Female';

// ── Adult ──────────────────────────────────────────────────────────────────

/** Hamwi IBW formula. heightCm must be > 0. Returns kg. Floor 30 kg. */
export function calcIBW(heightCm: number, sex: Sex): number {
  const inchesOver5Feet = (heightCm / 2.54) - 60;
  const base = sex === 'Male' ? 48.0 : 45.5;
  const perInch = sex === 'Male' ? 2.7 : 2.2;
  return Math.max(base + perInch * inchesOver5Feet, 30);
}

/** Adjusted body weight — use when actual > 120% IBW. Returns kg. */
export function calcAjBW(actualKg: number, ibwKg: number): number {
  return ibwKg + 0.25 * (actualKg - ibwKg);
}

/** %IBW */
export function calcPercentIBW(actualKg: number, ibwKg: number): number {
  return (actualKg / ibwKg) * 100;
}

/**
 * Working weight for energy (flat kcal/kg) and fluid (mL/kg) prescriptions.
 * Weight-basis rule (M2, prescription-targets.json → weight_basis):
 *   - %IBW > 120 → AjBW
 *   - %IBW ≤ 120 → actual body weight (ABW)
 * NOTE: this corrects the prior 90–120%→IBW band, which contradicted the doc.
 */
export function calcWorkingWeight(actualKg: number, ibwKg: number): number {
  return calcPercentIBW(actualKg, ibwKg) > 120 ? calcAjBW(actualKg, ibwKg) : actualKg;
}

/**
 * BMR weight (Mifflin): %IBW > 120 → AjBW; otherwise actual body weight.
 */
export function calcBmrWeight(actualKg: number, ibwKg: number): number {
  return calcPercentIBW(actualKg, ibwKg) > 120 ? calcAjBW(actualKg, ibwKg) : actualKg;
}

/** Mifflin-St Jeor BMR. Returns kcal/day. */
export function calcBMR(weightKg: number, heightCm: number, ageYears: number, sex: Sex): number {
  const base = 10 * weightKg + 6.25 * heightCm - 5 * ageYears;
  return sex === 'Male' ? base + 5 : base - 161;
}

/** TEE = BMR × activity factor (default 1.2 = sedentary/bed-bound). */
export function calcTEE(bmr: number, activityFactor = 1.2): number {
  return bmr * activityFactor;
}

/**
 * Physical Activity Level → TEE multiplier.
 * Keys must stay in sync with the assessment PAL dropdown and the backend spec.
 */
export const ACTIVITY_FACTORS: Record<string, { label: string; factor: number }> = {
  sedentary:    { label: 'Sedentary / Bed-bound (ICU)',          factor: 1.2   },
  light:        { label: 'Lightly Active (Ambulatory inpatient)', factor: 1.375 },
  moderate:     { label: 'Moderately Active (Outpatient)',        factor: 1.55  },
  very_active:  { label: 'Very Active (Regular exercise)',        factor: 1.725 },
  extra_active: { label: 'Extra Active (Heavy labor)',            factor: 1.9   },
};

// ── Pediatric ──────────────────────────────────────────────────────────────

/** Schofield equation — weight-based only. ageYears may be fractional. Returns kcal/day. */
export function calcSchofield(weightKg: number, ageYears: number, sex: Sex): number {
  if (sex === 'Male') {
    if (ageYears < 3)  return 59.512 * weightKg - 30.4;
    if (ageYears < 10) return 22.706 * weightKg + 504.3;
    return 17.686 * weightKg + 658.2;
  } else {
    if (ageYears < 3)  return 58.317 * weightKg - 31.1;
    if (ageYears < 10) return 20.315 * weightKg + 485.9;
    return 13.384 * weightKg + 692.8;
  }
}

/** Holliday-Segar fluid maintenance. Returns mL/day. */
export function calcHollidaySegar(weightKg: number): number {
  if (weightKg <= 10) return weightKg * 100;
  if (weightKg <= 20) return 1000 + (weightKg - 10) * 50;
  return 1500 + (weightKg - 20) * 20;
}

/**
 * Pediatric protein g/kg by age (IOM bands; cross-check vs PDRI g/day at handover).
 * Pediatric goal-specific logic is DEFERRED (M4) — this is a maintenance baseline.
 */
export function pediatricProteinPerKg(ageYears: number): number {
  if (ageYears < 0.5)  return 1.52;
  if (ageYears < 1)    return 1.20;
  if (ageYears < 4)    return 1.05;
  if (ageYears < 14)   return 0.95;
  return 0.85;
}

/** Age-banded pediatric growth energy allowance (kcal/day added to TEE). */
function growthAllowance(ageYears: number): number {
  if (ageYears < 0.5) return 70;
  if (ageYears < 1)   return 45;
  if (ageYears < 4)   return 20;
  return 15; // 4–18 yrs
}

// ── Macro distribution helpers ─────────────────────────────────────────────

/** Carbs = remainder after protein + fat. fatPct default 0.25 (PDRI 15–30% envelope). */
function macrosFromEnergyProtein(
  energyKcal: number,
  proteinG: number,
  fatPct = 0.25,
): { carbs_g: number; fat_g: number } {
  const fat_g   = Math.round((energyKcal * fatPct) / 9);
  const carbs_g = Math.max(Math.round((energyKcal - proteinG * 4 - fat_g * 9) / 4), 0);
  return { carbs_g, fat_g };
}

const CALORIC_FLOOR: Record<Sex, number> = { Female: 1200, Male: 1500 };
const FLUID_FACTOR_ML_PER_KG = 32.5;

// ── Prescription auto-fill ─────────────────────────────────────────────────

export interface Prescription {
  energy_kcal: number;
  protein_g: number;
  carbs_g: number;
  fat_g: number;
  fluid_ml: number;
  /** PDRI baselines / goal targets — optional, surfaced to the RND. */
  fiber_g?: number;
  sodium_max_mg?: number;
  free_sugar_max_pct?: number;
  cholesterol_max_mg?: number;
  feeding_phase?: 'refeeding_start';
  target_energy_kcal_range?: [number, number];
  note?: string;
}

export interface PatientMetrics {
  weightKg: number;
  heightCm: number;
  ageYears: number;
  sex: Sex;
  isAdult: boolean;
  /** Activity factor from assessment PAL dropdown. Default 1.2 (sedentary). */
  activityFactor?: number;
  /** Stress/injury factor (reserved; high_protein flat rate already embeds stress). */
  stressFactor?: number;
  /** PDRI pregnancy/lactation add-on (mirrors backend NutritionPrescriptionService). */
  pregnancyLactationStatus?: 'none' | 'pregnant' | 'lactating';
}

/**
 * Auto-fills a nutrition prescription based on goal_type + disease_stage.
 * Mirrors docs/logic/prescription-targets.json. RND can override any value.
 * AUTHORITATIVE values come from the backend; this is the live-preview mirror.
 *
 * Applies the PDRI pregnancy/lactation modifier on top of the goal result, in
 * lockstep with the PHP NutritionPrescriptionService so the live preview never
 * drifts from the persisted backend value.
 */
export function autofillPrescription(
  goalType: string,
  stage: string | null,
  metrics: PatientMetrics,
): Prescription {
  return applyPregnancyLactation(autofillBase(goalType, stage, metrics), metrics);
}

/**
 * PDRI pregnancy / lactation add-on (mirrors backend exactly):
 *   pregnant  → +300 kcal, +27 g protein
 *   lactating → +500 kcal, +27 g protein
 * Macros are recomputed at the original fat fraction to keep carb/fat consistent.
 */
function applyPregnancyLactation(rx: Prescription, metrics: PatientMetrics): Prescription {
  const status = metrics.pregnancyLactationStatus;
  if (!status || status === 'none') return rx;

  const [energyAdj, proteinAdj] = status === 'pregnant' ? [300, 27]
    : status === 'lactating' ? [500, 27] : [0, 0];
  if (energyAdj === 0 && proteinAdj === 0) return rx;

  const newEnergy  = rx.energy_kcal + energyAdj;
  const newProtein = rx.protein_g + proteinAdj;
  const fatPct     = rx.fat_g > 0 ? (rx.fat_g * 9) / Math.max(rx.energy_kcal, 1) : 0.25;
  const recalc     = macrosFromEnergyProtein(newEnergy, newProtein, fatPct);
  const adjNote    = `${status.charAt(0).toUpperCase()}${status.slice(1)} adjustment applied: +${energyAdj} kcal, +${proteinAdj} g protein (PDRI).`;

  return {
    ...rx,
    energy_kcal: newEnergy,
    protein_g:   newProtein,
    carbs_g:     recalc.carbs_g,
    fat_g:       recalc.fat_g,
    note: rx.note ? `${rx.note} ${adjNote}` : adjNote,
  };
}

function autofillBase(
  goalType: string,
  stage: string | null,
  metrics: PatientMetrics,
): Prescription {
  const { weightKg, heightCm, ageYears, sex, isAdult } = metrics;
  const palFactor = metrics.activityFactor ?? 1.2;

  if (!isAdult) return autofillPediatric(goalType, stage, metrics);

  const ibw       = calcIBW(heightCm, sex);
  const working   = calcWorkingWeight(weightKg, ibw);
  const bmrWt     = calcBmrWeight(weightKg, ibw);
  const bmr       = calcBMR(bmrWt, heightCm, ageYears, sex);
  const tee       = calcTEE(bmr, palFactor);
  const std_fluid = Math.round(working * FLUID_FACTOR_ML_PER_KG);
  const floor     = CALORIC_FLOOR[sex];

  switch (goalType) {
    case 'renal_diet': {
      const energy = Math.round(working * 30); // default 30 kcal/kg (range 25–35), individualized
      const proteinPerKg: Record<string, number> = {
        stage_1: 0.8, stage_2: 0.8, stage_3: 0.7,
        stage_4: 0.6, stage_5_predialysis: 0.6,
        hemodialysis: 1.2, peritoneal: 1.35,
      };
      const sodiumMap: Record<string, number> = {
        stage_1: 2000, stage_2: 2000, stage_3: 2000, stage_4: 2000,
        stage_5_predialysis: 1500, hemodialysis: 1500, peritoneal: 2000,
      };
      const protein_g = Math.round(ibw * (proteinPerKg[stage ?? 'stage_1'] ?? 0.8));
      const fluidMap: Record<string, number> = { hemodialysis: 750, peritoneal: 1000 };
      const fluid_ml  = fluidMap[stage ?? ''] ?? std_fluid;
      const noteMap: Record<string, string> = {
        hemodialysis: 'Add prior-day urine output to 750 mL fluid base.',
        peritoneal:   'Subtract ~500–800 kcal/day for dialysate glucose; individualize fluid to residual renal function + peritoneal losses.',
      };
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.25),
        fluid_ml, sodium_max_mg: sodiumMap[stage ?? 'stage_1'] ?? 2000, note: noteMap[stage ?? ''] };
    }

    case 'diabetic_control': {
      const proteinPerKg = stage === 'stage_3' ? 0.8 : 0.9;
      const protein_g = Math.round(ibw * proteinPerKg);
      let energy = Math.round(tee);
      if (stage === 'stage_2') energy = Math.max(Math.round(tee - 500), floor); // overweight: deficit + floor
      const note = stage === 'stage_3'
        ? 'Coexisting CKD: protein ≈ 0.8 g/kg; avoid routinely exceeding 1.3 g/kg.'
        : stage === 'stage_2' ? '5% weight loss improves glycemic control; −500 kcal ≈ 0.5 kg/week.' : undefined;
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.28),
        fluid_ml: std_fluid, fiber_g: 25, sodium_max_mg: 2000, free_sugar_max_pct: 0.10, note };
    }

    case 'cardiac_diet': {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.8);
      const fatPct    = stage === 'severe' ? 0.24 : stage === 'moderate' ? 0.26 : 0.28;
      const sodiumMap: Record<string, number> = { mild: 2000, moderate: 2000, severe: 1500 };
      const cholMap:   Record<string, number> = { mild: 300, moderate: 200, severe: 200 };
      const cardiacFluid: Record<string, number> = { moderate: 2000, severe: 1500 };
      const fluid_ml  = cardiacFluid[stage ?? ''] ?? std_fluid;
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, fatPct),
        fluid_ml, sodium_max_mg: sodiumMap[stage ?? 'mild'] ?? 2000,
        cholesterol_max_mg: cholMap[stage ?? 'mild'] ?? 300 };
    }

    case 'weight_loss': {
      const deficits: Record<string, number> = {
        overweight: 375, class_1: 500, class_2: 625, class_3: 875,
      };
      const deficit   = deficits[stage ?? 'class_1'] ?? 500;
      const energy    = Math.max(Math.round(tee - deficit), floor);
      const protein_g = Math.round(ibw * 1.4);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275),
        fluid_ml: std_fluid, fiber_g: 25 };
    }

    case 'weight_gain': {
      if (stage === 'severe') {
        const energy    = Math.round(working * 7.5);
        const protein_g = Math.round(ibw * 1.0);
        return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275),
          fluid_ml: std_fluid,
          feeding_phase: 'refeeding_start',
          target_energy_kcal_range: [Math.round(working * 30), Math.round(working * 35)],
          note: 'Refeeding: start 5–10 kcal/kg/day → target 30–35 kcal/kg, reach full needs by day 4–7. Monitor phosphate/K/Mg daily for first 72h; thiamine 200–300 mg/day before feeding.' };
      }
      const surplus   = stage === 'mild' ? 400 : 625;
      const energy    = Math.round(tee + surplus);
      const protein_g = Math.round(ibw * 1.6);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275), fluid_ml: std_fluid };
    }

    case 'high_protein': {
      const kcalPerKg = stage === 'burns' ? 32.5 : 27.5;
      const energy    = Math.round(working * kcalPerKg);
      const protPerKg: Record<string, number> = {
        mild_stress: 1.1, moderate_stress: 1.35, severe_stress: 1.75, burns: 1.75,
      };
      const protein_g = Math.round(ibw * (protPerKg[stage ?? 'mild_stress'] ?? 1.1));
      const note = 'Flat kcal/kg already incorporates the stress factor — do not apply an additional stress multiplier.';
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275), fluid_ml: std_fluid, note };
    }

    case 'liver_disease': {
      const energy = Math.round(working * 37.5); // 35–40 kcal/kg
      // Protein restriction is CONTRAINDICATED — target 1.2–1.5 g/kg ALL stages.
      const protein_g = Math.round(ibw * 1.35);
      const note = stage === 'encephalopathy_grade_3_4'
        ? 'Maintain protein 1.2–1.5 g/kg. Temporary reduction to 1.0 ONLY if protein-intolerant and unresponsive to BCAA/lactulose/rifaximin. Prefer vegetable/dairy protein; BCAA 0.25 g/kg/day; late-evening snack.'
        : stage === 'encephalopathy_grade_1_2'
          ? 'Do not restrict protein. Prefer vegetable/dairy protein; BCAA preferred; late-evening snack.'
          : 'Late-evening snack recommended; prefer small frequent meals.';
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275),
        fluid_ml: std_fluid, sodium_max_mg: 2000, note };
    }

    case 'malnutrition': {
      if (stage === 'severe') {
        const energy    = Math.round(working * 7.5);
        const protein_g = Math.round(ibw * 1.0);
        return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275),
          fluid_ml: std_fluid,
          feeding_phase: 'refeeding_start',
          target_energy_kcal_range: [Math.round(working * 30), Math.round(working * 35)],
          note: 'Refeeding: start 5–10 kcal/kg/day → target 30–35 kcal/kg, full needs by day 4–7. Thiamine 200–300 mg/day BEFORE feeding, continue 10 days. Daily phosphate/K/Mg for first 72h.' };
      }
      const energy    = Math.round(working * 32.5);
      const protein_g = Math.round(ibw * 1.35);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.275), fluid_ml: std_fluid };
    }

    default: {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.8);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, 0.25),
        fluid_ml: std_fluid, fiber_g: 22, sodium_max_mg: 2000 };
    }
  }
}

function autofillPediatric(
  goalType: string,
  stage: string | null,
  { weightKg, ageYears, sex, activityFactor }: PatientMetrics,
): Prescription {
  // M4: pediatric goal-specific logic deferred — generic maintenance estimate.
  void goalType; void stage;
  const palFactor = activityFactor ?? 1.2;
  const bmr      = calcSchofield(weightKg, ageYears, sex);
  const tee      = calcTEE(bmr, palFactor) + growthAllowance(ageYears);
  const fluid_ml = calcHollidaySegar(weightKg);
  const dri_prot = pediatricProteinPerKg(ageYears);

  const energy    = Math.round(tee);
  const protein_g = Math.round(weightKg * dri_prot);
  const fat_g     = Math.round((energy * 0.30) / 9);
  const carbs_g   = Math.max(Math.round((energy - protein_g * 4 - fat_g * 9) / 4), 0);

  return { energy_kcal: energy, protein_g, carbs_g, fat_g, fluid_ml };
}

// ── Nutritional Status Classification (Asia-Pacific default) ──────────────────

export interface NutritionalStatusResult {
  label: string;
  severity: string;
  colorClass: string;
  suggestedGoal?: string;
  suggestedStage?: string;
}

/**
 * Classifies nutritional status from BMI and %IBW using **WHO Asia-Pacific** cut-points (D1).
 * Uses the more severe of the two indicators (clinical convention).
 * Malnutrition bands follow intervention-goals.md §9; overweight/obese side follows AP + D2 remap.
 * `ageYears` is optional (reserved for GLIM age-banded low-BMI refinement).
 */
export function classifyNutritionalStatus(
  bmi: number,
  percentIBW: number,
  ageYears?: number,
): NutritionalStatusResult {
  void ageYears;
  // Malnutrition — %IBW primary, BMI confirms (§9 classification bands)
  if (percentIBW < 70 || bmi < 16.0) {
    return { label: 'Severe Malnutrition', severity: 'severe_malnutrition',
      colorClass: 'bg-red-100 text-red-800 border-red-200',
      suggestedGoal: 'malnutrition', suggestedStage: 'severe' };
  }
  if (percentIBW < 85 || bmi < 17.0) {
    return { label: 'Moderate Malnutrition', severity: 'moderate_malnutrition',
      colorClass: 'bg-red-50 text-red-700 border-red-100',
      suggestedGoal: 'malnutrition', suggestedStage: 'moderate' };
  }
  if (percentIBW < 90 || bmi < 18.5) {
    return { label: 'Mild Malnutrition / Underweight', severity: 'mild_malnutrition',
      colorClass: 'bg-amber-50 text-amber-700 border-amber-100',
      suggestedGoal: 'weight_gain', suggestedStage: 'mild' };
  }
  // Normal (Asia-Pacific: 18.5–22.9)
  if (bmi < 23.0 && percentIBW <= 120) {
    return { label: 'Normal', severity: 'normal',
      colorClass: 'bg-green-50 text-green-700 border-green-100' };
  }
  // Overweight (AP 23.0–24.9) → weight_loss / overweight
  if (bmi < 25.0) {
    return { label: 'Overweight', severity: 'overweight',
      colorClass: 'bg-amber-50 text-amber-700 border-amber-100',
      suggestedGoal: 'weight_loss', suggestedStage: 'overweight' };
  }
  // Obese Class I (AP 25.0–29.9) → class_1
  if (bmi < 30.0) {
    return { label: 'Obese Class I', severity: 'obese_1',
      colorClass: 'bg-orange-50 text-orange-700 border-orange-100',
      suggestedGoal: 'weight_loss', suggestedStage: 'class_1' };
  }
  // Obese Class II (AP ≥30) — split at 35 for weight_loss staging (D2)
  if (bmi < 35.0) {
    return { label: 'Obese Class II', severity: 'obese_2',
      colorClass: 'bg-orange-100 text-orange-800 border-orange-200',
      suggestedGoal: 'weight_loss', suggestedStage: 'class_2' };
  }
  return { label: 'Obese Class II (Severe)', severity: 'obese_2_severe',
    colorClass: 'bg-red-100 text-red-800 border-red-200',
    suggestedGoal: 'weight_loss', suggestedStage: 'class_3' };
}

// ── Micronutrient auto-flag ────────────────────────────────────────────────

/** Micro keys pre-checked for a given goal_type (drives auto-display, per research). */
export const GOAL_MICRO_FLAGS: Record<string, string[]> = {
  renal_diet:        ['potassium', 'phosphate', 'sodium'],
  diabetic_control:  ['fiber', 'sodium', 'free_sugars'],
  cardiac_diet:      ['sodium', 'cholesterol', 'potassium'],
  weight_loss:       ['fiber'],
  liver_disease:     ['sodium'],
  malnutrition:      [],
  weight_gain:       [],
  high_protein:      [],
  custom:            [],
};

export const ALL_MICROS: { key: string; label: string; unit: string }[] = [
  { key: 'sodium',      label: 'Sodium',      unit: 'mg'  },
  { key: 'potassium',   label: 'Potassium',   unit: 'mg'  },
  { key: 'phosphate',   label: 'Phosphorus',  unit: 'mg'  },
  { key: 'calcium',     label: 'Calcium',     unit: 'mg'  },
  { key: 'iron',        label: 'Iron',        unit: 'mg'  },
  { key: 'magnesium',   label: 'Magnesium',   unit: 'mg'  },
  { key: 'zinc',        label: 'Zinc',        unit: 'mg'  },
  { key: 'fiber',       label: 'Fiber',       unit: 'g'   },
  { key: 'free_sugars', label: 'Free Sugars', unit: 'g'   },
  { key: 'cholesterol', label: 'Cholesterol', unit: 'mg'  },
  { key: 'vitamin_a',   label: 'Vitamin A',   unit: 'mcg' },
  { key: 'vitamin_c',   label: 'Vitamin C',   unit: 'mg'  },
  { key: 'vitamin_d',   label: 'Vitamin D',   unit: 'mcg' },
  { key: 'vitamin_b12', label: 'Vitamin B12', unit: 'mcg' },
  { key: 'folate',      label: 'Folate',      unit: 'mcg' },
  { key: 'omega3',      label: 'Omega-3',     unit: 'g'   },
];

/** Set of valid micronutrient keys, for fast membership checks. */
const MICRO_KEY_SET = new Set(ALL_MICROS.map((m) => m.key));

/**
 * Keep only real micronutrient keys, dropping anything not in ALL_MICROS
 * (e.g. macro keys like `energy_kcal`/`protein_g` that should never appear in
 * the micronutrient-limits UI). Order is preserved.
 */
export function microKeys(keys: string[]): string[] {
  return keys.filter((k) => MICRO_KEY_SET.has(k));
}

export type MicronutrientLimit = { max?: number; min?: number; unit: string };

/**
 * Map the prescription engine's micronutrient outputs (sodium_max_mg, fiber_g,
 * cholesterol_max_mg, free_sugar_max_pct) into ALL_MICROS-keyed limit rows with
 * actual values, so the displayed micros aren't shown with empty inputs.
 * free_sugar_max_pct (% of energy) is converted to grams using the energy target.
 */
export function microLimitsFromRx(
  rx: { sodium_max_mg?: number; fiber_g?: number; cholesterol_max_mg?: number; free_sugar_max_pct?: number },
  energyKcal?: number,
): Record<string, MicronutrientLimit> {
  const limits: Record<string, MicronutrientLimit> = {};
  if (rx.sodium_max_mg != null) limits.sodium = { max: rx.sodium_max_mg, unit: "mg" };
  if (rx.cholesterol_max_mg != null) limits.cholesterol = { max: rx.cholesterol_max_mg, unit: "mg" };
  if (rx.fiber_g != null) limits.fiber = { min: rx.fiber_g, unit: "g" };
  if (rx.free_sugar_max_pct != null && energyKcal) {
    limits.free_sugars = { max: Math.round((energyKcal * rx.free_sugar_max_pct) / 4), unit: "g" };
  }
  return limits;
}
