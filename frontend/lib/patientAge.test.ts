import { describe, expect, it } from "vitest";

import { formatDateInputValue, formatPatientAge } from "./patientAge";

const NOW = new Date("2026-07-06T12:00:00Z");

describe("formatPatientAge", () => {
  it("returns N/A for missing or invalid dates", () => {
    expect(formatPatientAge(null, NOW)).toBe("N/A");
    expect(formatPatientAge("", NOW)).toBe("N/A");
    expect(formatPatientAge("not-a-date", NOW)).toBe("N/A");
  });

  it("formats patients younger than one month in days", () => {
    expect(formatPatientAge("2026-06-16", NOW)).toBe("20 days");
  });

  it("formats patients younger than one year in months", () => {
    expect(formatPatientAge("2026-06-06", NOW)).toBe("1 month");
    expect(formatPatientAge("2025-08-06", NOW)).toBe("11 months");
  });

  it("formats patients one year and older in years", () => {
    expect(formatPatientAge("2025-07-06", NOW)).toBe("1 year");
    expect(formatPatientAge("1996-07-06", NOW)).toBe("30 years");
  });
});

describe("formatDateInputValue", () => {
  it("keeps date-only values compatible with date inputs", () => {
    expect(formatDateInputValue("2026-06-06")).toBe("2026-06-06");
  });

  it("normalizes API date-time values for date inputs", () => {
    expect(formatDateInputValue("2026-06-06T00:00:00.000000Z")).toBe("2026-06-06");
  });

  it("returns an empty string for invalid dates", () => {
    expect(formatDateInputValue("not-a-date")).toBe("");
  });
});
