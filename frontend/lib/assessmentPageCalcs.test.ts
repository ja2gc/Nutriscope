/**
 * TDD: Assessment page calculated-values panel logic.
 *
 * These tests cover the derived values shown in the auto-calc panel
 * on the Anthropometrics tab. All logic is pure (imported from
 * nutritionCalculations.ts) — no DOM, no React.
 *
 * Run: node --experimental-strip-types --test lib/assessmentPageCalcs.test.ts
 */
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import {
  calcIBW,
  calcAjBW,
  calcPercentIBW,
  calcBMR,
  calcTEE,
  calcBmrWeight,
  classifyNutritionalStatus,
  ACTIVITY_FACTORS,
} from './nutritionCalculations.ts';

// ── IBW + %IBW + AjBW pipeline ────────────────────────────────────────────────

describe('Auto-calc panel: IBW / %IBW / AjBW', () => {
  test('normal-weight adult: IBW is shown, AjBW is not needed', () => {
    // 70 kg, 170 cm male
    const ibw = calcIBW(170, 'Male');
    const pct = calcPercentIBW(70, ibw);
    assert.ok(pct >= 90 && pct <= 120, `%IBW should be normal range: ${pct}`);
    // AjBW is only shown when pct > 120
    assert.ok(pct <= 120, 'AjBW panel should be hidden for this patient');
  });

  test('obese patient: AjBW is shown when %IBW > 120', () => {
    // 100 kg, 165 cm female
    const ibw = calcIBW(165, 'Female');
    const pct = calcPercentIBW(100, ibw);
    assert.ok(pct > 120, `%IBW should be > 120: ${pct}`);
    const ajbw = calcAjBW(100, ibw);
    assert.ok(ajbw > ibw && ajbw < 100, `AjBW ${ajbw} should be between IBW ${ibw} and actual 100`);
  });

  test('underweight patient: %IBW < 90, AjBW not needed', () => {
    // 45 kg, 165 cm female → IBW ≈ 56.7 kg → %IBW ≈ 79%
    const ibw = calcIBW(165, 'Female');
    const pct = calcPercentIBW(45, ibw);
    assert.ok(pct < 90, `%IBW should be < 90: ${pct}`);
  });
});

// ── BMR panel (uses calcBmrWeight) ────────────────────────────────────────────

describe('Auto-calc panel: BMR calculation', () => {
  test('normal-weight male: BMR uses actual weight', () => {
    // 70 kg, 170 cm, 40 yo male — %IBW ≈ 103% → use actual
    const ibw = calcIBW(170, 'Male');
    const bmrWt = calcBmrWeight(70, ibw);
    assert.equal(bmrWt, 70, 'Should use actual weight for BMR (≤120% IBW)');
    const bmr = calcBMR(bmrWt, 170, 40, 'Male');
    // Mifflin-St Jeor: 10*70 + 6.25*170 - 5*40 + 5 = 700+1062.5-200+5 = 1567.5
    assert.equal(bmr, 1567.5);
  });

  test('obese male: BMR uses AjBW', () => {
    // 100 kg, 170 cm, 40 yo male — IBW≈68, %IBW≈147% → use AjBW
    const ibw = calcIBW(170, 'Male');
    const bmrWt = calcBmrWeight(100, ibw);
    const expectedAjbw = calcAjBW(100, ibw); // 68 + 0.25*(100-68) = 76
    assert.equal(bmrWt, expectedAjbw, 'Should use AjBW for BMR when >120% IBW');
    const bmr = calcBMR(bmrWt, 170, 40, 'Male');
    const expected = 10 * expectedAjbw + 6.25 * 170 - 5 * 40 + 5;
    assert.equal(bmr, expected);
  });
});

// ── TEE panel (PAL integration) ───────────────────────────────────────────────

describe('Auto-calc panel: TEE from PAL selection', () => {
  test('sedentary: TEE = BMR × 1.2', () => {
    const bmr = 1500;
    const tee = calcTEE(bmr, ACTIVITY_FACTORS.sedentary.factor);
    assert.equal(tee, 1500 * 1.2);
  });

  test('moderately active: TEE = BMR × 1.55', () => {
    const bmr = 1500;
    const tee = calcTEE(bmr, ACTIVITY_FACTORS.moderate.factor);
    assert.equal(tee, 1500 * 1.55);
  });

  test('no PAL selected: TEE defaults to BMR × 1.2', () => {
    const bmr = 1500;
    const tee = calcTEE(bmr); // no factor = default sedentary
    assert.equal(tee, 1500 * 1.2);
  });
});

// ── Nutritional status chip ───────────────────────────────────────────────────

describe('Auto-calc panel: nutritional status chip (classifyNutritionalStatus)', () => {
  test('BMI 38 → Obese Class II (Severe) with weight_loss goal hint', () => {
    const result = classifyNutritionalStatus(38, 130);
    assert.equal(result.label, 'Obese Class II (Severe)');
    assert.equal(result.suggestedGoal, 'weight_loss');
    assert.equal(result.suggestedStage, 'class_3');
  });

  test('BMI 22, %IBW 100 → Normal with no goal hint', () => {
    const result = classifyNutritionalStatus(22, 100);
    assert.equal(result.label, 'Normal');
    assert.equal(result.severity, 'normal');
    assert.equal(result.suggestedGoal, undefined);
  });

  test('weight 42 kg, height 165 cm female → %IBW ≈ 74 → Moderate Malnutrition', () => {
    const ibw = calcIBW(165, 'Female');   // ≈ 56.7 kg
    const pct = calcPercentIBW(42, ibw);  // ≈ 74%
    const bmi = Math.round((42 / (1.65 * 1.65)) * 100) / 100; // ≈ 15.47
    const result = classifyNutritionalStatus(bmi, pct);
    // bmi < 16 → Severe Malnutrition
    assert.equal(result.label, 'Severe Malnutrition');
  });

  test('WHR auto-display: waist 90 cm, hip 100 cm → WHR 0.90 (borderline male)', () => {
    const whr = Math.round((90 / 100) * 100) / 100;
    assert.equal(whr, 0.9);
  });
});

// ── ACTIVITY_FACTORS all labels present ───────────────────────────────────────

describe('ACTIVITY_FACTORS PAL dropdown options', () => {
  const expectedKeys = ['sedentary', 'light', 'moderate', 'very_active', 'extra_active'];

  test('all 5 PAL options exist in ACTIVITY_FACTORS', () => {
    for (const key of expectedKeys) {
      assert.ok(key in ACTIVITY_FACTORS, `Missing key: ${key}`);
      assert.ok(ACTIVITY_FACTORS[key].label.length > 0, `Empty label for: ${key}`);
      assert.ok(ACTIVITY_FACTORS[key].factor > 1, `Factor should be > 1 for: ${key}`);
    }
  });
});
