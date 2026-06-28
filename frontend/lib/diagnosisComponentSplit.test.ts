import { describe, it, expect } from "vitest";
import { splitStoredComponent } from "./diagnosisComponentSplit";

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
});
