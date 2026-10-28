import { describe, expect, it } from "vitest";
import { buildDiagnosisProblemText } from "./diagnosisBuilder";

describe("buildDiagnosisProblemText", () => {
  it("composes a canonical term with its specified nutrient without codes or category labels", () => {
    expect(buildDiagnosisProblemText({ domain: "NI", niDirection: "Inadequate", niNutrient: "Vitamin D", ncProblems: [], nbProblems: [],
      niNutrientSpecify: "Vitamins" })).toBe("Inadequate vitamin intake: D");
  });
  it("represents an explicit NO decision without inventing a PES problem", () => {
    expect(buildDiagnosisProblemText({ domain: "NO", niDirection: "Inadequate", niNutrient: "", ncProblems: [], nbProblems: [] })).toBe("No nutrition diagnosis at this time");
  });
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

