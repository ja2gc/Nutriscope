import { beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import { exportAuditLogs, listAuditLogs } from "./auditLogService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

const apiFetchMock = vi.mocked(apiFetch);

describe("audit log service behavior", () => {
  beforeEach(() => apiFetchMock.mockReset());

  test("passes the caller AbortSignal to list requests", async () => {
    const controller = new AbortController();
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: [], meta: {} }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }));

    await listAuditLogs({}, { signal: controller.signal });

    expect(apiFetchMock).toHaveBeenCalledWith(
      "/api/admin/audit-logs?",
      expect.objectContaining({ signal: controller.signal }),
      { redirectOnUnauthorized: false },
    );
  });

  test("accepts only a successful CSV export body", async () => {
    const csv = new Blob(["event_id\nevt_1\n"], { type: "text/csv" });
    apiFetchMock.mockResolvedValue(new Response(csv, {
      status: 200,
      headers: { "Content-Type": "text/csv; charset=UTF-8" },
    }));

    const result = await exportAuditLogs({ category: "security" });

    expect(result.type).toMatch(/^text\/csv/);
    expect(apiFetchMock).toHaveBeenCalledWith(
      "/api/admin/audit-logs/export?category=security",
      expect.objectContaining({ headers: { Accept: "text/csv" } }),
      { redirectOnUnauthorized: false },
    );
  });

  test.each([
    [403, "application/json", 403],
    [200, "text/html", 502],
    [200, "text/csv-evil", 502],
  ])("rejects status %s with content type %s safely", async (status, contentType, expectedStatus) => {
    apiFetchMock.mockResolvedValue(new Response("RAW-SENSITIVE-BODY", {
      status,
      headers: { "Content-Type": contentType },
    }));

    await expect(exportAuditLogs()).rejects.toMatchObject({
      message: "Audit export unavailable.",
      status: expectedStatus,
    });
  });
});
