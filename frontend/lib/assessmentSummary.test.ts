import { describe, expect, test } from "vitest";
import { buildAssessmentSummary, type AssessmentSummaryInput } from "./assessmentSummary";

function input(overrides: Partial<AssessmentSummaryInput> = {}): AssessmentSummaryInput {
  return {
    assessment: {},
    anthropometrics: {},
    labs: [],
    risk: null,
    ...overrides,
  };
}

describe("buildAssessmentSummary", () => {
  test("returns an empty draft when no meaningful assessment data exists", () => {
    expect(buildAssessmentSummary(input())).toBe("");
  });

  test("generates only populated categories in clinical order", () => {
    const result = buildAssessmentSummary(input({
      assessment: {
        weight: 70,
        height: 170,
        present_diet: "Soft diet",
        energy_intake_status: "Sub-optimal",
        appetite_changes: "decreased",
        medical_history: "Type 2 diabetes",
        functional_assessment: "Ambulatory",
      },
      anthropometrics: {
        bmi: 24.2,
        ibwKg: 68,
        percentIbw: 103,
        nutritionalStatus: "Normal",
      },
    }));

    expect(result).toContain("Anthropometrics: Weight 70 kg; height 170 cm; BMI 24.2; IBW 68 kg (103%); nutritional status Normal.");
    expect(result).toContain("Dietary / GI: Current diet: Soft diet. Energy intake is sub-optimal. Appetite is decreased.");
    expect(result).toContain("Clinical context: Medical history: Type 2 diabetes. Functional status: Ambulatory.");
    expect(result).not.toContain("Biochemical:");
    expect(result.indexOf("Anthropometrics:")).toBeLessThan(result.indexOf("Dietary / GI:"));
    expect(result.indexOf("Dietary / GI:")).toBeLessThan(result.indexOf("Clinical context:"));
  });

  test("marks abnormal labs while retaining entered normal results", () => {
    const result = buildAssessmentSummary(input({
      labs: [
        { label: "Albumin", value: 3, unit: "g/dL", status: "low" },
        { label: "Sodium", value: 140, unit: "mEq/L", status: "normal" },
      ],
    }));

    expect(result).toBe("Biochemical: Albumin 3 g/dL (LOW); Sodium 140 mEq/L.");
  });

  test("skips incomplete calculations without producing dangling text", () => {
    const result = buildAssessmentSummary(input({
      assessment: { weight: 70, edema_present: true },
      anthropometrics: { bmi: null, ibwKg: null, percentIbw: null },
    }));

    expect(result).toBe("Anthropometrics: Weight 70 kg.\n\nClinical context: Edema is present.");
    expect(result).not.toMatch(/undefined|null|NaN|IBW \(|BMI ;/);
  });

  test("normalizes note whitespace without truncating clinical text", () => {
    const note = "Poor intake   for three days.\nNeeds feeding assistance and close review.";
    const result = buildAssessmentSummary(input({
      assessment: {
        dietary_intake: note,
        chewing_swallowing_difficulties: "  Coughs with thin liquids  ",
      },
    }));

    expect(result).toContain("Reported intake: Poor intake for three days. Needs feeding assistance and close review.");
    expect(result).toContain("Chewing/swallowing: Coughs with thin liquids.");
    expect(result).not.toContain("  ");
  });

  test("includes current risk mode, score, and selected factors", () => {
    const result = buildAssessmentSummary(input({
      risk: {
        label: "Moderate",
        score: 3,
        mode: "manual",
        factors: ["Unintentional weight loss", "Low albumin"],
      },
    }));

    expect(result).toBe("Nutrition risk: Moderate risk (3 points, manual); factors: Unintentional weight loss, Low albumin.");
  });

  test("does not repeat risk when the badge label already includes it", () => {
    const result = buildAssessmentSummary(input({
      risk: {
        label: "High Risk",
        score: 7,
        mode: "automatic",
        factors: [],
      },
    }));

    expect(result).toBe("Nutrition risk: High Risk (7 points, automatic).");
  });
});
