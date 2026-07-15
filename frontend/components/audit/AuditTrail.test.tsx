// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { getActivity, type ActivityPage } from "@/services/activityService";
import type { AuditEventDto } from "@/types/audit";
import { AuditTrail } from "./AuditTrail";

vi.mock("@/services/activityService", () => ({ getActivity: vi.fn() }));
const getActivityMock = vi.mocked(getActivity);

function event(overrides: Partial<AuditEventDto> = {}): AuditEventDto {
  return {
    id: "event-1", module: "reports", category: "operations", domain: "reports", record_type: "Report", action: "archived",
    action_label: "Archived", summary: "Archived nutrition report", severity: "notice",
    outcome: "success", actor: { id: "user-1", kind: "user", name: "Maria Santos", role: "RND" },
    subject: { type: "report", id: "report-1", label: "Nutrition report" }, context: null,
    patient: null, ncp_reference: null, detail_mode: "changes", reason: null, history: null, current_record_url: null,
    occurred_at: "2026-07-12T08:30:00Z", details: [], changes: [], ...overrides,
  };
}

function page(data: AuditEventDto[], hasMore = false, cursor: string | null = null): ActivityPage {
  return { data, meta: { has_more: hasMore, next_before_id: cursor } };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}

describe("AuditTrail", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    getActivityMock.mockReset();
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("renders user and system actors with exact semantic Manila dates", async () => {
    getActivityMock.mockResolvedValue(page([
      event(),
      event({ id: "event-2", actor: null, summary: "Automated lifecycle refresh" }),
    ]));
    const user = userEvent.setup();
    await act(async () => root.render(<AuditTrail path="/api/rnd/reports/report-1/activity" title="Report history" />));
    await act(async () => user.click(container.querySelector("button")!));

    expect(container.textContent).toContain("Maria Santos");
    expect(container.textContent).toContain("System");
    expect(container.textContent).toContain("Jul 12, 2026");
    expect(container.textContent).toContain("04:30:00 PM PHT");
    expect(container.querySelector('time[datetime="2026-07-12T08:30:00.000Z"]')).not.toBeNull();
  });

  test("shows field names only for clinical changes", async () => {
    getActivityMock.mockResolvedValue(page([event({
      category: "clinical", domain: "patients",
      changes: [{
        field: "medical_diagnosis", label: "Medical diagnosis", old_value: "PRIVATE-OLD", new_value: "PRIVATE-NEW",
        before: { type: "redacted", value: null }, after: { type: "redacted", value: null }, redacted: true,
      }],
    })]));
    const user = userEvent.setup();
    await act(async () => root.render(<AuditTrail path="/api/rnd/patients/patient-1/activity" />));
    await act(async () => user.click(container.querySelector("button")!));

    expect(container.textContent).toContain("Medical diagnosis");
    expect(container.textContent).toContain("Value hidden; field changed");
    expect(container.textContent).not.toContain("PRIVATE-OLD");
    expect(container.textContent).not.toContain("PRIVATE-NEW");
  });

  test("keeps deletion events readable after their subject no longer exists", async () => {
    getActivityMock.mockResolvedValue(page([event({
      action: "deleted", action_label: "Deleted", summary: "Deleted archived report", subject: null,
    })]));
    const user = userEvent.setup();
    await act(async () => root.render(<AuditTrail path="/api/admin/reports/report-1/activity" />));
    await act(async () => user.click(container.querySelector("button")!));

    expect(container.textContent).toContain("Deleted archived report");
    expect(container.textContent).toContain("Deleted");
  });

  test("appends older cursor pages without discarding newer events", async () => {
    getActivityMock
      .mockResolvedValueOnce(page([event({ id: "newer", summary: "Newer event" })], true, "42"))
      .mockResolvedValueOnce(page([event({ id: "older", summary: "Older event" })]));
    const user = userEvent.setup();
    await act(async () => root.render(<AuditTrail path="/api/fss/purchase-orders/po-1/activity" />));
    await act(async () => user.click(container.querySelector("button")!));
    const loadEarlier = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Load earlier"));
    await act(async () => user.click(loadEarlier!));

    expect(getActivityMock).toHaveBeenNthCalledWith(
      2,
      "/api/fss/purchase-orders/po-1/activity",
      "42",
      { signal: expect.any(AbortSignal) },
    );
    expect(container.textContent).toContain("Newer event");
    expect(container.textContent).toContain("Older event");
  });

  test("aborts an older path request and ignores its late response", async () => {
    const older = deferred<ActivityPage>();
    const newer = deferred<ActivityPage>();
    getActivityMock.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
    const user = userEvent.setup();

    await act(async () => root.render(<AuditTrail path="/api/rnd/reports/older/activity" />));
    await act(async () => user.click(container.querySelector("button")!));
    const firstSignal = getActivityMock.mock.calls[0][2]?.signal;
    await act(async () => root.render(<AuditTrail path="/api/rnd/reports/newer/activity" />));

    expect(firstSignal?.aborted).toBe(true);
    expect(getActivityMock).toHaveBeenCalledTimes(2);
    expect(getActivityMock.mock.calls[1][0]).toBe("/api/rnd/reports/newer/activity");
    await act(async () => newer.resolve(page([event({ id: "newer", summary: "Current event" })])));
    expect(container.textContent).toContain("Current event");
    await act(async () => older.resolve(page([event({ id: "older", summary: "Stale event" })])));
    expect(container.textContent).not.toContain("Stale event");
  });
});
