import { describe, expect, it, vi } from "vitest";

const proxy = vi.fn(async () => new Response(null, { status: 200 }));

vi.mock("@/lib/laravelProxy", () => ({ proxy }));

describe("intervention plan proxy routes", () => {
  it('proxies clearing menu entries without deleting the intervention', async () => {
    const { POST } = await import('./[ncpRecordId]/meal-plans/[mealPlanId]/clear/route');
    await POST(new Request('http://localhost', { method: 'POST' }) as never, { params: Promise.resolve({ ncpRecordId: 'ncp-uuid', mealPlanId: 'menu-uuid' }) });
    expect(proxy).toHaveBeenCalledWith('/rnd/ncp-records/ncp-uuid/meal-plans/menu-uuid/clear', { method: 'POST' });
  });
  it('proxies latest-plan updates and plan deletion', async () => {
    const { PATCH, DELETE } = await import('./[ncpRecordId]/interventions/[interventionId]/route');
    const context = { params: Promise.resolve({ ncpRecordId: 'ncp-uuid', interventionId: 'plan-uuid' }) };
    await PATCH(new Request('http://localhost', { method: 'PATCH', body: JSON.stringify({ education_notes: 'Updated' }) }) as never, context);
    expect(proxy).toHaveBeenCalledWith('/rnd/ncp-records/ncp-uuid/interventions/plan-uuid', { method: 'PATCH', body: { education_notes: 'Updated' } });
    await DELETE(new Request('http://localhost') as never, context);
    expect(proxy).toHaveBeenCalledWith('/rnd/ncp-records/ncp-uuid/interventions/plan-uuid', { method: 'DELETE' });
  });
  it("proxies the latest complete plan endpoint", async () => {
    const { GET } = await import("./[ncpRecordId]/interventions/latest/route");

    await GET(new Request("http://localhost") as never, {
      params: Promise.resolve({ ncpRecordId: "ncp-uuid" }),
    });

    expect(proxy).toHaveBeenCalledWith("/rnd/ncp-records/ncp-uuid/interventions/latest");
  });

  it("proxies recommendations through the plural plan endpoint", async () => {
    const { GET } = await import("./[ncpRecordId]/interventions/recommendations/route");

    await GET(new Request("http://localhost") as never, {
      params: Promise.resolve({ ncpRecordId: "ncp-uuid" }),
    });

    expect(proxy).toHaveBeenCalledWith("/rnd/ncp-records/ncp-uuid/interventions/recommendations");
  });

  it("proxies saved menu scaling to Laravel", async () => {
    const { POST } = await import("./[ncpRecordId]/meal-plans/[mealPlanId]/scale-to-prescription/route");

    await POST(new Request("http://localhost", { method: "POST" }) as never, {
      params: Promise.resolve({ ncpRecordId: "ncp-uuid", mealPlanId: "menu-uuid" }),
    });

    expect(proxy).toHaveBeenCalledWith(
      "/rnd/ncp-records/ncp-uuid/meal-plans/menu-uuid/scale-to-prescription",
      { method: "POST" },
    );
  });
});
