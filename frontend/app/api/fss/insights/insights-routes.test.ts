import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET as getBudgetBurn } from "./budget-burn/route";
import { GET as getPerHead } from "./per-head-actual-vs-limit/route";
import { GET as getTimeline } from "./procurement-deduction-timeline/route";
import { GET as getSupplierSpend } from "./spend-by-supplier/route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/fss/insights proxy routes", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test.each([
    [getBudgetBurn, "/fss/insights/budget-burn"],
    [getPerHead, "/fss/insights/per-head-actual-vs-limit"],
    [getTimeline, "/fss/insights/procurement-deduction-timeline"],
    [getSupplierSpend, "/fss/insights/spend-by-supplier"],
  ])("GET proxies %s", async (handler, laravelPath) => {
    await handler(new Request("http://localhost/api/fss/insights?fiscal_year=2026") as never);

    expect(proxyMock).toHaveBeenCalledWith(laravelPath, {
      search: new URLSearchParams("fiscal_year=2026"),
    });
  });
});
