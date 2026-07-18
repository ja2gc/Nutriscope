/**
 * TDD: nutritionCalculations.ts formula correctness tests.
 *
 * Tests verify fixes from Part 3 of the plan:
 * - burns energy uses 32.5 kcal/kg (not 27.5)
 * - BMR uses actual weight for normal/underweight patients (not IBW)
 * - Pediatric growth allowance is age-banded (not flat +20)
 * - PatientMetrics accepts activityFactor
 * - ACTIVITY_FACTORS export is present with correct values
 * - classifyNutritionalStatus covers full spectrum
 * - calcTEE uses activityFactor from metrics
 * - calcBmrWeight returns AjBW only if >120% IBW, else actual
 */
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import {
  calcIBW,
  calcAjBW,
  calcWorkingWeight,
  calcBMR,
  calcTEE,
  calcBmrWeight,
  autofillPrescription,
  ACTIVITY_FACTORS,
  classifyNutritionalStatus,
  classifyBmi,
  type PatientMetrics,
} from './nutritionCalculations.ts';

// ── ACTIVITY_FACTORS ────────────────────────────────────────────────────────

describe('ACTIVITY_FACTORS', () => {
  test('sedentary factor is 1.2', () => {
    assert.equal(ACTIVITY_FACTORS.sedentary.factor, 1.2);
  });
  test('light factor is 1.375', () => {
    assert.equal(ACTIVITY_FACTORS.light.factor, 1.375);
  });
  test('moderate factor is 1.55', () => {
    assert.equal(ACTIVITY_FACTORS.moderate.factor, 1.55);
  });
  test('very_active factor is 1.725', () => {
    assert.equal(ACTIVITY_FACTORS.very_active.factor, 1.725);
  });
  test('extra_active factor is 1.9', () => {
    assert.equal(ACTIVITY_FACTORS.extra_active.factor, 1.9);
  });
});

// ── calcBmrWeight ────────────────────────────────────────────────────────────

describe('calcBmrWeight', () => {
  test('returns actual weight when %IBW <= 120%', () => {
    // 75 kg actual, IBW = 70 kg → %IBW = 107% → use actual
    const actual = 75, ibw = 70;
    assert.equal(calcBmrWeight(actual, ibw), actual);
  });

  test('returns actual weight when %IBW is 90% (underweight)', () => {
    // 54 kg actual, IBW = 60 kg → %IBW = 90% → use actual
    const actual = 54, ibw = 60;
    assert.equal(calcBmrWeight(actual, ibw), actual);
  });

  test('returns AjBW when %IBW > 120%', () => {
    // 90 kg actual, IBW = 65 kg → %IBW = 138% → use AjBW = 65 + 0.25*(90-65) = 71.25
    const actual = 90, ibw = 65;
    const expected = calcAjBW(actual, ibw); // 71.25
    assert.equal(calcBmrWeight(actual, ibw), expected);
  });
});

// ── calcTEE with activityFactor ──────────────────────────────────────────────

describe('calcTEE with activityFactor', () => {
  test('uses provided activity factor instead of hardcoded 1.2', () => {
    const bmr = 1500;
    assert.equal(calcTEE(bmr, 1.55), 1500 * 1.55);
  });

  test('defaults to 1.2 when no factor provided', () => {
    const bmr = 1500;
    assert.equal(calcTEE(bmr), 1500 * 1.2);
  });
});

// ── PatientMetrics accepts activityFactor ────────────────────────────────────

describe('PatientMetrics activityFactor', () => {
  test('autofillPrescription uses activityFactor for diabetic_control TEE', () => {
    const metrics: PatientMetrics = {
      weightKg: 65,
      heightCm: 165,
      ageYears: 40,
      sex: 'Female',
      isAdult: true,
      activityFactor: 1.55, // Moderately active
    };
    const result1_2 = autofillPrescription('diabetic_control', null, { ...metrics, activityFactor: 1.2 });
    const result1_55 = autofillPrescription('diabetic_control', null, { ...metrics, activityFactor: 1.55 });
    // Higher PAL → higher energy
    assert.ok(result1_55.energy_kcal > result1_2.energy_kcal,
      `Expected ${result1_55.energy_kcal} > ${result1_2.energy_kcal}`);
  });
});

// ── Pregnancy / lactation PDRI add-on (FE↔BE parity) ──────────────────────────

