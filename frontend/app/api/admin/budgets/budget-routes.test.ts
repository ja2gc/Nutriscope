import { NextResponse } from "next/server";
import { beforeEach, describe, expect, test, vi } from "vitest";

import { proxy } from "@/lib/laravelProxy";
import { GET as listBudgets, POST as createBudget } from "./route";
import { POST as adjustBudget } from "./adjust/route";
import { GET as getLedger } from "./ledger/route";
import { GET as getSummary } from "./summary/route";
import { GET as getActivity } from "./[id]/activity/route";

vi.mock("@/lib/laravelProxy", () => ({
  proxy: vi.fn(),
}));

const proxyMock = vi.mocked(proxy);

describe("/api/admin/budgets proxy routes", () => {
  beforeEach(() => {
    proxyMock.mockReset();
    proxyMock.mockResolvedValue(NextResponse.json({ data: null }, { status: 200 }));
  });

  test("GET list proxies to the admin budget endpoint", async () => {
    await listBudgets();

    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets");
  });

  test("POST creates a fiscal year through the admin endpoint", async () => {
    const body = { fiscal_year: 2027, allocated_amount: 100000 };
    await createBudget(new Request("http://localhost/api/admin/budgets", { method: "POST", body: JSON.stringify(body) }) as never);
    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets", { method: "POST", body });
  });

  test("POST adjustments retain the admin scope and submitted fields", async () => {
    const body = { fiscal_year: 2027, type: "manual_addition", amount: 1000, reason: "Supplemental allocation" };
    await adjustBudget(new Request("http://localhost/api/admin/budgets/adjust", { method: "POST", body: JSON.stringify(body) }) as never);
    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets/adjust", { method: "POST", body });
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

  test("GET activity proxies a read-only budget trail with its cursor", async () => {
    await getActivity(
      new Request("http://localhost/api/admin/budgets/budget-123/activity?before_id=42"),
      { params: Promise.resolve({ id: "budget-123" }) },
    );

    expect(proxyMock).toHaveBeenCalledWith("/admin/budgets/budget-123/activity", {
      search: new URLSearchParams("before_id=42"),
    });
  });
});
