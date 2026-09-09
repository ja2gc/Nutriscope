import { afterEach, describe, expect, test, vi } from "vitest";
import { createPlanFromTemplate, generateMealPlan, scaleMealPlanToPrescription } from "./mealPlanService";

describe("generateMealPlan", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  test("throws backend validation message instead of returning a fake meal plan", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () =>
        new Response(
          JSON.stringify({
            message:
              "Complete the nutrition prescription before generating a meal plan. Missing: Intervention goal.",
            errors: { intervention: ["Intervention goal"] },
          }),
          {
            status: 422,
            headers: { "Content-Type": "application/json" },
          },
        ),
      ),
    );

    await expect(
      generateMealPlan("7", { week_start_date: "2026-06-29" }),
    ).rejects.toThrow(
      "Complete the nutrition prescription before generating a meal plan. Missing: Intervention goal.",
    );
  });
});

describe("scaleMealPlanToPrescription", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  test("uses the common plan scaling endpoint and returns diagnostics", async () => {
    const fetchMock = vi.fn(async () =>
      new Response(JSON.stringify({
        data: { id: "plan-uuid", scale_status: "already_scaled", days: [] },
        meta: { scaling: { changed_items: 4, problem_days: ["Tuesday"] } },
      }), { status: 200, headers: { "Content-Type": "application/json" } }),
    );
    vi.stubGlobal("fetch", fetchMock);

    const result = await scaleMealPlanToPrescription("ncp-uuid", "plan-uuid");

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/ncp-uuid/meal-plans/plan-uuid/scale-to-prescription",
      expect.objectContaining({ method: "POST" }),
    );
    expect(result.scaling.problem_days).toEqual(["Tuesday"]);
  });
});

describe("createPlanFromTemplate", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  test("returns the backend compatibility warning with the loaded plan", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(async () =>
        new Response(JSON.stringify({
          data: { id: "plan-uuid", days: [] },
          meta: {
            template_compatibility: {
              goal_matches: false,
              disease_stage_matches: false,
              warning: "Review and scale this template.",
            },
          },
        }), { status: 201, headers: { "Content-Type": "application/json" } }),
      ),
    );

    const result = await createPlanFromTemplate("ncp-uuid", {
      template_id: "template-uuid",
      week_start_date: "2026-09-14",
    });

    expect(result.plan.id).toBe("plan-uuid");
    expect(result.compatibility.warning).toBe("Review and scale this template.");
  });
});
