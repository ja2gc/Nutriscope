// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import PatientNcpReportsPage from "./page";
import { listPatientNcpReports, prepareReport } from "@/services/reportService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("next/navigation", () => ({ useParams: () => ({ patientId: "patient-1" }) }));
vi.mock("@/services/reportService", () => ({
  listPatientNcpReports: vi.fn(),
  prepareReport: vi.fn(),
  reportViewUrl: (id: string) => `/reports/${id}/view`,
  reportDownloadUrl: (id: string) => `/reports/${id}/download`,
}));
vi.mock("@/components/ReportPreview", () => ({ ReportPreview: () => null }));

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

describe("patient clinical reports", () => {
  it("shows cycle status, but no report status or archive action", async () => {
    vi.mocked(listPatientNcpReports).mockResolvedValue({
      patient: { id: "patient-1", display_name: "Fictional Patient", hospital_number: null, status: "Active" },
      data: [{
        id: "cycle-1", label: "ADIME Cycle", status: "Active", date: "2026-10-02",
        reports: [{
          key: "plan-1", label: "Nutrition Intervention Plan — Oct 5, 2026",
          type: "patient_menu_plan", status: "active", date: "2026-10-05",
          params: { ncp_record_id: "cycle-1", intervention_plan_id: "plan-1" },
          intervention_plan_id: "plan-1", intervention_plan_date: "2026-10-05",
          meal_plan_id: "menu-1", available: true, unavailable_reason: null,
        }],
      }],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });
    vi.mocked(prepareReport).mockResolvedValue({ id: "prepared-1" } as never);
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => { root.render(<PatientNcpReportsPage />); });
    await act(async () => { await Promise.resolve(); });
    await act(async () => {
      Array.from(container.querySelectorAll("button"))
        .find((button) => button.textContent?.includes("ADIME Cycle"))!.click();
    });

    expect(container.textContent).not.toContain("active");
    expect(container.querySelector('[aria-label="Archive Nutrition Intervention Plan — Oct 5, 2026"]')).toBeNull();
    await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Nutrition Intervention Plan — Oct 5, 2026"))!.click(); });
    expect(prepareReport).toHaveBeenCalledWith("patient_menu_plan", {
      ncp_record_id: "cycle-1", intervention_plan_id: "plan-1",
    }, "rnd");
    await act(async () => root.unmount());
  });
});
