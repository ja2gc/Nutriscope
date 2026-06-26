import { afterEach, describe, expect, test, vi } from "vitest";

import { getCostEfficiency } from "./procurementService";

describe("getCostEfficiency", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  test("maps current backend per-head fields to procurement UI fields", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(JSON.stringify({
      data: {
        estimated_per_head: 25.5,
        actual_per_head: 20,
        pending: false,
        estimated_total: 2550,
        estimate_population: 10,
        span_days: 10,
      },
    }), { status: 200 }));

    await expect(getCostEfficiency(12)).resolves.toEqual({
      estimated: 25.5,
      actual: 20,
      pending: false,
      pending_reason: null,
      procurement_cost: 2550,
      served_population: 10,
      service_days_done: 0,
      service_days_expected: 10,
    });
  });
});
