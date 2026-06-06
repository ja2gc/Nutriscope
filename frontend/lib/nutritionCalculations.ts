export type Sex = 'Male' | 'Female';

// ── Adult ──────────────────────────────────────────────────────────────────

/** Hamwi IBW formula. heightCm must be > 0. Returns kg. */
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
 * Working weight selection:
 * - >120% IBW → AjBW
 * - 90–120% IBW → IBW
 * - <90% IBW → actual
 */
export function calcWorkingWeight(actualKg: number, ibwKg: number): number {
  const pct = calcPercentIBW(actualKg, ibwKg);
  if (pct > 120) return calcAjBW(actualKg, ibwKg);
  if (pct >= 90) return ibwKg;
  return actualKg;
}

/** Mifflin-St Jeor BMR. Returns kcal/day. */
export function calcBMR(weightKg: number, heightCm: number, ageYears: number, sex: Sex): number {
  const base = 10 * weightKg + 6.25 * heightCm - 5 * ageYears;
  return sex === 'Male' ? base + 5 : base - 161;
}

/** TEE for hospitalized patients (sedentary = 1.2 default). */
export function calcTEE(bmr: number, activityFactor = 1.2): number {
  return bmr * activityFactor;
}

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

/** Pediatric DRI protein g/kg by age. */
export function pediatricProteinPerKg(ageYears: number): number {
  if (ageYears < 0.5)  return 1.52;
  if (ageYears < 1)    return 1.20;
  if (ageYears < 4)    return 1.05;
  if (ageYears < 14)   return 0.95;
  return 0.85;
}

// ── Macro distribution helpers ─────────────────────────────────────────────

function macrosFromEnergyProtein(
  energyKcal: number,
  proteinG: number,
  fatPct = 0.275,
): { carbs_g: number; fat_g: number } {
  const fat_g   = Math.round((energyKcal * fatPct) / 9);
  const carbs_g = Math.max(Math.round((energyKcal - proteinG * 4 - fat_g * 9) / 4), 0);
  return { carbs_g, fat_g };
}

// ── Prescription auto-fill ─────────────────────────────────────────────────

export interface Prescription {
  energy_kcal: number;
  protein_g: number;
  carbs_g: number;
  fat_g: number;
  fluid_ml: number;
  note?: string;
}

export interface PatientMetrics {
  weightKg: number;
  heightCm: number;
  ageYears: number;
  sex: Sex;
  isAdult: boolean;
}

/**
 * Auto-fills a nutrition prescription based on goal_type + disease_stage.
 * All formulas sourced from docs/logic/intervention-goals.md.
 * RND can override any returned value.
 */
