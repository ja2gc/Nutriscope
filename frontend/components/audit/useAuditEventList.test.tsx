// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { listAuditLogs, type AuditLogListMeta } from "@/services/auditLogService";
import type { AuditEventDto } from "@/types/audit";
import { useAuditEventList } from "./useAuditEventList";

vi.mock("@/services/auditLogService", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/services/auditLogService")>()),
  listAuditLogs: vi.fn(),
}));

const listMock = vi.mocked(listAuditLogs);
function auditMeta(total: number): AuditLogListMeta {
  return {
  current_page: 1,
  per_page: 10,
  total,
  last_page: 1,
  filters: {
    modules: [], actions: [], outcomes: [], severities: [],
    module_subfilters: { security_administration: [], nutrition_care: [], food_service_operations: [], reports: [] },
    module_actions: { security_administration: [], nutrition_care: [], food_service_operations: [], reports: [] },
    module_counts: { all: 0, security_administration: 0, nutrition_care: 0, food_service_operations: 0, reports: 0 },
  },
  capabilities: {},
  retention: { enabled: false, source: "config", periods: { security: 365, clinical: 2190, operations: 1095, legacy: 90 } },
  };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => { resolve = res; reject = rej; });
  return { promise, resolve, reject };
}

function auditEvent(summary: string): AuditEventDto {
  return {
    id: summary,
    module: "reports",
    category: "operations",
    domain: "reports",
    record_type: "Report",
    action: "viewed",
    action_label: "Viewed",
    summary,
    severity: "info",
    outcome: "success",
    actor: null,
    subject: null,
    context: null,
    patient: null,
    ncp_reference: null,
    detail_mode: "changes",
    reason: null,
    history: null,
    current_record_url: null,
    occurred_at: "2026-07-12T08:30:00Z",
    details: [],
    changes: [],
  };
}

function Harness({ module }: { module: "security_administration" | "reports" }) {
  const state = useAuditEventList({ module });
  return (
    <div data-loading={state.loading} data-total={state.meta.total}>
      {state.error?.message || state.events[0]?.summary || "empty"}
    </div>
  );
}

describe("useAuditEventList", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    listMock.mockReset();
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("aborts the older request and ignores its late response or error", async () => {
    const older = deferred<{ data: AuditEventDto[]; meta: AuditLogListMeta }>();
    const newer = deferred<{ data: AuditEventDto[]; meta: AuditLogListMeta }>();
    listMock.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);

    act(() => root.render(<Harness module="security_administration" />));
    const firstSignal = listMock.mock.calls[0][1]?.signal;
    act(() => root.render(<Harness module="reports" />));

    expect(firstSignal?.aborted).toBe(true);
    await act(async () => newer.resolve({ data: [auditEvent("newest")], meta: auditMeta(2) }));
    expect(container.textContent).toBe("newest");
    expect(container.firstElementChild?.getAttribute("data-total")).toBe("2");
    expect(container.firstElementChild?.getAttribute("data-loading")).toBe("false");

    await act(async () => older.resolve({ data: [auditEvent("stale")], meta: auditMeta(9) }));
    expect(container.textContent).toBe("newest");
    expect(container.firstElementChild?.getAttribute("data-total")).toBe("2");
  });

  test("a late stale failure cannot replace the current error or loading state", async () => {
    const older = deferred<{ data: AuditEventDto[]; meta: AuditLogListMeta }>();
    const newer = deferred<{ data: AuditEventDto[]; meta: AuditLogListMeta }>();
    listMock.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);

    act(() => root.render(<Harness module="security_administration" />));
    act(() => root.render(<Harness module="reports" />));
    await act(async () => newer.resolve({ data: [auditEvent("current")], meta: auditMeta(1) }));
    await act(async () => older.reject(new Error("stale failure")));

    expect(container.textContent).toBe("current");
    expect(container.firstElementChild?.getAttribute("data-loading")).toBe("false");
  });
});
