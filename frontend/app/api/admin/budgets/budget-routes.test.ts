import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET as listBudgets } from "./route";
import { GET as getLedger } from "./ledger/route";
import { GET as getSummary } from "./summary/route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/admin/budgets read-only proxy routes", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("GET list proxies to the admin budget endpoint", async () => {
    await listBudgets();

    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets");
  });

  test("GET summary proxies to the admin fiscal-year summary endpoint", async () => {
    await getSummary(new Request("http://localhost/api/admin/budgets/summary?fiscal_year=2026") as never);

    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets/summary", {
      search: new URLSearchParams("fiscal_year=2026"),
    });
  });

  test("GET ledger proxies to the admin fiscal-year ledger endpoint", async () => {
    await getLedger(new Request("http://localhost/api/admin/budgets/ledger?fiscal_year=2026&source=manual") as never);

    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets/ledger", {
      search: new URLSearchParams("fiscal_year=2026&source=manual"),
    });
  });
});
