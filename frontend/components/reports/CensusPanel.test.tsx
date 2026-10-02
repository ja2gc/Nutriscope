// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { CensusPanel } from "./CensusPanel";
import { getCensusSummary, type CensusSummary } from "@/services/reportService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/reportService", () => ({ getCensusSummary: vi.fn() }));

const summary: CensusSummary = {
  year: 2026,
  month: null,
  label: "2026",
  status: "live" as const,
  total: 2,
  age_groups: ["30-39"],
  age_sex: { "30-39": { M: 1, F: 1, total: 2 } },
  unknown_sex: 0,
  by_risk: { Low: 1, High: 1 },
  by_primary_diagnosis_category: { Diabetes: 1, Renal: 1 },
  available_years: [2026, 2025],
  available_months: [{ month: 1, label: "January", status: "frozen" }, { month: 6, label: "June", status: "live" }],
};

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

describe("CensusPanel", () => {
  it("shows year and month summaries and prints only selected census content", async () => {
    vi.mocked(getCensusSummary).mockImplementation(async (_prefix, _year, month) => month
      ? { ...summary, month, label: "June 2026", total: 1, age_sex: { "30-39": { M: 0, F: 1, total: 1 } }, by_risk: { High: 1 }, by_primary_diagnosis_category: { Renal: 1 } }
      : summary);
    const print = vi.fn();
    Object.defineProperty(window, "print", { value: print, configurable: true });
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
    expect(container.querySelector("[data-census-screen]")).not.toBeNull();
    const printable = document.body.querySelector(":scope > [data-census-print]");
    expect(printable).not.toBeNull();
    expect(printable?.textContent).toContain("2 cycles");

    const month = container.querySelector<HTMLSelectElement>('select[aria-label="Census month"]');
    expect(month).not.toBeNull();
    await act(async () => {
      month!.value = "6";
      month!.dispatchEvent(new Event("change", { bubbles: true }));
    });
    expect(getCensusSummary).toHaveBeenLastCalledWith("rnd", 2026, 6);
    expect(document.body.querySelector(":scope > [data-census-print]")?.textContent).toContain("June 2026");
    expect(document.body.querySelector(":scope > [data-census-print]")?.textContent).not.toContain("Save PDF");

    const button = Array.from(container.querySelectorAll("button")).find((item) => item.textContent?.includes("Save PDF"));
    await act(async () => button?.click());
    expect(print).toHaveBeenCalledOnce();
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
});
