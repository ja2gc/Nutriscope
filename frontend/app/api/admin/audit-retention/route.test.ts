import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { GET, PUT } from "./route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));

const proxyMock = vi.mocked(proxy);

describe("/api/admin/audit-retention proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }));
  });

  test("GET forwards to the Laravel retention endpoint", async () => {
    await GET();

    expect(proxyMock).toHaveBeenCalledWith("/admin/audit-retention");
  });

  test("PUT forwards the explicit boolean body", async () => {
    await PUT(new NextRequest("http://localhost/api/admin/audit-retention", {
      method: "PUT",
      body: JSON.stringify({ enabled: true }),
    }));

    expect(proxyMock).toHaveBeenCalledWith("/admin/audit-retention", {
      method: "PUT",
      body: { enabled: true },
    });
  });
});
