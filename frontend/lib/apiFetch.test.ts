import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "./apiFetch";

const fetchMock = vi.fn<typeof fetch>();
const replaceMock = vi.fn();

describe("apiFetch unauthorized redirect policy", () => {
  beforeEach(() => {
    fetchMock.mockReset();
    replaceMock.mockReset();
    vi.stubGlobal("fetch", fetchMock);
    vi.stubGlobal("window", { location: { replace: replaceMock } });
    fetchMock.mockResolvedValue(new Response(null, { status: 401 }));
  });

  afterEach(() => vi.unstubAllGlobals());

  test("preserves the shared browser redirect by default", async () => {
    await apiFetch("/api/default");
    expect(replaceMock).toHaveBeenCalledWith("/login");
  });

  test("allows an explicit caller to retain its inline unauthorized state", async () => {
    const configurableApiFetch = apiFetch as unknown as (
      input: RequestInfo | URL,
      init?: RequestInit,
      options?: { redirectOnUnauthorized?: boolean },
    ) => Promise<Response>;

    const response = await configurableApiFetch(
      "/api/admin/audit-logs",
      undefined,
      { redirectOnUnauthorized: false },
    );

    expect(response.status).toBe(401);
    expect(replaceMock).not.toHaveBeenCalled();
  });
});
