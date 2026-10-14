// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, test, vi } from "vitest";
import { ReportsBrowser } from "./ReportsBrowser";
import { listAccomplishmentStaff, listAccomplishmentsToReview, listReports } from "@/services/reportService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/reportService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/reportService")>(),
  listAccomplishmentStaff: vi.fn(),
  listAccomplishmentsToReview: vi.fn(),
  listReports: vi.fn(),
}));

afterEach(() => { document.body.innerHTML = ""; vi.clearAllMocks(); });

test("RND selects an FSS name before viewing that user's approved and pending periods", async () => {
  vi.mocked(listAccomplishmentStaff).mockResolvedValue({
    data: [{ id: "staff-uuid", display_name: "Ada Santos" }],
    meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
  });
  vi.mocked(listReports).mockResolvedValue({ data: [{ id: "legacy-1", title: "June report", review_state: null, created_by: { id: "staff-uuid", name: "Ada Santos" }, report_covered_from: "2026-06-01", report_covered_until: "2026-06-15" } as never], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
  vi.mocked(listAccomplishmentsToReview).mockResolvedValue([]);
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  await act(async () => { root.render(<ReportsBrowser catalog={[{ type: "accomplishment_report", name: "Accomplishment Report", desc: "Periods", icon: () => null, group: "Food Service" }]} apiPrefix="rnd" />); });

  expect(container.textContent).toContain("Ada Santos");
  expect(container.querySelector('input[type="month"]')).toBeNull();
  expect(listReports).not.toHaveBeenCalled();
  const staff = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Ada Santos"));
  await act(async () => { staff!.click(); });
  expect(container.textContent).toContain("Approved Reports");
  expect(container.textContent).toContain("To Review");
  expect(container.querySelector('input[type="month"]')).not.toBeNull();
  expect(listReports).toHaveBeenCalledWith("rnd", 1, { type: "accomplishment_report", fss_user_id: "staff-uuid" });
  expect(container.querySelector("h2")?.textContent).toBe("Ada Santos");
  expect(container.textContent?.match(/Ada Santos/g)).toHaveLength(1);
  expect(container.textContent).toContain("Earlier generated");

  const review = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "To Review");
  await act(async () => { review!.click(); });
  expect(listAccomplishmentsToReview).toHaveBeenCalledWith({ fss_user_id: "staff-uuid" });
  await act(async () => {
    const month = container.querySelector<HTMLInputElement>('input[type="month"]')!;
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(month, "2026-06");
    month.dispatchEvent(new Event("input", { bubbles: true }));
    month.dispatchEvent(new Event("change", { bubbles: true }));
  });
  expect(listAccomplishmentsToReview).toHaveBeenCalledWith({ fss_user_id: "staff-uuid", covered_month: "2026-06" });
  await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Back to Food Service Staff")!.click(); });
  expect(container.querySelector('input[type="month"]')).toBeNull();
  await act(async () => root.unmount());
});
