// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import MealPlanSection from "./MealPlanSection";
import { searchUsda } from "@/services/foodLibraryService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/mealPlanService", () => ({
  fetchMealPlans: vi.fn(async () => [{
    id: "plan-1", intervention_plan_id: "intervention-1", week_start_date: "2026-10-05",
    generation_type: "manual", scale_status: "available", scaled_at: null, status: "draft",
    days: [{ id: "day-1", day_of_week: "Monday", meal_type: "breakfast", flagged: false }],
  }]),
  fetchMealPlanTemplates: vi.fn(async () => ({ data: [], meta: null })),
  fetchAllMealPlanItems: vi.fn(async () => []),
}));

vi.mock("@/services/foodLibraryService", () => ({
  fetchFoodItems: vi.fn(),
  fetchRecipes: vi.fn(),
  searchUsda: vi.fn(async () => []),
  importUsdaFood: vi.fn(),
  fetchRecipeById: vi.fn(),
}));

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

it("searches USDA from the first letter in the meal picker", async () => {
  const container = document.createElement("div");
  document.body.appendChild(container);
  const root = createRoot(container);
  await act(async () => {
    root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1"
      prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />);
  });
  await act(async () => { await Promise.resolve(); });

  const add = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Add");
  expect(add).toBeDefined();
  await act(async () => { add!.click(); });
  const usda = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "USDA");
  expect(usda).toBeDefined();
  await act(async () => { usda!.click(); });

  const input = container.querySelector<HTMLInputElement>('.fixed input[type="text"]');
  expect(input).not.toBeNull();
  await act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(input, "a");
    input!.dispatchEvent(new Event("input", { bubbles: true }));
  });
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 550)); });

  expect(searchUsda).toHaveBeenCalledWith("a");
  await act(async () => root.unmount());
});
