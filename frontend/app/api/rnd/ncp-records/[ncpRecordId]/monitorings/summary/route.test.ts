import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET } from "./route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/rnd/ncp-records/[ncpRecordId]/monitorings/summary proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("GET proxies to Laravel monitoring summary endpoint", async () => {
    await GET(new Request("http://localhost/api") as never, {
      params: Promise.resolve({ ncpRecordId: "42" }),
    });

    expect(proxyMock).toHaveBeenCalledWith("/rnd/ncp-records/42/monitorings/summary");
  });
});
