import { describe, expect, it } from "vitest";

import { getAnthropometricSafetyWarning, getWaistHipAssessment } from "./anthropometricSafety";
import { assessmentTextError } from "./assessmentValidation";

const NOW = new Date("2026-07-06T12:00:00Z");

describe("waist-to-hip assessment", () => {
  const input = { waistCm: 90, hipCm: 100, sex: "Male", ageYears: 40 };
  it("recalculates when hip changes and uses both measured circumferences", () => {
    expect(getWaistHipAssessment(input)).toMatchObject({ ratio: 0.9, risk: "High Risk", error: null });
    expect(getWaistHipAssessment({ ...input, hipCm: 120 })).toMatchObject({ ratio: 0.75, risk: "Low Risk" });
  });
  it("classifies the unrounded ratio at both sex-specific cutoffs", () => {
    expect(getWaistHipAssessment({ ...input, waistCm: 89.9 }).risk).toBe("Low Risk");
    expect(getWaistHipAssessment({ ...input, waistCm: 84.9, sex: "Female" }).risk).toBe("Low Risk");
    expect(getWaistHipAssessment({ ...input, waistCm: 85, sex: "Female" }).risk).toBe("High Risk");
  });
  it.each([1, 0, -1, 250.01, Number.NaN, Number.POSITIVE_INFINITY])("does not calculate or assign low risk to invalid waist %s", (waistCm) => {
    expect(getWaistHipAssessment({ ...input, waistCm })).toMatchObject({ ratio: null, risk: null });
    expect(getWaistHipAssessment({ ...input, waistCm }).error).toBeTruthy();
    expect(assessmentTextError({ waist_cm: waistCm })).toBeTruthy();
  });
  it("rejects invalid hips and leaves missing measurements unclassified", () => {
    expect(getWaistHipAssessment({ ...input, hipCm: 1 })).toMatchObject({ ratio: null, risk: null });
    expect(getWaistHipAssessment({ ...input, hipCm: null })).toEqual({ ratio: null, risk: null, error: null });
    expect(assessmentTextError({ hip_cm: 1 })).toBeTruthy();
  });
  it("does not apply adult WHR risk categories to children, pregnancy, or unknown sex", () => {
    expect(getWaistHipAssessment({ ...input, ageYears: 8 }).risk).toBeNull();
    expect(getWaistHipAssessment({ ...input, sex: null }).risk).toBeNull();
    expect(getWaistHipAssessment({ ...input, pregnancyLactationStatus: "pregnant_t2" }).risk).toBeNull();
  });
});

describe("getAnthropometricSafetyWarning", () => {
  it("warns for impossible infant anthropometrics without blocking", () => {
    const warning = getAnthropometricSafetyWarning({
      dob: "2026-06-06",
      weightKg: 70,
      heightCm: 183,
      now: NOW,
    });

    expect(warning).toContain("1 month");
    expect(warning).toContain("70 kg");
    expect(warning).toContain("183 cm");
    expect(warning).toContain("Confirm");
  });

  it("does not warn for plausible infant anthropometrics", () => {
    expect(getAnthropometricSafetyWarning({
      dob: "2026-06-06",
      weightKg: 4.5,
      heightCm: 55,
      now: NOW,
    })).toBeNull();
  });

  it("does not warn for adult anthropometrics", () => {
    expect(getAnthropometricSafetyWarning({
      dob: "1996-07-06",
      weightKg: 70,
      heightCm: 183,
      now: NOW,
    })).toBeNull();
  });

  it("does not warn when age cannot be determined", () => {
    expect(getAnthropometricSafetyWarning({
      dob: "not-a-date",
      weightKg: 70,
      heightCm: 183,
      now: NOW,
    })).toBeNull();
  });
});