export function autofillPrescription(
  goalType: string,
  stage: string | null,
  metrics: PatientMetrics,
): Prescription {
  const { weightKg, heightCm, ageYears, sex, isAdult } = metrics;

  if (!isAdult) return autofillPediatric(goalType, stage, metrics);

  const ibw     = calcIBW(heightCm, sex);
  const working = calcWorkingWeight(weightKg, ibw);
  const bmr     = calcBMR(working, heightCm, ageYears, sex);
  const tee     = calcTEE(bmr);
  const std_fluid = Math.round(working * 32.5);

  switch (goalType) {
    case 'renal_diet': {
      const energy = Math.round(working * 32.5);
      const proteinPerKg: Record<string, number> = {
        stage_1: 0.8, stage_2: 0.8, stage_3: 0.7,
        stage_4: 0.6, stage_5_predialysis: 0.6,
        hemodialysis: 1.2, peritoneal: 1.35,
      };
      const protein_g = Math.round(ibw * (proteinPerKg[stage ?? 'stage_1'] ?? 0.8));
      const fluidMap: Record<string, number> = { hemodialysis: 750, peritoneal: 1000 };
      const fluid_ml  = fluidMap[stage ?? ''] ?? std_fluid;
      const noteMap: Record<string, string> = {
        hemodialysis: 'Add prior-day urine output to 750 mL fluid base.',
        peritoneal:   'Conservative 1000 mL base; adjust to residual renal function + peritoneal losses.',
      };
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml,
        note: noteMap[stage ?? ''] };
    }

    case 'diabetic_control': {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.9);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'cardiac_diet': {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.8);
      const fatPct    = stage === 'severe' ? 0.24 : stage === 'moderate' ? 0.26 : 0.28;
      const cardiacFluid: Record<string, number> = { moderate: 2000, severe: 1500 };
      const fluid_ml  = cardiacFluid[stage ?? ''] ?? std_fluid;
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, fatPct), fluid_ml };
    }

    case 'weight_loss': {
      const deficits: Record<string, number> = {
        overweight: 375, class_1: 500, class_2: 625, class_3: 875,
      };
      const deficit   = deficits[stage ?? 'class_1'] ?? 500;
      const floor     = sex === 'Female' ? 1200 : 1500;
      const energy    = Math.max(Math.round(tee - deficit), floor);
      const protein_g = Math.round(ibw * 1.4);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'weight_gain': {
      if (stage === 'severe') {
        const energy    = Math.round(weightKg * 7.5);
        const protein_g = Math.round(ibw * 1.0);
        return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid,
          note: 'Refeeding protocol: start at 50% energy, increase 33% every 3–5 days. Monitor electrolytes daily.' };
      }
      const surplus   = stage === 'mild' ? 400 : 625;
      const energy    = Math.round(tee + surplus);
      const protein_g = Math.round(ibw * 1.6);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'high_protein': {
      const energy    = Math.round(working * 27.5);
      const protPerKg: Record<string, number> = {
        mild_stress: 1.1, moderate_stress: 1.35, severe_stress: 1.75, burns: 1.75,
      };
      const protein_g = Math.round(ibw * (protPerKg[stage ?? 'mild_stress'] ?? 1.1));
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'liver_disease': {
      const energy    = Math.round(working * 37.5);
      const protPerKg: Record<string, number> = {
        compensated: 1.35, decompensated: 1.35,
        encephalopathy_grade_1_2: 0.9, encephalopathy_grade_3_4: 0.65,
      };
      const protein_g = Math.round(ibw * (protPerKg[stage ?? 'compensated'] ?? 1.35));
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'malnutrition': {
      if (stage === 'severe') {
        const energy    = Math.round(weightKg * 7.5);
        const protein_g = Math.round(ibw * 1.0);
        return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid,
          note: 'Refeeding protocol applies. Give thiamine 200–300 mg before starting. Monitor phosphate, K, Mg daily.' };
      }
      const energy    = Math.round(working * 32.5);
      const protein_g = Math.round(ibw * 1.35);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    default:
      return { energy_kcal: Math.round(tee), protein_g: Math.round(ibw * 0.8),
        carbs_g: 0, fat_g: 0, fluid_ml: std_fluid };
  }
}

function autofillPediatric(
  goalType: string,
  stage: string | null,
  { weightKg, ageYears, sex }: PatientMetrics,
): Prescription {
  void goalType; void stage;
  const bmr      = calcSchofield(weightKg, ageYears, sex);
  const tee      = calcTEE(bmr) + 20;
  const fluid_ml = calcHollidaySegar(weightKg);
  const dri_prot = pediatricProteinPerKg(ageYears);

  const energy    = Math.round(tee);
  const protein_g = Math.round(weightKg * dri_prot);
  const fat_g     = Math.round((energy * 0.30) / 9);
  const carbs_g   = Math.max(Math.round((energy - protein_g * 4 - fat_g * 9) / 4), 0);

  return { energy_kcal: energy, protein_g, carbs_g, fat_g, fluid_ml };
}

// ── Micronutrient auto-flag ────────────────────────────────────────────────

/** Returns micro keys that should be pre-checked for a given goal_type. */
export const GOAL_MICRO_FLAGS: Record<string, string[]> = {
  renal_diet:        ['potassium', 'phosphate', 'sodium'],
  diabetic_control:  ['fiber'],
  cardiac_diet:      ['sodium', 'cholesterol'],
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
  { key: 'cholesterol', label: 'Cholesterol', unit: 'mg'  },
  { key: 'vitamin_a',   label: 'Vitamin A',   unit: 'mcg' },
  { key: 'vitamin_c',   label: 'Vitamin C',   unit: 'mg'  },
  { key: 'vitamin_d',   label: 'Vitamin D',   unit: 'mcg' },
  { key: 'vitamin_b12', label: 'Vitamin B12', unit: 'mcg' },
  { key: 'folate',      label: 'Folate',      unit: 'mcg' },
  { key: 'omega3',      label: 'Omega-3',     unit: 'g'   },
];
