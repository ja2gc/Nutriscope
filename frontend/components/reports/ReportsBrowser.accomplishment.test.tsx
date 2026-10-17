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

test("Admin selects staff and opens approved reports without review tabs", async () => {
  vi.mocked(listAccomplishmentStaff).mockResolvedValue({ data: [{ id: "staff-uuid", display_name: "Ada Santos" }], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
  vi.mocked(listReports).mockResolvedValue({ data: [{ id: "approved", title: "Approved period", report_covered_from: "2026-07-01", report_covered_until: "2026-07-15" }] as never, meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  await act(async () => { root.render(<ReportsBrowser catalog={[{ type: "accomplishment_report", name: "Accomplishment Report", desc: "Periods", icon: () => null, group: "Food Service" }]} apiPrefix="admin" />); });
  expect(listAccomplishmentStaff).toHaveBeenCalledWith(1, "", "admin");
  expect(listReports).not.toHaveBeenCalled();
  await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Ada Santos")!.click(); });
  expect(listReports).toHaveBeenCalledWith("admin", 1, { type: "accomplishment_report", fss_user_id: "staff-uuid" });
  expect(container.querySelector("h2")?.textContent).toBe("Ada Santos");
  expect(container.querySelector('[aria-label="Accomplishment views"]')).toBeNull();
  expect(container.textContent).not.toContain("To Review");
  expect(listAccomplishmentsToReview).not.toHaveBeenCalled();
  expect(container.querySelector('button[aria-label="Open report: Approved period"]')).not.toBeNull();
  await act(async () => root.unmount());
});

test("RND selects an FSS name before viewing that user's approved and pending periods", async () => {
  vi.mocked(listAccomplishmentStaff).mockResolvedValue({
    data: [{ id: "staff-uuid", display_name: "Ada Santos" }],
    meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
  });
  vi.mocked(listReports).mockResolvedValue({ data: [
    { id: "legacy-1", title: "June report", review_state: null, created_by: { id: "staff-uuid", name: "Ada Santos" }, report_covered_from: "2026-06-01", report_covered_until: "2026-06-15" },
    { id: "approved-1", title: "July report", review_state: "approved", created_by: { id: "staff-uuid", name: "Ada Santos" }, report_covered_from: "2026-07-01", report_covered_until: "2026-07-15" },
  ] as never, meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
  vi.mocked(listAccomplishmentsToReview).mockResolvedValue([{ report_id: "pending-1", start: "2026-06-01", end: "2026-06-15", status: "pending", submission_version: 1, submitted_at: "2026-06-16T08:00:00Z", fss_user: { id: "staff-uuid", display_name: "Ada Santos" } }]);
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
  expect(container.querySelector('select[aria-label="Report month"]')).not.toBeNull();
  expect(container.querySelector('select[aria-label="Report year"]')).not.toBeNull();
  expect(container.querySelector('input[type="month"]')).toBeNull();
  expect(listReports).toHaveBeenCalledWith("rnd", 1, { type: "accomplishment_report", fss_user_id: "staff-uuid" });
  expect(container.querySelector("h2")?.textContent).toBe("Ada Santos");
  expect(container.textContent?.match(/Ada Santos/g)).toHaveLength(1);
  const approvedCards = Array.from(container.querySelectorAll<HTMLButtonElement>('button[aria-label^="Open report"]'));
  expect(approvedCards).toHaveLength(2);
  expect(approvedCards.map((card) => card.textContent).join(" ")).not.toMatch(/Earlier generated| · Approved/);
  expect(container.textContent).not.toContain("View report");
  const reportCard = container.querySelector<HTMLButtonElement>('button[aria-label^="Open report"]');
  expect(reportCard).not.toBeNull();
  await act(async () => { reportCard!.click(); });
  expect(document.querySelector('[role="dialog"]')).not.toBeNull();
  await act(async () => { document.querySelector<HTMLButtonElement>('[aria-label="Close preview"]')!.click(); });

  const review = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "To Review");
  await act(async () => { review!.click(); });
  expect(listAccomplishmentsToReview).toHaveBeenCalledWith({ fss_user_id: "staff-uuid" });
  const reviewCard = container.querySelector<HTMLButtonElement>('button[aria-label^="Review submission"]');
  expect(reviewCard).not.toBeNull();
  await act(async () => { reviewCard!.click(); });
  expect(container.querySelector('[aria-label="Review submission"]')).not.toBeNull();
  await act(async () => {
    const year = container.querySelector<HTMLSelectElement>('select[aria-label="Report year"]')!;
    year.value = "2026";
    year.dispatchEvent(new Event("change", { bubbles: true }));
    const month = container.querySelector<HTMLSelectElement>('select[aria-label="Report month"]')!;
    month.value = "6";
    month.dispatchEvent(new Event("change", { bubbles: true }));
  });
  expect(listAccomplishmentsToReview).toHaveBeenCalledWith({ fss_user_id: "staff-uuid", covered_month: "2026-06" });
  await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Back to Food Service Staff")!.click(); });
  expect(container.querySelector('input[type="month"]')).toBeNull();
  await act(async () => root.unmount());
});
