import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { GET } from "./route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));

const proxyMock = vi.mocked(proxy);

describe("admin audit history proxy", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: {} }));
  });

  test("rejects non-UUID event references without reaching Laravel", async () => {
    const response = await GET(
      new NextRequest("http://localhost/api/admin/audit-logs/not-an-id/history"),
      { params: Promise.resolve({ id: "not-an-id" }) },
    );

    expect(response.status).toBe(404);
    expect(response.headers.get("Cache-Control")).toContain("no-store");
    expect(proxyMock).not.toHaveBeenCalled();
  });

  test("forwards only the validated public event UUID and disables caching", async () => {
    const id = "70e4f184-95da-43bd-b017-8d48f803fb94";
    const response = await GET(
      new NextRequest(`http://localhost/api/admin/audit-logs/${id}/history`),
      { params: Promise.resolve({ id }) },
    );

    expect(proxyMock).toHaveBeenCalledWith(`/admin/audit-logs/${encodeURIComponent(id)}/history`);
    expect(response.headers.get("Cache-Control")).toContain("private");
    expect(response.headers.get("Cache-Control")).toContain("no-store");
  });
});
