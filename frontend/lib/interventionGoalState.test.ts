import { describe, expect, test } from "vitest";
import { buildGoalPrescriptionForm, nutrientKeysWithValues, weightTargetError } from "./interventionGoalState";

test('weight targets accept one decimal and complete ordered ranges only', () => {
  expect(weightTargetError({ target: '60.5' })).toBeNull();
  expect(weightTargetError({ min: '58', max: '62.5' })).toBeNull();
  for (const weight of [{ target: '60.55' }, { target: '401' }, { target: '0' }, { min: '60' }, { min: '62', max: '58' }]) {
    expect(weightTargetError(weight)).not.toBeNull();
  }
});

describe("buildGoalPrescriptionForm", () => {
  test("leaves unsupported pediatric totals blank and retains the confirmed fluid limit", () => {
    const form = buildGoalPrescriptionForm("general_diet", {
      energy_kcal: null, protein_g: null, carbs_g: null, fat_g: null, fluid_ml: 1600,
      fluid_mode: "restricted", nutrient_operators: { fluid_ml: "max" },
    });
    expect(form.energy_kcal).toBe("");
    expect(form.protein_g).toBe("");
    expect(form.fluid_ml).toBe("1600");
    expect(form.nutrient_operators?.fluid_ml).toBe("max");
  });
  test("replaces the previous goal's targets with the new goal's targets", () => {
    const previous = buildGoalPrescriptionForm("weight_loss", {
      energy_kcal: 1800,
      protein_g: 126,
      carbs_g: 180,
      fat_g: 55,
      fluid_ml: 2200,
      fiber_g: 25,
    });

    const next = buildGoalPrescriptionForm("renal_diet", {
      energy_kcal: 2100,
      protein_g: 56,
      carbs_g: 250,
      fat_g: 58,
      fluid_ml: 750,
      sodium_max_mg: 1500,
    });

    expect(previous.energy_kcal).toBe("1800");
    expect(previous.displayed_nutrients).toContain("fiber");

    expect(next.energy_kcal).toBe("2100");
    expect(next.protein_g).toBe("56");
    expect(next.fluid_ml).toBe("750");
    expect(next.displayed_nutrients).toEqual(["sodium"]);
    expect(next.displayed_nutrients).not.toContain("fiber");
  });

  test("does not display micronutrients without numeric targets", () => {
    const form = buildGoalPrescriptionForm("malnutrition", {
      energy_kcal: 1690,
      protein_g: 67,
      carbs_g: 239,
      fat_g: 52,
      fluid_ml: 1690,
    });

    expect(form.displayed_nutrients).toEqual([]);
    expect(form.micronutrient_limits).toEqual({});
  });

  test("keeps only micronutrients with real values", () => {
    expect(nutrientKeysWithValues({
      potassium: { unit: "mg" },
      sodium: { max: 2000, unit: "mg" },
      fiber: { min: 25, unit: "g" },
    })).toEqual(["sodium", "fiber"]);
  });

  test("clears the prescription instead of keeping stale values when no autofill result exists", () => {
    const cleared = buildGoalPrescriptionForm("custom", null);

    expect(cleared.energy_kcal).toBe("");
    expect(cleared.displayed_nutrients).toEqual([]);
    expect(cleared.micronutrient_limits).toEqual({});
  });
});
