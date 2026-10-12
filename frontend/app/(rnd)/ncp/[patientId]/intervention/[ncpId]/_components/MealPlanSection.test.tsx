// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import MealPlanSection from "./MealPlanSection";
import { fetchRecipeById, searchUsda } from "@/services/foodLibraryService";
import { fetchAllMealPlanItems } from "@/services/mealPlanService";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("@/services/mealPlanService", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/services/mealPlanService")>()),
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

it("shows scaled ingredient amounts per person and removes the generic scale slider", async () => {
  vi.mocked(fetchAllMealPlanItems).mockResolvedValueOnce([{
    id: "item-1",
    meal_plan_day_id: "day-1",
    food_item_id: null,
    fdc_id: null,
    recipe_id: "recipe-1",
    quantity: "1.5",
    unit: "serving",
    nutrient_snapshot: {
      name: "Rice bowl",
      calories: 200,
      protein: 8,
      carbs: 35,
      fat: 4,
      micronutrients: {},
      serving_size: 1,
      serving_unit: "serving",
    },
    ai_suggested: false,
    source: "recipe",
    recipe_portion: {
      yield: 4,
      ingredients: [{ name: "Rice", quantity: 400, unit: "g" }],
    },
  }]);

  const container = document.createElement("div");
  document.body.appendChild(container);
  const root = createRoot(container);
  vi.mocked(fetchRecipeById).mockResolvedValueOnce({
    id: "recipe-1", rnd_user_id: 1, name: "Rice bowl", category: "lunch", meal_types: null,
    prep_notes: null, servings: 4, total_calories: "800", total_protein: "32",
    total_carbs: "140", total_fat: "16", prepared_portion_amount: null,
    prepared_portion_unit: null, micronutrients: {}, ingredients: [{
      id: 1, food_item_id: 1, quantity: "400", unit: "g", food_item: {
        id: "food-1", name: "Rice", calories: "200", protein: "8", carbs: "35",
        fat: "4", serving_size: "100", serving_unit: "g", micronutrients: {},
      },
    }], created_at: "", updated_at: "",
  });
  await act(async () => {
    root.render(<MealPlanSection ncpId="ncp-1" interventionPlanId="intervention-1"
      prescriptionTargets={{ energy: 2000, protein: 75, carbs: 250, fat: 65, fluid: 2000 }} />);
  });
  await act(async () => { await Promise.resolve(); });

  expect(container.textContent).toContain("Per person: Rice 150 g");
  const edit = Array.from(container.querySelectorAll("button")).find((button) => button.title === "Edit");
  expect(edit).toBeDefined();
  await act(async () => { edit!.click(); });
  expect(container.textContent).not.toContain("Master Scale");
  expect(container.querySelector('input[type="range"]')).toBeNull();

  await act(async () => root.unmount());
});
