// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import MealPlanSection from "./MealPlanSection";
import { fetchFoodItems, fetchRecipes, fetchRecipeById, searchUsda } from "@/services/foodLibraryService";
import { addMealPlanItem, fetchAllMealPlanItems, updateMealPlanItem } from "@/services/mealPlanService";

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
  addMealPlanItem: vi.fn(async () => ({ id: "item-new", meal_plan_day_id: "day-1", recipe_id: "snack-1", quantity: "1", unit: "serving", source: "recipe", nutrient_snapshot: { name: "Fruit cup", calories: 80, protein: 1, carbs: 20, fat: 0, serving_size: 1, serving_unit: "serving", micronutrients: {} } })),
  updateMealPlanItem: vi.fn(async () => ({ id: "item-1", meal_plan_day_id: "day-1", recipe_id: "recipe-1", quantity: "1.5", unit: "serving", source: "recipe", nutrient_snapshot: { name: "Rice bowl", calories: 200, protein: 8, carbs: 35, fat: 4, serving_size: 1, serving_unit: "serving", micronutrients: {} } })),
}));

vi.mock("@/services/foodLibraryService", () => ({
  fetchFoodItems: vi.fn(),
  fetchRecipes: vi.fn(async () => ({ data: [{ id: "snack-1", name: "Fruit cup", category: "snack", servings: 1, total_calories: "80", total_protein: "1", total_carbs: "20", total_fat: "0" }], meta: null })),
  searchUsda: vi.fn(async () => []),
  importUsdaFood: vi.fn(),
  fetchRecipeById: vi.fn(),
}));

afterEach(() => {
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

it("offers Menu Items only and allows a Snack in a manually planned main slot", async () => {
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
  const input = container.querySelector<HTMLInputElement>('[aria-label="Search Menu Items"]');
  expect(input).not.toBeNull();
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 300)); });
  const picker = container.querySelector('[aria-label="Add Menu Item"]')!;
  expect(picker.textContent).not.toMatch(/USDA|Library|Recipes/);
  expect(fetchRecipes).toHaveBeenCalledWith("", "all", 1);
  expect(fetchFoodItems).not.toHaveBeenCalled();
  expect(searchUsda).not.toHaveBeenCalled();
  const addSnack = Array.from(picker.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Add")!;
  await act(async () => { addSnack.click(); });
  expect(addMealPlanItem).toHaveBeenCalledWith("ncp-1", "plan-1", "day-1", { recipe_id: "snack-1", quantity: 1, unit: "serving" });
  expect(container.textContent).toContain("Fruit cup");
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
      id: 1, food_item_id: 1, quantity: "0.4", unit: "kg", food_item: {
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
  const ingredientTab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Ingredients")!;
  await act(async () => ingredientTab.click());
  const editor = container.querySelector('.fixed')!;
  expect(editor.textContent).toContain("Energy200kcal");
  expect(editor.querySelector('input[type="number"]')?.getAttribute("step")).toBe("0.000001");
  const save = Array.from(editor.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Save Changes")!;
  await act(async () => save.click());
  expect(updateMealPlanItem).toHaveBeenCalledWith("ncp-1", "plan-1", "day-1", "item-1", { quantity: 1.5, ingredient_overrides: [{ id: 1, quantity: 0.4 }] });

  await act(async () => root.unmount());
});
