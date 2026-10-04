// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { CensusPanel } from "./CensusPanel";
import { censusExportUrl, getCensusSummary, type CensusSummary } from "@/services/reportService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/reportService", () => ({
  getCensusSummary: vi.fn(),
  censusExportUrl: vi.fn(() => "/api/rnd/reports/demographic_census/export?year=2026"),
}));

const summary: CensusSummary = {
  year: 2026,
  month: null,
  label: "2026",
  status: "live" as const,
  total: 2,
  age_groups: ["30-39"],
  age_sex: { "30-39": { M: 1, F: 1, total: 2 } },
  unknown_sex: 0,
  by_risk: { Low: 1, Moderate: 0, High: 1 },
  by_nutritional_status: {
    "Severe Malnutrition": 0, "Moderate Malnutrition": 0,
    "Mild Malnutrition / Underweight": 0, Normal: 0, Overweight: 2,
    "Obese Class I": 0, "Obese Class II": 0, "Obese Class II (Severe)": 0,
    Unspecified: 0,
  },
  by_primary_diagnosis_category: { Diabetes: 1, Renal: 1 },
  available_years: [2026, 2025],
  available_months: [{ month: 1, label: "January", status: "frozen" }, { month: 6, label: "June", status: "live" }],
};

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

describe("CensusPanel", () => {
  it("shows year and month summaries and downloads only the selected census content", async () => {
    vi.mocked(getCensusSummary).mockImplementation(async (_prefix, _year, month) => month
      ? { ...summary, month, label: "June 2026", total: 1, age_sex: { "30-39": { M: 0, F: 1, total: 1 } }, by_risk: { High: 1 }, by_primary_diagnosis_category: { Renal: 1 } }
      : summary);
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => { root.render(<CensusPanel apiPrefix="rnd" />); });

    expect(container.textContent).toContain("2026");
    expect(container.textContent).toContain("2 cycles");
    expect(container.textContent).toContain("Low");
    expect(container.textContent).toContain("By nutrition care category");
    expect(container.textContent).not.toContain("By primary diagnosis category");
    expect(container.textContent).toContain("Diabetes");
    expect(container.textContent).toContain("By nutritional status");
    expect(container.textContent).toContain("Moderate Malnutrition");
    expect(container.textContent).toContain("Mild Malnutrition / Underweight");
    expect(container.textContent).toContain("Obese Class II (Severe)");
    expect(container.querySelector("[data-census-screen]")).not.toBeNull();
    expect(document.body.querySelector(":scope > [data-census-print]")).toBeNull();
    expect(container.querySelector("thead")?.textContent).toContain("Sex30-39Total");

    const month = container.querySelector<HTMLSelectElement>('select[aria-label="Census month"]');
    expect(month).not.toBeNull();
    await act(async () => {
      month!.value = "6";
      month!.dispatchEvent(new Event("change", { bubbles: true }));
    });
    expect(getCensusSummary).toHaveBeenLastCalledWith("rnd", 2026, 6);
    expect(censusExportUrl).toHaveBeenLastCalledWith("rnd", 2026, 6);
    const download = container.querySelector<HTMLAnchorElement>('a[download]');
    expect(download?.textContent).toContain("Download PDF");
    expect(download?.getAttribute("href")).toContain("demographic_census/export");
    await act(async () => root.unmount());
  });

  it("shows three risk categories as compact text without progress bars", async () => {
    vi.mocked(getCensusSummary).mockResolvedValue(summary);
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => { root.render(<CensusPanel apiPrefix="rnd" />); });

    const risk = container.querySelector('section[aria-label="By risk level"]');
    expect(risk?.textContent).toContain("Low");
    expect(risk?.textContent).toContain("Moderate");
    expect(risk?.textContent).toContain("High");
    expect(risk?.querySelector('[role="img"]')).toBeNull();
    await act(async () => root.unmount());
  });

  it("labels cycles missing age or sex without claiming sex is always missing", async () => {
    vi.mocked(getCensusSummary).mockResolvedValue({
      ...summary,
      total: 1,
      age_sex: { "30-39": { M: 0, F: 0, total: 0 } },
      unknown_sex: 1,
    });
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => { root.render(<CensusPanel apiPrefix="rnd" />); });
    expect(container.textContent).toContain("Unclassified age or sex: 1");
    expect(container.textContent).not.toContain("Unspecified sex");
    await act(async () => root.unmount());
  });

  it("hides the prior period and its download while the next period loads", async () => {
    let resolveMonth!: (value: CensusSummary) => void;
    const nextMonth = new Promise<CensusSummary>((resolve) => { resolveMonth = resolve; });
    vi.mocked(getCensusSummary).mockImplementation((_prefix, _year, month) => month ? nextMonth : Promise.resolve(summary));
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => { root.render(<CensusPanel apiPrefix="rnd" />); });
    expect(container.querySelector("[data-census-screen]")).not.toBeNull();

    await act(async () => {
      const month = container.querySelector<HTMLSelectElement>('select[aria-label="Census month"]')!;
      month.value = "6";
      month.dispatchEvent(new Event("change", { bubbles: true }));
    });

    expect(container.querySelector("[data-census-screen]")).toBeNull();
    expect(container.querySelector("a[download]")).toBeNull();
    resolveMonth({ ...summary, month: 6, label: "June 2026", total: 1 });
    await act(async () => { await nextMonth; });
    expect(container.querySelector("[data-census-screen]")?.textContent).toContain("June 2026");
    await act(async () => root.unmount());
  });
});
