import { describe, expect, test } from "vitest";
import { canonicalClinicalUnit, clinicalIngredientFactor, clinicalRecipeUnits } from "./clinicalUnits";

describe("clinical Ingredient units", () => {
  test("offers the full same-family unit set", () => {
    expect(clinicalRecipeUnits("grams")).toEqual(["mg", "g", "kg", "oz", "lb"]);
    expect(clinicalRecipeUnits("ml")).toEqual(["mL", "L", "tsp", "tbsp", "cup"]);
    expect(clinicalRecipeUnits("PCS")).toEqual(["piece"]);
    expect(clinicalRecipeUnits("slice")).toEqual(["slice"]);
    expect(clinicalRecipeUnits("")).toEqual([]);
  });

  test("normalizes aliases and case without inventing count weights", () => {
    expect(canonicalClinicalUnit(" millilitre ")).toBe("mL");
    expect(canonicalClinicalUnit("LITER")).toBe("L");
    expect(canonicalClinicalUnit("pieces")).toBe("piece");
    expect(clinicalIngredientFactor(0.5, "kg", 100, "g")).toBe(5);
    expect(clinicalIngredientFactor(1, "cup", 100, "mL")).toBeCloseTo(2.36588, 10);
    expect(clinicalIngredientFactor(2, "pcs", 1, "piece")).toBe(2);
    expect(clinicalIngredientFactor(2, "slice", 1, "slice")).toBe(2);
  });

  test("rejects incompatible units and invalid quantities or baselines", () => {
    for (const [from, to] of [["cup", "g"], ["piece", "g"], ["slice", "piece"], ["", ""]]) {
      expect(clinicalIngredientFactor(1, from, 100, to)).toBeNull();
    }
    for (const value of [0, -1, NaN, Infinity]) {
      expect(clinicalIngredientFactor(value, "g", 100, "g")).toBeNull();
      expect(clinicalIngredientFactor(1, "g", value, "g")).toBeNull();
    }
  });

  test("uses every specified mass and volume factor without intermediate rounding", () => {
    for (const [unit, grams] of [["mg", 0.001], ["g", 1], ["kg", 1000], ["oz", 28.349523125], ["lb", 453.59237]] as const) {
      expect(clinicalIngredientFactor(1, unit, 1, "g")).toBeCloseTo(grams, 10);
    }
    for (const [unit, ml] of [["mL", 1], ["L", 1000], ["tsp", 4.92892], ["tbsp", 14.7868], ["cup", 236.588]] as const) {
      expect(clinicalIngredientFactor(1, unit, 1, "mL")).toBeCloseTo(ml, 10);
    }
    expect(clinicalIngredientFactor(0.000001, "g", 100, "g")).toBe(0.00000001);
  });
});
