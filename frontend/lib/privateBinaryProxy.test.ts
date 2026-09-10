import { beforeEach, describe, expect, it, vi } from "vitest";
import { cookies } from "next/headers";
import { privateBinaryProxy } from "./privateBinaryProxy";

vi.mock("next/headers", () => ({ cookies: vi.fn() }));

const cookiesMock = vi.mocked(cookies);

describe("privateBinaryProxy", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    cookiesMock.mockResolvedValue({
      get: vi.fn(() => ({ value: "demo-token" })),
    } as never);
  });

  it("returns 401 without an authenticated session", async () => {
    cookiesMock.mockResolvedValue({ get: vi.fn(() => undefined) } as never);
    const fetchMock = vi.spyOn(globalThis, "fetch");

    const response = await privateBinaryProxy("/auth/profile-photo");

    expect(response.status).toBe(401);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("preserves bytes and content type without allowing private caching", async () => {
    const bytes = new Uint8Array([137, 80, 78, 71]);
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(bytes, {
      status: 200,
      headers: { "Content-Type": "image/png" },
    }));

    const response = await privateBinaryProxy("/auth/profile-photo");

    expect(response.status).toBe(200);
    expect(response.headers.get("Content-Type")).toBe("image/png");
    expect(response.headers.get("Cache-Control")).toBe("private, no-store");
    expect(response.headers.get("X-Content-Type-Options")).toBe("nosniff");
    expect(new Uint8Array(await response.arrayBuffer())).toEqual(bytes);
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining("/auth/profile-photo"),
      expect.objectContaining({ headers: { Authorization: "Bearer demo-token" } }),
    );
  });

  it("keeps the upstream error status", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 404 }));

    const response = await privateBinaryProxy("/announcements/missing/author-photo");

    expect(response.status).toBe(404);
  });
});
