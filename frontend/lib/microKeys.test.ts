import { describe, expect, test } from "vitest";
import { microKeys, ALL_MICROS, GOAL_MICRO_FLAGS } from "@/lib/nutritionCalculations";

describe("microKeys", () => {
  test("drops macro keys, keeps only real micronutrient keys", () => {
    // The bug: displayed_nutrients seeded/saved with MACRO keys leaked into the
    // micronutrient-limits UI. microKeys() must strip anything not in ALL_MICROS.
    const mixed = ["energy_kcal", "protein_g", "carbs_g", "fat_g", "fluid_ml", "sodium"];
    expect(microKeys(mixed)).toEqual(["sodium"]);
  });

  test("returns empty array when only macros are present", () => {
    expect(microKeys(["energy_kcal", "protein_g", "fat_g"])).toEqual([]);
  });

  test("preserves a goal's required micros (they are valid ALL_MICROS keys)", () => {
    const required = GOAL_MICRO_FLAGS.diabetic_control; // ['fiber','sodium','free_sugars']
    expect(microKeys([...required, "energy_kcal"])).toEqual(required);
  });

  test("every key it returns exists in ALL_MICROS", () => {
    const valid = new Set(ALL_MICROS.map((m) => m.key));
    for (const k of microKeys(["sodium", "potassium", "bogus_key", "energy_kcal"])) {
      expect(valid.has(k)).toBe(true);
    }
  });
});
