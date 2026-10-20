import { describe, expect, it } from "vitest";
import { composeDiagnosisComponent, splitDiagnosisComponent } from "./diagnosisEditor";

describe("explicit diagnosis Other text", () => {
  it("composes only checked options and enabled Other, excluding reasoning", () => {
    expect(composeDiagnosisComponent(["Poor appetite / anorexia"], "Early satiety", true)).toBe("Poor appetite / anorexia; Early satiety");
    expect(composeDiagnosisComponent([], "Unselected detail", false)).toBe("");
  });
  it("preserves unmatched signs without inventing a threshold checkbox", () => {
    expect(splitDiagnosisComponent("4% weight loss over 6 months", ["Unintentional weight loss > 5% in 3 months"])).toEqual({ checks: [], other: "4% weight loss over 6 months" });
  });
});
