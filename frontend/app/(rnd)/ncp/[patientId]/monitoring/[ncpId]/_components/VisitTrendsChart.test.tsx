import React from "react";
import { readFileSync } from "node:fs";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, it } from "vitest";
import VisitTrendsChart from "./VisitTrendsChart";
import type { MonitoringPlan } from "@/services/monitoringPlan";

const plan: MonitoringPlan = {
  visits: [], pes_statements: [], goal_type: null, nutritional_status: null,
  indicators: [
    { key: "weight", label: "Weight", unit: "kg", category: "anthro", sources: [], reference: null, target: 60, latest_status: "in_progress", series: [{ visit: "Visit 1", value: 90, status: "in_progress" }] },
    { key: "glucose", label: "Glucose", unit: "mg/dL", category: "lab", sources: [], reference: { min: 70, max: 99 }, target: null, latest_status: "in_progress", series: [{ visit: "Visit 1", value: 120, status: "in_progress" }] },
  ],
};

it("shows an empty state rather than an empty chart card for unrecorded intake", () => {
  const noValues: MonitoringPlan = { ...plan, indicators: [{ ...plan.indicators[0], key: "energy_kcal", label: "Energy", category: "intake", series: [{ visit: "Visit 1", value: null, status: "no_data" }] }] };
  const html = renderToStaticMarkup(<VisitTrendsChart plan={noValues} entries={[]} intervention={null} category="intake" />);
  expect(html).toContain("No recorded values in this category yet.");
  expect(html).not.toContain("Intake vs Prescription");
});

it("filters existing categories and labels the recorded target/reference", () => {
  const html = renderToStaticMarkup(<VisitTrendsChart plan={plan} entries={[]} intervention={null} category="lab" />);
  expect(html).toContain("Glucose");
  expect(html).toContain("Reference: 70–99 mg/dL");
  expect(html).not.toContain("Weight");
  const all = renderToStaticMarkup(<VisitTrendsChart plan={plan} entries={[]} intervention={null} />);
  expect(all).toContain("Target: 60 kg");
  const source = readFileSync("app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/VisitTrendsChart.tsx", "utf8");
  expect(source).not.toMatch(/<ReferenceLine(?! ifOverflow="extendDomain")/);
});
