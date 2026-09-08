import { beforeEach, describe, expect, it, vi } from "vitest";
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";
import { GET as dashboard } from "./dashboard/route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));
const proxyMock = vi.mocked(proxy);

describe("NCP appointment proxy routes", () => {
  beforeEach(() => proxyMock.mockReset());

  it("forwards the dashboard queue and its pagination query", async () => {
    const request = new NextRequest("http://localhost/api/rnd/ncp-appointments/dashboard?page=2&per_page=3");
    await dashboard(request);

    expect(proxyMock).toHaveBeenCalledWith("/rnd/ncp-appointments/dashboard", { search: request.nextUrl.searchParams });
  });
});
