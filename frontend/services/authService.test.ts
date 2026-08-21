import { afterEach, describe, expect, it, vi } from "vitest";
import { loginUser } from "./authService";

describe("loginUser", () => {
  afterEach(() => vi.restoreAllMocks());

  it("marks an FSS app login as app traffic", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ user: { id: 1 } }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );

    await loginUser("fss@example.test", "secret", "app");

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/auth/login",
      expect.objectContaining({
        body: JSON.stringify({ email: "fss@example.test", password: "secret", platform: "app" }),
      }),
    );
  });
});
