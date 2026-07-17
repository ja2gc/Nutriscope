import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import PrescriptionCalculationPanel from "./PrescriptionCalculationPanel";
import type { CalculationTrace } from "@/lib/prescriptionCalculationTrace";

const trace: CalculationTrace = {
  context: [
    { label: "Goal", value: "Diabetic Control" },
    { label: "IBW", value: "68 kg", formulaName: "Hamwi" },
  ],
  targets: [
    {
      key: "energy_kcal",
      label: "Energy",
      unit: "kcal",
      value: { value: 1400, unit: "kcal", text: "1400 kcal" },
      formula: "max((BMR × PAL) − calorie deficit, caloric floor)",
      calculation: "2001 - 500 = 1501 kcal",
      status: "modified",
    },
    {
      key: "sodium",
      label: "Sodium",
      unit: "mg",
      value: { value: 2000, unit: "mg", text: "<= 2000 mg" },
      formula: "Goal-specific recommended target",
      calculation: "Goal-specific nutrient limit.",
      status: "matches",
    },
  ],
  notes: [],
};

describe("PrescriptionCalculationPanel", () => {
  test("renders hidden-by-default controls when collapsed", () => {
    const html = renderToStaticMarkup(
      <PrescriptionCalculationPanel trace={trace} expanded={false} onToggle={() => {}} />,
    );

    expect(html).toContain("Show calculations");
    expect(html).toContain('aria-expanded="false"');
    expect(html).not.toContain("Patient &amp; Assessment Context");
  });

  test("renders condensed context and one value per nutrient when expanded", () => {
    const html = renderToStaticMarkup(
      <PrescriptionCalculationPanel trace={trace} expanded={true} onToggle={() => {}} />,
    );

    expect(html).toContain("Hide calculations");
    expect(html).toContain('aria-expanded="true"');
    expect(html).toContain("Patient &amp; Assessment Context");
    expect(html).toContain("Nutrition Prescription");
    expect(html).toContain("1400 kcal");
    expect(html).toContain("BMR × PAL");
    expect(html).not.toContain("Prescribed</p>");
    expect(html).not.toContain("Calculated</p>");
    expect(html).not.toContain("Flagged");
  });
});
