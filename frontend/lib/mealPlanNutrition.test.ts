import { describe, expect, test } from "vitest";
import { foodFluidBalance } from "./mealPlanNutrition";

describe("foodFluidBalance", () => {
  test("totals food water by saved quantity and returns remaining drink guidance", () => {
    expect(foodFluidBalance([
      { quantity: "125", nutrient_snapshot: { serving_size: 100, water_g: 80 } },
      { quantity: "1", nutrient_snapshot: { serving_size: 1, water_g: 150 } },
    ], 1658)).toEqual({ requiredFluidMl: 1658, foodFluidMl: 250, remainingMl: 1408 });
  });

  test("never reports negative remaining drinks", () => {
    expect(foodFluidBalance([
      { quantity: "2", nutrient_snapshot: { serving_size: 1, water_g: 600 } },
    ], 1000)).toEqual({ requiredFluidMl: 1000, foodFluidMl: 1200, remainingMl: 0 });
  });
});
