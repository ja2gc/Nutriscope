import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

import { getAnthropometricSafetyWarning, getWaistHipAssessment, getMuacScreening } from "./anthropometricSafety";
import { assessmentTextError } from "./assessmentValidation";

const NOW = new Date("2026-07-06T12:00:00Z");

it('ships identical verified WHO monthly reference data in both application builds', () => {
  const frontend = readFileSync(resolve('lib/who-bmi-for-age.json'), 'utf8');
  expect(frontend).toBe(readFileSync(resolve('../backend/resources/who-bmi-for-age.json'), 'utf8'));
  const reference = JSON.parse(frontend);
  expect(reference.first_month).toBe(61);
  expect(reference.last_month).toBe(228);
  for (const sex of ['Male', 'Female']) {
    expect(reference[sex]).toHaveLength(168);
    for (const row of reference[sex]) expect(row.every((value: number, index: number) => Number.isFinite(value) && (index === 0 || value > row[index - 1]))).toBe(true);
  }
});

describe('age-specific MUAC screening', () => {
  it('uses the 6–59-month screening cutoffs without declaring a nutrition diagnosis', () => {
    expect(getMuacScreening(114, 6)?.label).toBe('Very low MUAC');
    expect(getMuacScreening(115, 24)?.label).toBe('Low MUAC');
    expect(getMuacScreening(124.9, 59)?.label).toBe('Low MUAC');
    expect(getMuacScreening(125, 59)?.label).toBe('Above screening cutoff');
  });
  it('does not apply child screening cutoffs to infants under six months, older children, adults or unknown age', () => {
    for (const age of [null, 5, 60, 120, 420, NaN]) expect(getMuacScreening(110, age)).toBeNull();
    for (const value of [null, 0, -1, NaN, Infinity]) expect(getMuacScreening(value, 24)).toBeNull();
  });
});

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
