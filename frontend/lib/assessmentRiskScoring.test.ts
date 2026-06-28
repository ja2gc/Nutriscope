import { describe, expect, test } from "vitest";
import { deriveRiskScore } from "./assessmentRiskScoring";

describe("deriveRiskScore", () => {
  test("reflects live assessment edits before save", () => {
    const result = deriveRiskScore({
      screeningType: "adult",
      ibwPercentage: 82,
      weightLossPercentage: 12.5,
      chewingSwallowingDifficulties: "chewing pain",
      biochemicalData: { albumin: 3.2, bun: 24 },
      sex: "Female",
    });

    expect(result.checkedFactors).toEqual([
      "screening_criteria",
      "ibw_limit",
      "unintentional_weight_loss",
      "mechanical_digestive_problem",
      "low_albumin",
      "significant_lab_result",
    ]);
    expect(result.score).toBe(7);
  });

  test("does not keep stale factors when live inputs normalize", () => {
    const result = deriveRiskScore({
      screeningType: "adult",
      ibwPercentage: 101,
      weightLossPercentage: null,
      biochemicalData: { albumin: 4.1, bun: 14 },
      sex: "Male",
    });

    expect(result.checkedFactors).toEqual(["screening_criteria"]);
    expect(result.score).toBe(1);
  });
});
