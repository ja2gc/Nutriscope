import { describe, it, expect } from "vitest";
import { matchStoredOption, splitStoredComponent } from "./diagnosisComponentSplit";

const ETIOLOGIES = [
  "Poor appetite / anorexia",
  "Nausea or vomiting",
  "Prescribed diet restriction",
];

describe("splitStoredComponent", () => {
  it("re-hydrates checked options from a joined string", () => {
    const stored = "Poor appetite / anorexia; Nausea or vomiting";
    expect(splitStoredComponent(stored, ETIOLOGIES)).toEqual({
      checks: ["Poor appetite / anorexia", "Nausea or vomiting"],
      notes: "",
    });
  });

  it("routes unknown parts to free-text notes", () => {
    const stored = "Poor appetite / anorexia; chemo-related taste change";
    expect(splitStoredComponent(stored, ETIOLOGIES)).toEqual({
      checks: ["Poor appetite / anorexia"],
      notes: "chemo-related taste change",
    });
  });

  it("handles pure free-text with no matching options", () => {
    expect(splitStoredComponent("custom reason only", ETIOLOGIES)).toEqual({
      checks: [],
      notes: "custom reason only",
    });
  });

  it("trims whitespace and ignores empty segments", () => {
    expect(splitStoredComponent(" Nausea or vomiting ;; ", ETIOLOGIES)).toEqual({
      checks: ["Nausea or vomiting"],
      notes: "",
    });
  });

  it("returns empty for an empty string", () => {
    expect(splitStoredComponent("", ETIOLOGIES)).toEqual({ checks: [], notes: "" });
  });

  it("maps common AI wording to checkbox options and keeps detailed text in notes", () => {
    expect(
      splitStoredComponent(
        "Poor appetite and sub-optimal intake consuming less than 50% of estimated energy needs for 7 days",
        ETIOLOGIES,
      ),
    ).toEqual({
      checks: ["Poor appetite / anorexia"],
      notes: "sub-optimal intake consuming less than 50% of estimated energy needs for 7 days",
    });
  });

  it("maps AI weight loss wording to the closest signs checkbox", () => {
    expect(
      splitStoredComponent(
        "Energy intake less than 50% of estimated needs for 7 days; unintentional weight loss of 8% in 1 month; current weight 45 kg at 78% IBW",
        [
          "Estimated intake < 75% of estimated needs",
          "Unintentional weight loss > 5% in 3 months",
          "BMI < 18.5 (underweight)",
        ],
      ),
    ).toEqual({
      checks: [
        "Estimated intake < 75% of estimated needs",
        "Unintentional weight loss > 5% in 3 months",
      ],
      notes: "current weight 45 kg at 78% IBW",
    });
  });

  it("matches AI problem labels to existing problem checklist options", () => {
    expect(
      matchStoredOption("Unintended weight loss", [
        "Unintended Weight Loss",
        "Malnutrition (undernutrition)",
      ]),
    ).toBe("Unintended Weight Loss");
  });
});
