import { describe, expect, it, vi } from "vitest";

const proxy = vi.fn(async () => new Response(null, { status: 200 }));

vi.mock("@/lib/laravelProxy", () => ({ proxy }));

describe("intervention plan proxy routes", () => {
  it("proxies the latest complete plan endpoint", async () => {
    const { GET } = await import("./[ncpRecordId]/interventions/latest/route");

    await GET(new Request("http://localhost") as never, {
      params: Promise.resolve({ ncpRecordId: "ncp-uuid" }),
    });

    expect(proxy).toHaveBeenCalledWith("/rnd/ncp-records/ncp-uuid/interventions/latest");
  });
});
