import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";
import { GET } from "./route";
import { proxy } from "@/lib/laravelProxy";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));

const proxyMock = vi.mocked(proxy);

describe("admin audit actor proxy", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: [] }));
  });

  test("forwards pagination, name search, and selected actor resolution", async () => {
    const search = new URLSearchParams({
      page: "2",
      per_page: "10",
      search: "Maria Santos",
      selected_id: "00000000-0000-4000-8000-000000000001",
    });

    await GET(new NextRequest(`http://localhost/api/admin/audit-actors?${search}`));

    expect(proxyMock).toHaveBeenCalledWith("/admin/audit-actors", { search });
  });
});
