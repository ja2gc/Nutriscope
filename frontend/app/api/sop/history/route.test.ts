import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET } from "./route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/sop/history proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: [] }, { status: 200 }));
  });

  test("GET proxies to Laravel SOP history endpoint", async () => {
    await GET(new NextRequest("http://localhost/api/sop/history?page=2"));

    expect(proxyMock).toHaveBeenCalledWith("/sop/history", {
      search: new URLSearchParams("page=2"),
    });
  });
});
