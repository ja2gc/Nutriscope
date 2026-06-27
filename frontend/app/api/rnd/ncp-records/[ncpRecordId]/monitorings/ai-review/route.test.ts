import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { POST } from "./route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/rnd/ncp-records/[ncpRecordId]/monitorings/ai-review proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("POST proxies to Laravel monitoring AI review endpoint", async () => {
    await POST(new Request("http://localhost/api", { method: "POST" }) as never, {
      params: Promise.resolve({ ncpRecordId: "42" }),
    });

    expect(proxyMock).toHaveBeenCalledWith("/rnd/ncp-records/42/monitorings/ai-review", {
      method: "POST",
    });
  });
});
