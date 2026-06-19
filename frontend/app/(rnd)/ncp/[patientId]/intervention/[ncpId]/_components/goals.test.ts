import { describe, expect, test } from "vitest";
import { GOALS } from "./goals";

describe("GOALS data", () => {
  test("diabetic_control exposes stage_1/stage_2/stage_3 (engine + doc require them)", () => {
    const diabetic = GOALS.find((g) => g.value === "diabetic_control");
    expect(diabetic).toBeDefined();
    const stageValues = diabetic?.stages?.map((s) => s.value) ?? [];
    expect(stageValues).toEqual(["stage_1", "stage_2", "stage_3"]);
  });

  test("every goal except custom has at least one stage", () => {
    for (const g of GOALS) {
      if (g.value === "custom") continue;
      expect(g.stages?.length ?? 0).toBeGreaterThan(0);
    }
  });
});
