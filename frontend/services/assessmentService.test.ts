import { afterEach, describe, expect, test, vi } from "vitest";

import { saveAssessment } from "./assessmentService";

describe("saveAssessment", () => {
  afterEach(() => vi.unstubAllGlobals());

  test("sends calculated IBW percentage at the API's two-decimal precision", async () => {
    const fetchMock = vi.fn<(input: RequestInfo | URL, init?: RequestInit) => Promise<Response>>(async () => new Response(JSON.stringify({ data: {} }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }));
    vi.stubGlobal("fetch", fetchMock);

    await saveAssessment("synthetic-ncp", { ibw_percentage: 134.932545 }, false);

    const body = JSON.parse(fetchMock.mock.calls[0][1]?.body as string);
    expect(body.ibw_percentage).toBe(134.93);
  });
});
