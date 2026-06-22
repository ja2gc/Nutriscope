import { describe, it, expect } from "vitest";
import { planToChartSeries, type PlanIndicator } from "./monitoringPlan";

const indicator: PlanIndicator = {
  key: "albumin",
  label: "Albumin",
  unit: "g/dL",
  category: "lab",
  sources: ["flagged_abnormal"],
  reference: { min: 3.5, max: 5.5 },
  target: null,
  latest_status: "in_progress",
  series: [
    { visit: "Visit 1", value: 2.8, status: "not_met" },
    { visit: "Follow-up 1", value: 3.0, status: "in_progress" },
    { visit: "Follow-up 2", value: null, status: "no_data" },
  ],
};

describe("planToChartSeries", () => {
  it("maps series to {visit, value} points in order", () => {
    expect(planToChartSeries(indicator)).toEqual([
      { visit: "Visit 1", value: 2.8 },
      { visit: "Follow-up 1", value: 3.0 },
      { visit: "Follow-up 2", value: null },
    ]);
  });
});
