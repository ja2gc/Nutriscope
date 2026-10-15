import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("meal-plan snack exclusion option", () => {
  test("sends the explicit checkbox choice through the existing generation request", () => {
    const editor = readFileSync(join(
      process.cwd(),
      "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx",
    ), "utf8");
    const service = readFileSync(join(process.cwd(), "services/mealPlanService.ts"), "utf8");

    expect(editor).toContain("Exclude snacks");
    expect(editor).toMatch(/type="checkbox"[\s\S]*checked=\{excludeSnacks\}/);
    expect(editor).toContain("exclude_snacks: excludeSnacks");
    expect(service).toContain("exclude_snacks?: boolean");
  });

  test("sends a selected Carb UUID or explicit exclusion", () => {
    const editor = readFileSync(join(
      process.cwd(),
      "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx",
    ), "utf8");
    const service = readFileSync(join(process.cwd(), "services/mealPlanService.ts"), "utf8");

    expect(editor).toContain("Exclude carbs");
    expect(editor).toContain("carb_menu_item_id: carb!.id");
    expect(editor).toContain("Choose Carb Menu Item");
    expect(service).toContain("exclude_carbs: false; carb_menu_item_id: string");
    expect(editor).not.toContain("use_rice_as_carb");
    expect(service).not.toContain("use_rice_as_carb");
  });
});
