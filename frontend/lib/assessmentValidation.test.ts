import { describe, expect, test } from "vitest";
import { assessmentTextError } from "./assessmentValidation";

describe("assessment text validation", () => {
  test.each([
    ["present_diet", 250], ["dietary_intake", 250], ["dietary_restrictions", 100],
    ["supplements", 100], ["knowledge_notes", 250], ["nutrient_drug_interaction", 100],
    ["chewing_swallowing_details", 150], ["constipation_details", 150], ["diarrhea_details", 150],
    ["primary_diagnosis_other", 150], ["medical_history", 250], ["social_history", 250],
    ["religion", 100], ["food_intolerance", 100], ["rnd_summary", 3500],
  ])("accepts %s at its limit and rejects one character over", (field, limit) => {
    expect(assessmentTextError({ [field]: "x".repeat(limit) })).toBeNull();
    expect(assessmentTextError({ [field]: "x".repeat(limit + 1) })).toContain(String(limit));
  });

  test("limits food dislikes by entry length and count", () => {
    expect(assessmentTextError({ food_dislikes: Array(5).fill("x".repeat(50)) })).toBeNull();
    expect(assessmentTextError({ food_dislikes: ["x".repeat(51)] })).toContain("50");
    expect(assessmentTextError({ food_dislikes: Array(6).fill("Rice") })).toContain("5");
  });

  test("counts Unicode characters the same way as API string limits", () => {
    expect(assessmentTextError({ dietary_restrictions: "😀".repeat(100) })).toBeNull();
    expect(assessmentTextError({ dietary_restrictions: "😀".repeat(101) })).not.toBeNull();
    expect(assessmentTextError({ food_dislikes: ["😀".repeat(50)] })).toBeNull();
    expect(assessmentTextError({ medications: ["😀".repeat(100)] })).toBeNull();
  });

  test("checks existing medication arrays as one combined entry without discarding values", () => {
    const medications = ["Metformin", "Amlodipine"];
    expect(assessmentTextError({ medications })).toBeNull();
    expect(medications).toEqual(["Metformin", "Amlodipine"]);
    expect(assessmentTextError({ medications: ["x".repeat(100), "Aspirin"] })).toContain("100");
  });

  test("ABG allows six characters and at most two decimals", () => {
    for (const abg of ["7.4", "7.45", "123.45", "123456", ""]) {
      expect(assessmentTextError({ biochemical_data: { abg } })).toBeNull();
    }
    for (const abg of ["1234.56", "7.456", "1e2", "abc"]) {
      expect(assessmentTextError({ biochemical_data: { abg } })).not.toBeNull();
    }
    expect(assessmentTextError({ biochemical_data: { bp: "x".repeat(21) } })).toContain("20");
  });
});
