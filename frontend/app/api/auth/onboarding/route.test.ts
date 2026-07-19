import { beforeEach, describe, expect, test, vi } from "vitest";
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";
import { POST as complete } from "./route";
import { POST as skip } from "./skip/route";

vi.mock("@/lib/laravelProxy", () => ({ proxy: vi.fn() }));
const proxyMock = vi.mocked(proxy);

describe("onboarding proxy routes", () => {
  beforeEach(() => proxyMock.mockReset());

  test("forwards account setup fields", async () => {
    const body = { password: "private-password", password_confirmation: "private-password", recovery_email: "recovery@example.com" };
    await complete(new NextRequest("http://localhost/api/auth/onboarding", {
      method: "POST",
      body: JSON.stringify(body),
      headers: { "Content-Type": "application/json" },
    }));
    expect(proxyMock).toHaveBeenCalledWith("/auth/onboarding", { method: "POST", body });
  });

  test("forwards do-later choice", async () => {
    await skip();
    expect(proxyMock).toHaveBeenCalledWith("/auth/onboarding/skip", { method: "POST" });
  });
});
