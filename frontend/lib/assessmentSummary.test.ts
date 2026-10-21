import { describe, expect, test } from "vitest";
import { buildAssessmentSummary, type AssessmentSummaryInput } from "./assessmentSummary";
import { assessmentTextError, ASSESSMENT_TEXT_LIMITS } from "./assessmentValidation";
import { RISK_FACTORS, scoreRiskFactors } from "./assessmentRiskScoring";

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
  test('includes recorded recall nutrients and omits selected blank nutrients', () => {
    const result = buildAssessmentSummary(input({ assessment: { intake_nutrients: { energy_kcal: 1800, protein_g: 65, micro_sodium: 1200, micro_iron: null } } }));
    expect(result).toContain('Energy 1800 kcal');
    expect(result).toContain('Protein 65 g');
    expect(result).toContain('Sodium 1200 mg');
    expect(result).not.toContain('Iron');
  });
  test("a fully populated summary with maximum source notes fits the editable summary limit", () => {
    const assessment = {
      present_diet: "x".repeat(250), dietary_intake: "x".repeat(250),
      dietary_restrictions: "x".repeat(100), supplements: "x".repeat(100),
      nutrient_drug_interaction: "x".repeat(100), food_intolerance: "x".repeat(100),
      medical_history: "x".repeat(250), appetite_changes: "nausea_vomiting",
      energy_intake_status: "Poor intake prior to admission" as const, dietary_intake_method: "other" as const,
      chewing_swallowing_difficulties: "Yes", constipation: "Yes", diarrhea_notes: "Yes",
      weight: 399.99, dry_weight_kg: 399.99, edema_present: true,
      weight_change_period_value: 104, weight_change_period_unit: "months" as const,
      functional_assessment: "Needs assistance", pregnancy_lactation_status: "pregnant_unspecified" as const,
      allergies: ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"],
      food_dislikes: Array(5).fill("x".repeat(50)), medications: ["x".repeat(100)],
    };
    const labs = [
      ["Albumin", 9.99, "g/dL"], ["Hemoglobin", 24.99, "g/dL"], ["Hematocrit", 74.99, "%"],
      ["Fasting Blood Sugar", 1999.99, "mg/dL"], ["HbA1c", 24.99, "%"], ["BUN", 299.99, "mg/dL"],
      ["Creatinine", 29.99, "mg/dL"], ["Sodium", 199.99, "mEq/L"], ["Potassium", 14.99, "mEq/L"],
      ["Calcium", 29.99, "mg/dL"], ["Phosphate", 19.99, "mg/dL"], ["Total Cholesterol", 4999.99, "mg/dL"],
      ["LDL", 4999.99, "mg/dL"], ["HDL", 4999.99, "mg/dL"], ["Triglycerides", 4999.99, "mg/dL"],
      ["URR", 0.01, "%"], ["ABG (pH)", 999.99, ""],
    ] as const;
    const result = buildAssessmentSummary(input({
      assessment,
      anthropometrics: {
        bmi: 4444.33, percentIbw: 1333, weightChangePercent: 39114.7, weightChangeDirection: "gain",
        nutritionalStatus: "Obese Class II (Severe)", muacClassification: "Moderate Malnutrition",
        whr: 12.49, whrRisk: "High Risk",
      },
      labs: labs.map(([label, value, unit]) => ({ label, value, unit, status: label === "URR" ? "low" : "high" })),
      risk: { label: "High Risk", score: scoreRiskFactors(RISK_FACTORS.map(f => f.key)), mode: "manual", factors: RISK_FACTORS.map(f => f.label) },
    }));
    expect(result.length).toBeGreaterThan(250);
    expect(result.length).toBeLessThanOrEqual(ASSESSMENT_TEXT_LIMITS.rnd_summary);
    expect(assessmentTextError({ ...assessment, rnd_summary: result })).toBeNull();
    expect(result).toContain("+4 more");
  });

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

    expect(result).toContain("Anthropometrics: 70 kg; BMI 24.2 (Normal).");
    expect(result).toContain("Dietary / GI: Current diet: Soft diet. Energy intake is sub-optimal. Appetite is decreased.");
    expect(result).toContain("Clinical context: Medical history: Type 2 diabetes. Functional status: Ambulatory.");
    expect(result).not.toContain("Biochemical:");
    expect(result.indexOf("Anthropometrics:")).toBeLessThan(result.indexOf("Dietary / GI:"));
    expect(result.indexOf("Dietary / GI:")).toBeLessThan(result.indexOf("Clinical context:"));
  });

  test("includes abnormal labs without dumping entered normal results", () => {
    const result = buildAssessmentSummary(input({
      labs: [
        { label: "Albumin", value: 3, unit: "g/dL", status: "low" },
        { label: "Sodium", value: 140, unit: "mEq/L", status: "normal" },
      ],
    }));

    expect(result).toBe("Biochemical: Albumin 3 g/dL (LOW).");
  });

  test("uses one short statement when all entered labs are normal", () => {
    expect(buildAssessmentSummary(input({
      labs: [{ label: "Sodium", value: 140, unit: "mEq/L", status: "normal" }],
    }))).toBe("Biochemical: Entered results are within listed reference ranges.");
  });

  test("skips incomplete calculations without producing dangling text", () => {
    const result = buildAssessmentSummary(input({
      assessment: { weight: 70, edema_present: true },
      anthropometrics: { bmi: null, ibwKg: null, percentIbw: null },
    }));

    expect(result).toBe("Anthropometrics: 70 kg.\n\nClinical context: Edema is present.");
    expect(result).not.toMatch(/undefined|null|NaN|IBW \(|BMI ;/);
  });

  test("formats structured weight duration and readable maternal status", () => {
    const result = buildAssessmentSummary(input({
      assessment: {
        weight_change_period_value: 1,
        weight_change_period_unit: "months",
        pregnancy_lactation_status: "pregnant_t2",
      },
      anthropometrics: {
        weightChangePercent: 4.5,
        weightChangeDirection: "loss",
      },
    }));

    expect(result).toContain("4.5% weight loss over 1 month");
    expect(result).toContain("Pregnancy/lactation: Pregnant — second trimester.");
    expect(result).not.toContain("pregnant_t2");
  });

  test("normalizes and compacts long source notes without changing the source", () => {
    const note = "Poor intake   for three days.\nNeeds feeding assistance and close review because fatigue limits every meal and snacks are usually refused despite repeated encouragement.";
    const result = buildAssessmentSummary(input({
      assessment: {
        dietary_intake: note,
        chewing_swallowing_difficulties: "  Coughs with thin liquids  ",
      },
    }));

    expect(result).toContain("Reported intake: Poor intake for three days. Needs feeding assistance and close review because fatigue limits every meal and snacks are usually…");
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

    expect(result).toBe("Nutrition risk: Moderate risk (3 points, manual); Unintentional weight loss, Low albumin.");
  });

  test("caps displayed risk factors and reports the remainder", () => {
    expect(buildAssessmentSummary(input({
      risk: {
        label: "High Risk",
        score: 7,
        mode: "automatic",
        factors: ["Weight loss", "Low albumin", "GI issue", "Abnormal lab", "Other"],
      },
    }))).toBe("Nutrition risk: High Risk (7 points, automatic); Weight loss, Low albumin, GI issue +2 more.");
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
