import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET, POST } from "./route";

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

  test("GET forwards the active draft identity so retries use the same allowance", async () => {
    await GET(new Request("http://localhost/api?draft_id=05510b9d-02a4-40d8-b8f5-e33c3776dfcc") as never, { params: Promise.resolve({ ncpRecordId: "cycle-uuid" }) });
    const options = proxyMock.mock.calls[0][1];
    expect(options?.search?.get("draft_id")).toBe("05510b9d-02a4-40d8-b8f5-e33c3776dfcc");
  });
});
