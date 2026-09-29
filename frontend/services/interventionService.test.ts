import { afterEach, describe, expect, it, vi } from "vitest";
import * as interventionService from "./interventionService";

describe("dated intervention plan service", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("loads a server-paginated plan page without exposing internal IDs", async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({
      data: [{
        id: "plan-newest",
        plan_date: "2026-09-29T08:00:00Z",
        goal_type: "renal_diet",
        disease_stage: "stage_3",
        source_monitoring_id: "visit-uuid",
        source_monitoring_date: "2026-09-28",
        has_meal_plan: true,
        meal_plan_id: "menu-uuid",
      }],
      meta: { current_page: 2, per_page: 10, total: 14, last_page: 2 },
    }), { status: 200, headers: { "Content-Type": "application/json" } }));
    vi.stubGlobal("fetch", fetchMock);

    const fetchPlans = (interventionService as typeof interventionService & {
      fetchInterventionPlans?: (ncpId: string, page: number) => Promise<unknown>;
    }).fetchInterventionPlans;

    expect(fetchPlans).toBeTypeOf("function");
    if (!fetchPlans) return;
    const result = await fetchPlans("ncp-uuid", 2) as unknown as {
      data: Array<Record<string, unknown>>;
      meta: { current_page: number; total: number };
    };

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/ncp-uuid/interventions?page=2&per_page=10",
      expect.objectContaining({ headers: { Accept: "application/json" } }),
    );
    expect(result.meta).toMatchObject({ current_page: 2, total: 14 });
    expect(result.data[0]).toMatchObject({ id: "plan-newest", meal_plan_id: "menu-uuid" });
    expect(result.data[0]).not.toHaveProperty("ncp_record_id");
  });

  it("creates one complete plan through the plural API", async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({
      data: { id: "saved-plan", goal_type: "custom", has_meal_plan: false },
    }), { status: 201, headers: { "Content-Type": "application/json" } }));
    vi.stubGlobal("fetch", fetchMock);

    const payload = {
      goal_type: "custom",
      disease_stage: null,
      energy_kcal: 1900,
      protein_g: 75,
      carbs_g: 250,
      fat_g: 60,
      fluid_ml: 2000,
      education_notes: "Meal timing",
      counseling_goals: "Follow meal schedule",
      barriers: "Shift work",
      strategies: "Pack meals",
    };

    const result = await interventionService.createIntervention("ncp-uuid", payload);

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/ncp-uuid/interventions",
      expect.objectContaining({ method: "POST", body: JSON.stringify(payload) }),
    );
    expect(result.id).toBe("saved-plan");
  });
});
