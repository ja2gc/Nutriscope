import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET, POST } from "./route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/sop proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("GET proxies to Laravel current SOP endpoint", async () => {
    await GET();

    expect(proxyMock).toHaveBeenCalledWith("/sop");
  });

  test("POST proxies SOP payload to Laravel", async () => {
    const request = new Request("http://localhost/api/sop", {
      method: "POST",
      body: JSON.stringify({ title: "Kitchen SOP", body: "Wash hands." }),
    });

    await POST(request);

    expect(proxyMock).toHaveBeenCalledWith("/sop", {
      method: "POST",
      body: { title: "Kitchen SOP", body: "Wash hands." },
    });
  });
});