describe('pregnancy / lactation modifier', () => {
  const base: PatientMetrics = {
    weightKg: 60, heightCm: 160, ageYears: 30, sex: 'Female', isAdult: true, activityFactor: 1.3,
  };

  test('pregnant adds +300 kcal and +27 g protein over baseline', () => {
    const none = autofillPrescription('diabetic_control', null, base);
    const preg = autofillPrescription('diabetic_control', null, { ...base, pregnancyLactationStatus: 'pregnant' });
    assert.equal(preg.energy_kcal - none.energy_kcal, 300);
    assert.equal(preg.protein_g - none.protein_g, 27);
    assert.match(preg.note ?? '', /Pregnant adjustment applied/);
  });

  test('lactating adds +500 kcal and +27 g protein', () => {
    const none = autofillPrescription('diabetic_control', null, base);
    const lact = autofillPrescription('diabetic_control', null, { ...base, pregnancyLactationStatus: 'lactating' });
    assert.equal(lact.energy_kcal - none.energy_kcal, 500);
    assert.equal(lact.protein_g - none.protein_g, 27);
  });

  test("status 'none' leaves the prescription unchanged", () => {
    const none = autofillPrescription('diabetic_control', null, base);
    const explicitNone = autofillPrescription('diabetic_control', null, { ...base, pregnancyLactationStatus: 'none' });
    assert.deepEqual(explicitNone, none);
  });
});

// ── Burns energy formula ──────────────────────────────────────────────────────

describe('high_protein burns energy', () => {
  test('uses 32.5 kcal/kg for burns stage (not 27.5)', () => {
    const metrics: PatientMetrics = {
      weightKg: 70,
      heightCm: 170,
      ageYears: 35,
      sex: 'Male',
      isAdult: true,
    };
    const result = autofillPrescription('high_protein', 'burns', metrics);
    const ibw = calcIBW(170, 'Male');
    const working = calcWorkingWeight(70, ibw);
    const expected = Math.round(working * 32.5);
    assert.equal(result.energy_kcal, expected,
      `Burns energy should be ${expected} (32.5 kcal/kg) but got ${result.energy_kcal}`);
  });

  test('mild_stress still uses 27.5 kcal/kg', () => {
    const metrics: PatientMetrics = {
      weightKg: 70,
      heightCm: 170,
      ageYears: 35,
      sex: 'Male',
      isAdult: true,
    };
    const result = autofillPrescription('high_protein', 'mild_stress', metrics);
    const ibw = calcIBW(170, 'Male');
    const working = calcWorkingWeight(70, ibw);
    const expected = Math.round(working * 27.5);
    assert.equal(result.energy_kcal, expected);
  });
});

// ── BMR uses actual weight for normal-weight patients ────────────────────────

describe('severe malnutrition prescription', () => {
  test('uses flat recommended targets without refeeding metadata', () => {
    const result = autofillPrescription('malnutrition', 'severe', {
      weightKg: 52,
      heightCm: 170,
      ageYears: 38,
      sex: 'Male',
      isAdult: true,
      activityFactor: 1.4,
    });

    assert.equal(result.energy_kcal, 1690);
    assert.equal(result.protein_g, 67);
    assert.equal(result.carbs_g, 239);
    assert.equal(result.fat_g, 52);
    assert.equal(result.fluid_ml, 1690);
    assert.equal('feeding_phase' in result, false);
    assert.equal('target_energy_kcal_range' in result, false);
    assert.doesNotMatch(result.note ?? '', /refeeding/i);
  });
});

describe('autofillPrescription BMR weight selection', () => {
  test('diabetic_control uses actual weight for normal-weight patient (not IBW)', () => {
    // 80 kg, IBW ≈ 68 kg → %IBW = 117% (within 90–120% → used to return IBW, now returns actual)
    const metrics: PatientMetrics = {
      weightKg: 80,
      heightCm: 170,
      ageYears: 40,
      sex: 'Male',
      isAdult: true,
      activityFactor: 1.2,
    };
    const result = autofillPrescription('diabetic_control', null, metrics);
    // BMR with actual (80 kg): 10*80 + 6.25*170 - 5*40 + 5 = 800 + 1062.5 - 200 + 5 = 1667.5
    // BMR with IBW (≈68 kg): 10*68 + 6.25*170 - 5*40 + 5 = 680 + 1062.5 - 200 + 5 = 1547.5
    // TEE with actual × 1.2 = 2001; TEE with IBW × 1.2 = 1857
    // Energy should be closer to 2001 (actual weight used for BMR)
    const bmrWithActual = calcBMR(80, 170, 40, 'Male');
    const teeWithActual = calcTEE(bmrWithActual, 1.2);
    assert.equal(result.energy_kcal, Math.round(teeWithActual),
      `Expected TEE from actual weight (${Math.round(teeWithActual)}) but got ${result.energy_kcal}`);
  });
});

// ── Pediatric growth allowance is age-banded ─────────────────────────────────

