import { beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import { fetchActiveAppointment } from "./ncpAppointmentService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

describe("ncpAppointmentService", () => {
  beforeEach(() => vi.clearAllMocks());

  test("preserves an intentional null active-visit response", async () => {
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({ data: null }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }));

    await expect(fetchActiveAppointment()).resolves.toBeNull();
  });
});
