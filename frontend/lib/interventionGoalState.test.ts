import { describe, expect, test } from "vitest";
import { buildGoalPrescriptionForm } from "./interventionGoalState";

describe("buildGoalPrescriptionForm", () => {
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
    expect(next.displayed_nutrients).toEqual(expect.arrayContaining(["potassium", "phosphate", "sodium"]));
    expect(next.displayed_nutrients).not.toContain("fiber");
  });

  test("clears the prescription instead of keeping stale values when no autofill result exists", () => {
    const cleared = buildGoalPrescriptionForm("custom", null);

    expect(cleared.energy_kcal).toBe("");
    expect(cleared.displayed_nutrients).toEqual([]);
    expect(cleared.micronutrient_limits).toEqual({});
  });
});
