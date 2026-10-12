// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test } from "vitest";
import type { AuditFilterMetadata } from "@/types/audit";
import { AuditFilters } from "./AuditFilters";

const metadata = {
  modules: [],
  actions: [],
  module_counts: {},
  report_contexts: {},
} as unknown as AuditFilterMetadata;

describe("AuditFilters", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("uses the shared month and year filter instead of a date range", () => {
    act(() => root.render(<AuditFilters metadata={metadata} value={{}} onChange={() => undefined} onClear={() => undefined} />));

    expect(container.querySelector('select[aria-label="Audit month"]')).not.toBeNull();
    expect(container.querySelector('select[aria-label="Audit year"]')).not.toBeNull();
    expect(container.textContent).not.toContain("Start date");
    expect(container.textContent).not.toContain("End date");
    expect(container.textContent).not.toMatch(/Context|Outcome|Severity/);
  });
  test("limits report type options to the selected module", () => {
    const scopedMetadata = {
      ...metadata,
      report_contexts: {
        nutrition_care: [
          { value: "demographic_census", label: "Demographic Census" },
          { value: "ncp_summary", label: "NCP Summary" },
        ],
        food_service_operations: [
          { value: "accomplishment_report", label: "Accomplishment Report" },
          { value: "program_project_activity", label: "PPA" },
          { value: "procurement_pack", label: "Procurement Pack" },
          { value: "menu_calendar", label: "Menu Calendar" },
        ],
      },
    } as AuditFilterMetadata;
    const reportTypeSelect = () => Array.from(container.querySelectorAll("label"))
      .find((label) => label.textContent?.includes("Report type"))
      ?.querySelector("select");

    act(() => root.render(<AuditFilters metadata={scopedMetadata} value={{ module: "nutrition_care" }} onChange={() => undefined} onClear={() => undefined} />));
    expect(Array.from(reportTypeSelect()?.options ?? []).map((option) => option.value)).toEqual(["", "demographic_census", "ncp_summary"]);

    act(() => root.render(<AuditFilters metadata={scopedMetadata} value={{ module: "food_service_operations" }} onChange={() => undefined} onClear={() => undefined} />));
    expect(Array.from(reportTypeSelect()?.options ?? []).map((option) => option.value)).toEqual(["", "accomplishment_report", "program_project_activity", "procurement_pack", "menu_calendar"]);
  });
});
