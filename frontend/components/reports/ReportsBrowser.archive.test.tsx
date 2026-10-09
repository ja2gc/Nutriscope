// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReportsBrowser } from "./ReportsBrowser";
import { archiveReport, getReportArchiveSettings, listInstances, listReports, prepareReport, setReportArchiveSettings, unarchiveReport } from "@/services/reportService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/reportService", () => ({
  listInstances: vi.fn(),
  archiveReport: vi.fn(),
  prepareReport: vi.fn(),
  listReports: vi.fn(),
  unarchiveReport: vi.fn(),
  getReportArchiveSettings: vi.fn(),
  setReportArchiveSettings: vi.fn(),
  reportViewUrl: (id: string) => `/reports/${id}/view`,
  reportDownloadUrl: (id: string) => `/reports/${id}/download`,
}));
vi.mock("@/components/reports/PatientsNcpTab", () => ({ PatientsNcpTab: () => null }));
vi.mock("@/components/reports/CensusPanel", () => ({ CensusPanel: () => null }));
vi.mock("@/components/ReportPreview", () => ({ ReportPreview: ({ onArchive }: { onArchive?: () => void }) => (
  <button onClick={onArchive}>Archive from preview</button>
) }));

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

describe("report archive action", () => {
  it("filters normal Browse by the selected report month", async () => {
    vi.mocked(listInstances).mockResolvedValue({
      data: { axis: "entity", instances: [] },
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
    });
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    await act(async () => { root.render(<ReportsBrowser catalog={[{
      type: "procurement_pack", name: "Procurement Pack", desc: "Pack", icon: () => null, group: "Food Service",
    }]} apiPrefix="rnd" />); });

    const month = container.querySelector<HTMLSelectElement>('select[aria-label="Report month"]');
    expect(month).not.toBeNull();
    expect(container.querySelector<HTMLSelectElement>('select[aria-label="Report year"]')).not.toBeNull();
    expect(container.querySelectorAll('input[type="month"]')).toHaveLength(0);
    await act(async () => {
      const year = container.querySelector<HTMLSelectElement>('select[aria-label="Report year"]')!;
      year.value = "2026";
      year.dispatchEvent(new Event("change", { bubbles: true }));
      month!.value = "6";
      month!.dispatchEvent(new Event("change", { bubbles: true }));
    });
    expect(listInstances).toHaveBeenCalledWith("procurement_pack", expect.objectContaining({
      covered_month: "2026-06",
    }), "rnd");
    await act(async () => root.unmount());
  });

  it("archives prepared report from preview and removes it from Browse", async () => {
    vi.mocked(listInstances).mockResolvedValue({
      data: {
        axis: "entity",
        instances: [{ key: "po-1", label: "PO-1", params: { purchase_order_id: "po-1" }, date: "2026-09-15" }],
      },
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });
    vi.mocked(prepareReport).mockResolvedValue({ id: "prepared-1" } as never);
    vi.mocked(archiveReport).mockResolvedValue({ id: "prepared-1" } as never);
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => {
      root.render(<ReportsBrowser catalog={[{
        type: "procurement_pack", name: "Procurement Pack", desc: "Pack", icon: () => null, group: "Food Service",
      }]} apiPrefix="rnd" />);
    });
    await act(async () => { await Promise.resolve(); });

    const view = Array.from(container.querySelectorAll("button"))
      .find((button) => button.textContent?.includes("PO-1"));
    await act(async () => { view!.click(); });
    const archive = Array.from(container.querySelectorAll("button"))
      .find((button) => button.textContent === "Archive from preview");
    expect(archive).toBeDefined();
    await act(async () => { archive!.click(); });

    expect(archiveReport).toHaveBeenCalledWith("prepared-1", "rnd");
    expect(container.textContent).toContain("Archived PO-1");
    await act(async () => root.unmount());
  });

  it("filters archived reports by type and restores a selected report", async () => {
    vi.mocked(getReportArchiveSettings).mockResolvedValue({ enabled: false, years: 5 });
    vi.mocked(listReports).mockResolvedValue({
      data: [{ id: "archived-1", title: "PO — May 10, 2026", type: "procurement_pack", status: "archived", file_path: "prepared", report_covered_until: "2026-05-10", created_by: { id: "staff-1", name: "Staff One" } } as never],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });
    vi.mocked(unarchiveReport).mockResolvedValue({ id: "archived-1" } as never);
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => { root.render(<ReportsBrowser catalog={[{
      type: "procurement_pack", name: "Procurement Pack", desc: "Pack", icon: () => null, group: "Food Service",
    }]} apiPrefix="rnd" />); });
    await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Archived")!.click(); });
    await act(async () => { await Promise.resolve(); });

    expect(listReports).toHaveBeenCalledWith("rnd", 1, expect.objectContaining({ status: "archived", type: "procurement_pack" }));
    const month = container.querySelector<HTMLSelectElement>('select[aria-label="Report month"]');
    expect(container.querySelector<HTMLSelectElement>('select[aria-label="Report year"]')).not.toBeNull();
    expect(container.querySelectorAll('input[type="month"]')).toHaveLength(0);
    await act(async () => {
      const year = container.querySelector<HTMLSelectElement>('select[aria-label="Report year"]')!;
      year.value = "2026";
      year.dispatchEvent(new Event("change", { bubbles: true }));
      month!.value = "6";
      month!.dispatchEvent(new Event("change", { bubbles: true }));
    });
    expect(listReports).toHaveBeenCalledWith("rnd", 1, expect.objectContaining({
      status: "archived", type: "procurement_pack", covered_month: "2026-06",
    }));
    expect(container.textContent).not.toContain("Status");
    expect(container.textContent).toContain("Covers through");
    expect(container.querySelector('[aria-label="Delete PO — May 10, 2026"]')).toBeNull();
    const unarchive = container.querySelector<HTMLButtonElement>('[aria-label="Unarchive PO — May 10, 2026"]');
    expect(unarchive).not.toBeNull();
    await act(async () => { unarchive!.click(); });
    expect(unarchiveReport).toHaveBeenCalledWith("archived-1", "rnd");
    await act(async () => root.unmount());
  });

  it("shows retention dates and helper only when Admin enables retention", async () => {
    vi.mocked(getReportArchiveSettings).mockResolvedValue({ enabled: false, years: 5 });
    vi.mocked(setReportArchiveSettings).mockResolvedValue({ enabled: true, years: 5 });
    vi.mocked(listReports).mockResolvedValue({
      data: [{ id: "archived-1", title: "PO — May 10, 2026", type: "procurement_pack", status: "archived", created_at: "2026-05-10T08:00:00Z", retention_expires_at: "2031-05-10T08:00:00Z" } as never],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    await act(async () => { root.render(<ReportsBrowser catalog={[{
      type: "procurement_pack", name: "Procurement Pack", desc: "Pack", icon: () => null, group: "Food Service",
    }]} apiPrefix="admin" />); });
    await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Archived")!.click(); });
    await act(async () => { await Promise.resolve(); });
    expect(container.textContent).not.toContain("Expires on");
    const toggle = container.querySelector<HTMLInputElement>('[aria-label="Enable five-year report retention"]');
    expect(toggle).not.toBeNull();
    await act(async () => { toggle!.click(); });
    expect(setReportArchiveSettings).toHaveBeenCalledWith(true);
    expect(container.textContent).toContain("Expires on");
    await act(async () => root.unmount());
  });

  it("shows enabled retention to RND without allowing settings changes", async () => {
    vi.mocked(getReportArchiveSettings).mockResolvedValue({ enabled: true, years: 5 });
    vi.mocked(listReports).mockResolvedValue({
      data: [{ id: "archived-1", title: "PO — May 10, 2026", type: "procurement_pack", status: "archived", created_at: "2026-05-10T08:00:00Z", retention_expires_at: "2031-05-10T08:00:00Z" } as never],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);
    await act(async () => { root.render(<ReportsBrowser catalog={[{
      type: "procurement_pack", name: "Procurement Pack", desc: "Pack", icon: () => null, group: "Food Service",
    }]} apiPrefix="rnd" />); });
    await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Archived")!.click(); });
    await act(async () => { await Promise.resolve(); });

    expect(getReportArchiveSettings).toHaveBeenCalledOnce();
    expect(container.textContent).toContain("Expires on");
    expect(container.querySelector('[aria-label="Enable five-year report retention"]')).toBeNull();
    await act(async () => root.unmount());
  });
});
