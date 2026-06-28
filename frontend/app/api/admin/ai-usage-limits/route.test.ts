import { NextRequest, NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET, PUT } from "./route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/admin/ai-usage-limits proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("GET proxies to Laravel admin AI usage limits endpoint", async () => {
    await GET();

    expect(proxyMock).toHaveBeenCalledWith("/admin/ai-usage-limits");
  });

  test("PUT proxies body to Laravel admin AI usage limits endpoint", async () => {
    const body = {
      daily_token_limit: 1000,
      monthly_token_limit: 100000,
      cost_per_1m_tokens_usd: 1.92,
    };

    await PUT(new NextRequest("http://localhost/api/admin/ai-usage-limits", {
      method: "PUT",
      body: JSON.stringify(body),
    }));

    expect(proxyMock).toHaveBeenCalledWith("/admin/ai-usage-limits", {
      method: "PUT",
      body,
    });
  });
});

