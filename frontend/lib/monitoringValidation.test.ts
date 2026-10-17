import { describe, expect, it } from "vitest";
import { monitoringTextError } from "./monitoringValidation";

describe("monitoring text boundaries", () => {
  it("uses assessment limits for shared fields", () => {
    expect(monitoringTextError({ dietary_restrictions: "x".repeat(100), food_dislikes: Array(5).fill("x".repeat(50)) })).toBeNull();
    expect(monitoringTextError({ dietary_restrictions: "x".repeat(101) })).toBeTruthy();
    expect(monitoringTextError({ food_dislikes: Array(6).fill("rice") })).toBeTruthy();
    expect(monitoringTextError({ food_dislikes: ["x".repeat(51)] })).toBeTruthy();
  });
  it("retains existing limits for monitoring-only notes", () => {
    for (const [key, limit] of Object.entries({ intake_notes: 400, symptoms: 400, clinical_summary: 600 })) {
      expect(monitoringTextError({ [key]: "x".repeat(limit) })).toBeNull();
      expect(monitoringTextError({ [key]: "x".repeat(limit + 1) })).toBeTruthy();
    }
  });
  it("validates each allergy and text lab without limiting preset choices", () => {
    expect(monitoringTextError({ allergies: ["x".repeat(161)] })).toBeTruthy();
    expect(monitoringTextError({ lab_values: { abg: "123.45", bp: "120/80" } })).toBeNull();
    expect(monitoringTextError({ lab_values: { abg: "7.456" } })).toBeTruthy();
    expect(monitoringTextError({ lab_values: { abg: "1234.56" } })).toBeTruthy();
  });
});
