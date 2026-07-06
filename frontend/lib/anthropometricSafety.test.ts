import { describe, expect, it } from "vitest";

import { getAnthropometricSafetyWarning } from "./anthropometricSafety";

const NOW = new Date("2026-07-06T12:00:00Z");

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
