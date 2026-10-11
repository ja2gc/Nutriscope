import { describe, expect, test } from "vitest";
import { compatibleRecipeUnits, foodServiceIngredientCost, foodServiceUnitFactor } from "./foodServiceUnits";

describe("Food Service recipe units", () => {
  test("converts within mass or volume and never guesses across them", () => {
    expect(foodServiceUnitFactor("g", "kg")).toBe(0.001);
    expect(foodServiceUnitFactor("mL", "L")).toBe(0.001);
    expect(foodServiceUnitFactor("tbsp", "kg")).toBeNull();
    expect(foodServiceUnitFactor("pc", "pc")).toBe(1);
  });

  test("costs measured Auto Grocery only", () => {
    const auto = { base_unit: "kg", unit_cost: 362, include_in_generated_lists: true };
    expect(foodServiceIngredientCost(auto, 3500, "g")).toBe(1267);
    expect(foodServiceIngredientCost(auto, 2, "tbsp")).toBe(0);
    expect(foodServiceIngredientCost({ ...auto, include_in_generated_lists: false }, 3500, "g")).toBe(0);
  });

  test("offers only matching recipe units for Auto Grocery", () => {
    expect(compatibleRecipeUnits("kg", true)).toContain("g");
    expect(compatibleRecipeUnits("kg", true)).not.toContain("tbsp");
    expect(compatibleRecipeUnits("pc", true)).toEqual(["pc"]);
    expect(compatibleRecipeUnits("kg", false)).toContain("tbsp");
  });
});
