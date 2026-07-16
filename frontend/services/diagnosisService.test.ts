import { beforeEach, describe, expect, test, vi } from "vitest";

import { apiFetch } from "@/lib/apiFetch";
import { fetchDiagnosesPage } from "./diagnosisService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

describe("fetchDiagnosesPage", () => {
  beforeEach(() => {
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({
      data: [{ id: 11, domain: "NI" }],
      meta: { current_page: 2, per_page: 10, total: 11, last_page: 2 },
    }), { status: 200 }));
  });

  test("requests a ten-item page and preserves pagination metadata", async () => {
    const result = await fetchDiagnosesPage(77, 2);

    expect(apiFetch).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/77/diagnoses?page=2&per_page=10",
      expect.any(Object)
    );
    expect(result.meta).toMatchObject({ current_page: 2, per_page: 10, total: 11, last_page: 2 });
    expect(result.data).toHaveLength(1);
  });
});
