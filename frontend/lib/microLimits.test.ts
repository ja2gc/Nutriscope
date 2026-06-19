import { describe, expect, test } from "vitest";
import { microLimitsFromRx } from "@/lib/nutritionCalculations";

describe("microLimitsFromRx", () => {
  test("maps engine micro outputs to ALL_MICROS-keyed limits with values", () => {
    const limits = microLimitsFromRx(
      { sodium_max_mg: 2000, fiber_g: 25, cholesterol_max_mg: 200 },
      1600,
    );
    expect(limits.sodium).toEqual({ max: 2000, unit: "mg" });
    expect(limits.fiber).toEqual({ min: 25, unit: "g" });
    expect(limits.cholesterol).toEqual({ max: 200, unit: "mg" });
  });

  test("converts free_sugar_max_pct to grams using energy (pct of kcal / 4)", () => {
    // 10% of 1600 kcal = 160 kcal of sugar / 4 kcal/g = 40 g
    const limits = microLimitsFromRx({ free_sugar_max_pct: 0.1 }, 1600);
    expect(limits.free_sugars).toEqual({ max: 40, unit: "g" });
  });

  test("omits micros the engine didn't return", () => {
    const limits = microLimitsFromRx({ sodium_max_mg: 1500 }, 2000);
    expect(Object.keys(limits)).toEqual(["sodium"]);
  });
});
