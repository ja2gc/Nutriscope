import { describe, expect, it } from "vitest";
import { serviceDateForDay, validPopulation } from "./fssMealPrep";

describe("FSS meal-prep inputs", () => {
  it("maps a cycle weekday to its service date", () => {
    expect(serviceDateForDay("2026-08-10", "Wednesday")).toBe("2026-08-12");
  });

  it("accepts only positive whole-number populations", () => {
    expect(validPopulation("25")).toBe(25);
    expect(validPopulation("0")).toBeNull();
    expect(validPopulation("2.5")).toBeNull();
  });
});
