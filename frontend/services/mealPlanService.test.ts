import { afterEach, describe, expect, test, vi } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { createPlanFromTemplate, generateMealPlan, plannedRecipeIngredients, scaleMealPlanToPrescription } from "./mealPlanService";

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
      generateMealPlan("7", { intervention_plan_id: "plan-uuid", week_start_date: "2026-06-29", exclude_carbs: true }),
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

  test("keeps preview items and surfaces an actionable load error", async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(new Response(JSON.stringify({ data: { id: 'draft-week' }, items: [{ id: 'draft-item', quantity: '2.00' }], meta: { preview: true } }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Correct the legacy serving measure before loading.' }), { status: 422 }));
    vi.stubGlobal('fetch', fetchMock);
    const result = await createPlanFromTemplate('ncp-uuid', { intervention_plan_id: 'plan-uuid', template_id: 'template-uuid', week_start_date: '2026-10-12' });
    expect(result).toEqual(expect.objectContaining({ items: [{ id: 'draft-item', quantity: '2.00' }], preview: true }));
    await expect(createPlanFromTemplate('ncp-uuid', { intervention_plan_id: 'plan-uuid', template_id: 'template-uuid', week_start_date: '2026-10-12' }))
      .rejects.toThrow('Correct the legacy serving measure before loading.');
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
      intervention_plan_id: "intervention-uuid",
      template_id: "template-uuid",
      week_start_date: "2026-09-14",
    });

    expect(result.plan.id).toBe("plan-uuid");
    expect(result.compatibility.warning).toBe("Review and scale this template.");
  });
});

describe("plannedRecipeIngredients", () => {
  test("divides the recipe batch by yield and scales each ingredient by planned portions", () => {
    expect(plannedRecipeIngredients([
      { name: "Rice", quantity: 400, unit: "g" },
      { name: "Chicken", quantity: 200, unit: "g" },
    ], 4, 1.5)).toEqual([
      { name: "Rice", quantity: 150, unit: "g" },
      { name: "Chicken", quantity: 75, unit: "g" },
    ]);
  });

  test("returns unavailable when yield or ingredient unit is invalid", () => {
    expect(plannedRecipeIngredients([{ name: "Rice", quantity: 400, unit: "" }], 4, 1)).toBeNull();
    expect(plannedRecipeIngredients([{ name: "Rice", quantity: 400, unit: "g" }], 0, 1)).toBeNull();
  });
});

describe("meal-plan fluid boundary", () => {
  test("displays fluid with macros while preserving water snapshots and the report estimate boundary", () => {
    const root = process.cwd();
    const section = readFileSync(join(root, "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx"), "utf8");
    const tracker = readFileSync(join(root, "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx"), "utf8");
    const service = readFileSync(join(root, "services/mealPlanService.ts"), "utf8");
    const report = readFileSync(join(root, "../backend/resources/views/reports/patient-menu-plan.blade.php"), "utf8");

    expect(section).toContain("label: 'Fluid'");
    expect(tracker).not.toContain('target.label !== "Fluid"');
    expect(service).toContain("water_g?: number | null");
    expect(report).toContain("Estimated fluid from this menu if followed.");
  });
});
