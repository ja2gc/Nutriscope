import { afterEach, describe, expect, test, vi } from "vitest";
import { generateMealPlan } from "./mealPlanService";

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
