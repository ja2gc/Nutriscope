import { describe, expect, test } from "vitest";
import { buildAssessmentSummary, type AssessmentSummaryInput } from "./assessmentSummary";
import { ASSESSMENT_TEXT_LIMITS } from "./assessmentValidation";

function input(overrides: Partial<AssessmentSummaryInput> = {}): AssessmentSummaryInput {
  return { assessment: {}, anthropometrics: {}, risk: null, ...overrides };
}

describe("buildAssessmentSummary", () => {
  test("prioritizes referral, context and intake in three concise sentences", () => {
    const result = buildAssessmentSummary(input({
      referral: { diagnosis: "Suspected gestational diabetes", dietPrescription: "Carbohydrate-controlled diet" },
      assessment: { pregnancy_lactation_status: "pregnant_t2", physical_activity_level: "sedentary", energy_intake_status: "Sub-optimal", chewing_swallowing_difficulties: "Yes", chewing_swallowing_details: "Coughs with thin liquids", medications: ["Medication list"], food_dislikes: ["Broccoli"] },
      anthropometrics: { bmi: 24.2 },
      risk: { label: "High Risk" },
    }));
    expect(result).toBe("Referral diagnosis: Suspected gestational diabetes; prescribed diet: Carbohydrate-controlled diet. BMI 24.2; pregnant — second trimester; Activity estimate: Sedentary. Energy intake: Sub-optimal; chewing/swallowing difficulty: Coughs with thin liquids; nutrition screening: High Risk.");
    expect(result).not.toMatch(/Medication list|Broccoli|Low albumin|7 points|automatic/);
  });

  test("uses the supplied BMI class without substituting legacy screening status", () => {
    expect(buildAssessmentSummary(input({ assessment: { nutritional_status: "Severe Malnutrition" }, anthropometrics: { bmi: 22, nutritionalStatus: "Normal" } }))).toBe("BMI 22 (Normal).");
    expect(buildAssessmentSummary(input({ anthropometrics: { bmi: 22 } }))).toBe("BMI 22.");
  });

  test.each(["pregnant_t1", "pregnant_t2", "pregnant_t3", "pregnant_unspecified", "lactating"] as const)("withholds a BMI class during %s", pregnancy => {
    const result = buildAssessmentSummary(input({ assessment: { pregnancy_lactation_status: pregnancy }, anthropometrics: { bmi: 28, nutritionalStatus: "Overweight" } }));
    expect(result).toContain("BMI 28");
    expect(result).not.toContain("Overweight");
    if (pregnancy.startsWith("pregnant")) expect(result).not.toContain(pregnancy);
  });

  test("withholds BMI classification with edema and includes the fluid context", () => {
    expect(buildAssessmentSummary(input({ assessment: { edema_present: true }, anthropometrics: { bmi: 28, nutritionalStatus: "Overweight" } }))).toBe("BMI 28; edema present.");
  });

  test("does not describe a small recorded weight change as a concern", () => {
    expect(buildAssessmentSummary(input({ assessment: { weight: 68.6, usual_weight: 70, weight_loss_percentage: 2, weight_change_period_value: 4, weight_change_period_unit: "months" }, anthropometrics: { bmi: 22, nutritionalStatus: "Normal" } }))).toBe("BMI 22 (Normal).");
  });

  test("omits nutrient lists and does not select arbitrary abnormal labs", () => {
    expect(buildAssessmentSummary(input({ assessment: { intake_nutrients: { energy_kcal: 1800, protein_g: 65 }, biochemical_data: { albumin: 3, sodium: 140 } } }))).toBe("");
  });

  test("does not infer default PAL or normal findings for missing values", () => {
    expect(buildAssessmentSummary(input())).toBe("");
    expect(buildAssessmentSummary(input({ assessment: { physical_activity_level: null, pregnancy_lactation_status: "none" }, anthropometrics: { bmi: NaN, nutritionalStatus: "Normal" } }))).toBe("");
  });

  test("does not report negative symptoms as barriers", () => {
    expect(buildAssessmentSummary(input({ assessment: { chewing_swallowing_difficulties: "No", constipation: "No", diarrhea_notes: "No", appetite_changes: "decreased", functional_assessment: "Independent" } }))).toBe("Appetite: decreased.");
  });

  test("includes feeding assistance as a fallback", () => {
    expect(buildAssessmentSummary(input({ assessment: { functional_assessment: "Needs assistance" } }))).toBe("Functional status: Needs assistance.");
  });

  test("includes support and prefers restrictions over a routine diet", () => {
    expect(buildAssessmentSummary(input({ assessment: { nutrition_support: "tube_feeding", dietary_restrictions: "Fluid restriction per referral", present_diet: "Routine diet" } }))).toBe("Nutrition support: Tube Feeding; dietary restrictions: Fluid restriction per referral.");
  });

  test("includes allergies without dumping an unlimited list", () => {
    expect(buildAssessmentSummary(input({ assessment: { allergies: ["Milk", "Egg", "Fish"] } }))).toBe("Allergies: Milk, Egg (+1 more).");
  });

  test("bounds long source clauses, preserves qualifiers and leaves source unchanged", () => {
    const note = "Suspected diabetes.\nDiagnosis not yet confirmed. " + "Additional detail ".repeat(60);
    const assessment = { dietary_intake: note, medical_history: "Recorded history" };
    const result = buildAssessmentSummary(input({ referral: { diagnosis: note, dietPrescription: note }, assessment, anthropometrics: { bmi: 22, nutritionalStatus: "Normal" } }));
    expect(result).toContain("Suspected diabetes; Diagnosis not yet confirmed");
    expect(result).toContain("…");
    expect(result).not.toContain("\n");
    expect(result.length).toBeLessThan(ASSESSMENT_TEXT_LIMITS.rnd_summary);
    expect(result.split(/(?<!\d)\.\s+/)).toHaveLength(3);
    expect(assessment.dietary_intake).toBe(note);
  });

  test("uses history as fallback without calling it a referral diagnosis", () => {
    expect(buildAssessmentSummary(input({ assessment: { medical_history: "Type 2 diabetes" } }))).toBe("Medical history: Type 2 diabetes.");
  });

  test("bounds unbroken referral text within the editable summary limit", () => {
    const note = "x".repeat(5000);
    const result = buildAssessmentSummary(input({ referral: { diagnosis: note, dietPrescription: note }, assessment: { dietary_intake: note } }));
    expect(result.length).toBeLessThan(ASSESSMENT_TEXT_LIMITS.rnd_summary);
    expect(result).toContain("…");
  });
});