describe('autofillPediatric growth allowance', () => {
  test('infant under 6 months gets +70 kcal growth allowance', () => {
    const metrics: PatientMetrics = {
      weightKg: 6,
      heightCm: 60,
      ageYears: 0.3, // 3.6 months
      sex: 'Male',
      isAdult: false,
    };
    const result = autofillPrescription('malnutrition', null, metrics);
    // Schofield male <3y: 59.512 * 6 - 30.4 = 356.7 - 30.4 = 326.3; TEE = 326.3 * 1.2 = 391.6; +70 growth = 461.6
    // So energy_kcal should be ~462, definitely > 391
    const baseSchofield = 59.512 * 6 - 30.4;
    const baseTEE = baseSchofield * 1.2;
    assert.ok(result.energy_kcal >= Math.round(baseTEE + 65),
      `Expected growth allowance ~70 added; got energy ${result.energy_kcal} vs base TEE ${Math.round(baseTEE)}`);
  });

  test('child age 1-3 gets +20 kcal growth allowance (not +70)', () => {
    const metrics: PatientMetrics = {
      weightKg: 12,
      heightCm: 85,
      ageYears: 2,
      sex: 'Female',
      isAdult: false,
    };
    const result = autofillPrescription('malnutrition', null, metrics);
    const baseSchofield = 58.317 * 12 - 31.1;
    const baseTEE = baseSchofield * 1.2;
    // Should be baseTEE + 20 (not +70)
    const expected = Math.round(baseTEE + 20);
    assert.equal(result.energy_kcal, expected,
      `Expected ${expected} (TEE+20) but got ${result.energy_kcal}`);
  });

  test('older child (4+ years) gets +15 kcal growth allowance', () => {
    const metrics: PatientMetrics = {
      weightKg: 20,
      heightCm: 110,
      ageYears: 6,
      sex: 'Male',
      isAdult: false,
    };
    const result = autofillPrescription('weight_gain', null, metrics);
    // Schofield male age 3–10: 22.706 * weight + 504.3
    const baseSchofield = 22.706 * 20 + 504.3;
    const baseTEE = baseSchofield * 1.2;
    const expected = Math.round(baseTEE + 15);
    assert.equal(result.energy_kcal, expected,
      `Expected ${expected} (TEE+15) but got ${result.energy_kcal}`);
  });
});

// ── classifyNutritionalStatus ─────────────────────────────────────────────────

describe('classifyNutritionalStatus', () => {
  test('returns Severe Malnutrition for BMI < 16', () => {
    const result = classifyNutritionalStatus(15.5, 65);
    assert.equal(result.label, 'Severe Malnutrition');
    assert.equal(result.severity, 'severe_malnutrition');
  });

  test('returns Severe Malnutrition for %IBW < 70 even if BMI is borderline', () => {
    // %IBW < 70 should override
    const result = classifyNutritionalStatus(17.0, 68);
    assert.equal(result.label, 'Severe Malnutrition');
  });

  test('returns Moderate Malnutrition for BMI 16-16.9', () => {
    const result = classifyNutritionalStatus(16.5, 75);
    assert.equal(result.label, 'Moderate Malnutrition');
  });

  test('returns Mild Malnutrition for BMI 17-18.4', () => {
    const result = classifyNutritionalStatus(17.5, 87);
    assert.equal(result.label, 'Mild Malnutrition / Underweight');
  });

  test('returns Normal for BMI 18.5-24.9', () => {
    const result = classifyNutritionalStatus(22.0, 100);
    assert.equal(result.label, 'Normal');
    assert.equal(result.severity, 'normal');
  });

  // Asia-Pacific cut-points (D1): Overweight 23–24.9 · Obese I 25–29.9 ·
  // Obese II ≥30 (split at 35 for weight_loss staging, D2).
  test('returns Overweight for AP BMI 23-24.9', () => {
    const result = classifyNutritionalStatus(24.0, 110);
    assert.equal(result.label, 'Overweight');
    assert.equal(result.suggestedStage, 'overweight');
  });

  test('returns Obese Class I for AP BMI 25-29.9', () => {
    const result = classifyNutritionalStatus(27.0, 112);
    assert.equal(result.label, 'Obese Class I');
    assert.equal(result.suggestedGoal, 'weight_loss');
    assert.equal(result.suggestedStage, 'class_1');
  });

  test('returns Obese Class II for AP BMI 30-34.9', () => {
    const result = classifyNutritionalStatus(32.0, 125);
    assert.equal(result.label, 'Obese Class II');
    assert.equal(result.suggestedStage, 'class_2');
  });

  test('returns Obese Class II (Severe) for AP BMI >= 35', () => {
    const result = classifyNutritionalStatus(37.0, 140);
    assert.equal(result.label, 'Obese Class II (Severe)');
    assert.equal(result.suggestedStage, 'class_3');
  });

  test('returns suggestedGoal for malnutrition stages', () => {
    const severe = classifyNutritionalStatus(15.0, 65);
    assert.equal(severe.suggestedGoal, 'malnutrition');
    assert.equal(severe.suggestedStage, 'severe');
  });
});

describe('classifyBmi', () => {
  test('reuses Asia-Pacific BMI bands without an IBW override', () => {
    assert.equal(classifyBmi(22).label, 'Normal');
    assert.equal(classifyBmi(24).label, 'Overweight');
    assert.equal(classifyBmi(27).label, 'Obese Class I');
    assert.equal(classifyBmi(32).label, 'Obese Class II');
    assert.equal(classifyBmi(37).label, 'Obese Class II (Severe)');
  });
});
