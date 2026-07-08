import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import PrescriptionCalculationPanel from "./PrescriptionCalculationPanel";
import type { CalculationTrace } from "@/lib/prescriptionCalculationTrace";

const trace: CalculationTrace = {
  inputs: [
    { label: "Goal", formula: "Selected intervention", calculation: "Diabetic Control", value: "Diabetic Control" },
  ],
  weights: [
    { label: "IBW", formula: "Hamwi", calculation: "170 cm -> 68 kg", value: "68 kg" },
  ],
  targets: [
    {
      key: "energy_kcal",
      label: "Energy",
      unit: "kcal",
      prescribed: { value: 1400, unit: "kcal", text: "1400 kcal" },
      calculated: { value: 1501, unit: "kcal", text: "1501 kcal" },
      formula: "Energy = max(TEE - 500, sex-specific floor)",
      calculation: "2001 - 500 = 1501 kcal",
      status: "modified",
    },
    {
      key: "potassium",
      label: "Potassium",
      unit: "mg",
      calculated: { text: "Flagged for monitoring", unit: "mg" },
      formula: "Goal-required monitoring flag",
      calculation: "Refeeding requires potassium monitoring.",
      status: "flagged",
    },
    {
      key: "sodium",
      label: "Sodium",
      unit: "mg",
      prescribed: { value: 2000, unit: "mg", text: "<= 2000 mg" },
      calculated: { value: 2000, unit: "mg", text: "<= 2000 mg" },
      formula: "Sodium maximum from goal target",
      calculation: "Goal-specific nutrient limit.",
      status: "matches",
    },
  ],
  notes: ["Refeeding phase: monitor electrolytes."],
};

describe("PrescriptionCalculationPanel", () => {
  test("renders hidden-by-default controls when collapsed", () => {
    const html = renderToStaticMarkup(
      <PrescriptionCalculationPanel trace={trace} expanded={false} onToggle={() => {}} />,
    );

    expect(html).toContain("Show calculations");
    expect(html).toContain('aria-expanded="false"');
    expect(html).not.toContain("Prescribed Targets");
  });

  test("renders formulas, prescribed values, and flagged micros when expanded", () => {
    const html = renderToStaticMarkup(
      <PrescriptionCalculationPanel trace={trace} expanded={true} onToggle={() => {}} />,
    );

    expect(html).toContain("Hide calculations");
    expect(html).toContain('aria-expanded="true"');
    expect(html).toContain("Prescribed Targets");
    expect(html).toContain("1400 kcal");
    expect(html).toContain("TEE - 500");
    expect(html).toContain("Flagged for monitoring");
    expect(html).toContain("Recommended target");
  });
});
