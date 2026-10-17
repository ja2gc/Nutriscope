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
    const body = { entry_key: "draft", weight: 61, lab_values: { albumin: 3.1 } };
    await POST(new Request("http://localhost/api", { method: "POST", body: JSON.stringify(body) }) as never, {
      params: Promise.resolve({ ncpRecordId: "42" }),
    });

    expect(proxyMock).toHaveBeenCalledWith("/rnd/ncp-records/42/monitorings/ai-review", {
      method: "POST",
      body,
    });
  });
});
