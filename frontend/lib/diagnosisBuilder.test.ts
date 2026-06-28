import { describe, expect, it } from "vitest";
import { buildDiagnosisProblemText } from "./diagnosisBuilder";

describe("buildDiagnosisProblemText", () => {
  it("keeps an AI NI label that does not match the manual intake builder shape", () => {
    expect(
      buildDiagnosisProblemText({
        domain: "NI",
        niDirection: "Inadequate",
        niNutrient: "",
        ncProblems: [],
        nbProblems: [],
        problemOverride: "Inadequate oral food/beverage intake",
      }),
    ).toBe("Inadequate oral food/beverage intake");
  });
});

