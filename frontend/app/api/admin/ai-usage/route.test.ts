import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";
import { proxy } from "@/lib/laravelProxy";
import { GET } from "./route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));
const proxyMock = vi.mocked(proxy);

describe("/api/admin/ai-usage proxy route", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ points: [] }));
  });

  test("forwards period query parameters to Laravel", async () => {
    await GET(new Request("http://localhost/api/admin/ai-usage?view=month&year=2026&month=7"));

    expect(proxyMock).toHaveBeenCalledWith("/admin/ai-usage", {
      search: new URLSearchParams("view=month&year=2026&month=7"),
    });
  });
});
