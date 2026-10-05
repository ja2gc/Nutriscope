// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReportsBrowser } from "./ReportsBrowser";
import { archiveReport, listInstances } from "@/services/reportService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/reportService", () => ({
  listInstances: vi.fn(),
  archiveReport: vi.fn(),
}));
vi.mock("@/components/reports/PatientsNcpTab", () => ({ PatientsNcpTab: () => null }));
vi.mock("@/components/reports/CensusPanel", () => ({ CensusPanel: () => null }));
vi.mock("@/components/ReportPreview", () => ({ ReportPreview: () => null }));

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

describe("report archive action", () => {
  it("files the selected non-Census instance with its exact type and params", async () => {
    vi.mocked(listInstances).mockResolvedValue({
      data: {
        axis: "entity",
        instances: [{ key: "po-1", label: "PO-1", params: { purchase_order_id: "po-1" }, date: "2026-09-15" }],
      },
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
    });
    vi.mocked(archiveReport).mockResolvedValue({ id: "filed-1" } as never);
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => {
      root.render(<ReportsBrowser catalog={[{
        type: "procurement_pack", name: "Procurement Pack", desc: "Pack", icon: () => null, group: "Food Service",
      }]} apiPrefix="rnd" />);
    });
    await act(async () => { await Promise.resolve(); });

    const archive = Array.from(container.querySelectorAll("button"))
      .find((button) => button.getAttribute("aria-label") === "Archive PO-1");
    expect(archive).toBeDefined();
    await act(async () => { archive!.click(); });

    expect(archiveReport).toHaveBeenCalledWith("procurement_pack", { purchase_order_id: "po-1" }, "rnd");
    expect(container.textContent).toContain("Archived PO-1");
    await act(async () => root.unmount());
  });
});
