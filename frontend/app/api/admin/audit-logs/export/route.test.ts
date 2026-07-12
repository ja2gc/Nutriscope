import { NextRequest } from "next/server";
import { cookies } from "next/headers";
import { beforeEach, describe, expect, test, vi } from "vitest";
import { GET } from "./route";

vi.mock("next/headers", () => ({ cookies: vi.fn() }));

const cookiesMock = vi.mocked(cookies);
const fetchMock = vi.fn<typeof fetch>();

describe("audit export proxy", () => {
  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    cookiesMock.mockResolvedValue({
      get: vi.fn(() => ({ value: "session-token" })),
    } as never);
  });

  test("streams CSV bytes and preserves status and download headers", async () => {
    const bytes = new TextEncoder().encode("event_id,action\nevt_1,created\n");
    fetchMock.mockResolvedValue(new Response(bytes, {
      status: 206,
      headers: {
        "Content-Type": "text/csv; charset=UTF-8",
        "Content-Disposition": 'attachment; filename="../../unsafe.csv"',
      },
    }));

    const response = await GET(new NextRequest(
      "http://localhost/api/admin/audit-logs/export?category=security&outcome=blocked",
    ));

    expect(fetchMock).toHaveBeenCalledWith(
      "http://127.0.0.1:8000/api/admin/audit-logs/export?category=security&outcome=blocked",
      expect.objectContaining({
        method: "GET",
        headers: expect.objectContaining({
          Authorization: "Bearer session-token",
          Accept: "text/csv",
        }),
      }),
    );
    expect(response.status).toBe(206);
    expect(response.headers.get("Content-Type")).toBe("text/csv; charset=UTF-8");
    expect(response.headers.get("Content-Disposition")).toBe('attachment; filename="nutriscope-audit-events.csv"');
    expect(response.headers.get("Cache-Control")).toBe("private, no-store, max-age=0");
    expect(new Uint8Array(await response.arrayBuffer())).toEqual(bytes);
  });

  test.each([401, 403, 422, 500])("returns a cache-disabled generic error for upstream %s", async (status) => {
    fetchMock.mockResolvedValue(new Response("SENSITIVE-UPSTREAM-BODY", {
      status,
      headers: { "Content-Type": "application/json" },
    }));

    const response = await GET(new NextRequest("http://localhost/api/admin/audit-logs/export"));

    expect(response.status).toBe(status);
    expect(response.headers.get("Content-Type")).toContain("application/json");
    expect(response.headers.get("Cache-Control")).toBe("private, no-store, max-age=0");
    expect(await response.text()).toBe('{"message":"Audit export unavailable."}');
  });

  test("rejects a successful upstream response that is not CSV", async () => {
    fetchMock.mockResolvedValue(new Response("<html>sensitive</html>", {
      status: 200,
      headers: { "Content-Type": "text/html" },
    }));

    const response = await GET(new NextRequest("http://localhost/api/admin/audit-logs/export"));

    expect(response.status).toBe(502);
    expect(response.headers.get("Cache-Control")).toBe("private, no-store, max-age=0");
    expect(await response.text()).toBe('{"message":"Audit export unavailable."}');
  });

  test("rejects a CSV prefix lookalike media type", async () => {
    fetchMock.mockResolvedValue(new Response("not,csv", {
      status: 200,
      headers: { "Content-Type": " text/csv-evil ; charset=UTF-8" },
    }));

    const response = await GET(new NextRequest("http://localhost/api/admin/audit-logs/export"));

    expect(response.status).toBe(502);
    expect(response.headers.get("Cache-Control")).toBe("private, no-store, max-age=0");
    expect(await response.text()).toBe('{"message":"Audit export unavailable."}');
  });

  test("converts upstream network failures to a private generic 502", async () => {
    fetchMock.mockRejectedValue(new Error("PRIVATE-UPSTREAM-NETWORK-DETAIL"));

    const response = await GET(new NextRequest("http://localhost/api/admin/audit-logs/export"));

    expect(response.status).toBe(502);
    expect(response.headers.get("Cache-Control")).toBe("private, no-store, max-age=0");
    expect(await response.text()).toBe('{"message":"Audit export unavailable."}');
  });

  test("returns 401 without contacting Laravel when the session cookie is absent", async () => {
    cookiesMock.mockResolvedValue({ get: vi.fn(() => undefined) } as never);

    const response = await GET(new NextRequest("http://localhost/api/admin/audit-logs/export"));

    expect(response.status).toBe(401);
    expect(response.headers.get("Cache-Control")).toBe("private, no-store, max-age=0");
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
