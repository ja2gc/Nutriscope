import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { exportAuditLogs, listAuditLogs } from "./auditLogService";

const fetchMock = vi.fn<typeof fetch>();
const replaceMock = vi.fn();

describe("audit browser unauthorized states", () => {
  beforeEach(() => {
    fetchMock.mockReset();
    replaceMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("window", { location: { replace: replaceMock } });
    fetchMock.mockResolvedValue(new Response(null, {
      status: 401,
      headers: { "Content-Type": "application/json" },
    }));
  });

  afterEach(() => vi.unstubAllGlobals());

  test("list and export retain safe inline 401 handling without replacing the page", async () => {
    await expect(listAuditLogs()).rejects.toMatchObject({ status: 401 });
    await expect(exportAuditLogs()).rejects.toMatchObject({ status: 401 });

    expect(replaceMock).not.toHaveBeenCalled();
  });
});
