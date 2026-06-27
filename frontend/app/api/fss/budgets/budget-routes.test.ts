import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { POST as adjustBudget } from "./adjust/route";
import { GET as getLedger } from "./ledger/route";
import { GET as getSummary } from "./summary/route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/fss/budgets fiscal-year proxy routes", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("GET summary proxies to the fiscal-year summary endpoint", async () => {
    await getSummary(new Request("http://localhost/api/fss/budgets/summary?fiscal_year=2026") as never);

    expect(proxyMock).toHaveBeenCalledWith("/fss/budgets/summary", {
      search: new URLSearchParams("fiscal_year=2026"),
    });
  });

  test("GET ledger proxies to the fiscal-year ledger endpoint", async () => {
    await getLedger(new Request("http://localhost/api/fss/budgets/ledger?fiscal_year=2026&type=po") as never);

    expect(proxyMock).toHaveBeenCalledWith("/fss/budgets/ledger", {
      search: new URLSearchParams("fiscal_year=2026&type=po"),
    });
  });

  test("POST adjust proxies manual budget adjustments", async () => {
    const body = { fiscal_year: 2026, type: "manual_addition", amount: 1000, reason: "Correction" };

    await adjustBudget(new Request("http://localhost/api/fss/budgets/adjust", {
      method: "POST",
      body: JSON.stringify(body),
    }) as never);

    expect(proxyMock).toHaveBeenCalledWith("/fss/budgets/adjust", { method: "POST", body });
  });
});
