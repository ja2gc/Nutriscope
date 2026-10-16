// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import { MealTemplatesTab } from "./MealTemplatesTab";
import { saveLibraryMealPlanTemplate } from "@/services/mealPlanService";

vi.mock("@/services/foodLibraryService", () => ({ fetchRecipes: vi.fn(async () => ({ data: [], meta: null })) }));
vi.mock("@/services/mealPlanService", () => ({
  fetchMealPlanTemplates: vi.fn(async () => ({ data: [{ id: "template-1", name: "Measured week", created_at: "2026-10-15" }], meta: null })),
  fetchMealPlanTemplate: vi.fn(async () => ({
    id: "template-1", name: "Measured week", description: null,
    days: [{ day_of_week: "Monday", meal_type: "breakfast", items: [
      { id: "line-1", recipe_id: "snack-1", food_item_id: null, food_name: "Fruit cup", quantity: "1.20", unit: "serving", line_order: 0 },
      { id: "line-2", recipe_id: "carb-1", food_item_id: null, food_name: "Oats", quantity: "0.50", unit: "serving", line_order: 1 },
    ] }],
  })),
  saveLibraryMealPlanTemplate: vi.fn(), deleteMealPlanTemplate: vi.fn(),
}));

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let root: Root;
afterEach(async () => { if (root) await act(async () => root.unmount()); document.body.innerHTML = ""; vi.clearAllMocks(); });

it("edits ordered Menu Items in the same full-week calendar with controlled serving units and profile links", async () => {
  const container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  const calendar = container.querySelector('table[aria-label="Weekly menu calendar"]');
  expect(calendar).not.toBeNull();
  expect(calendar!.querySelectorAll('td[data-day]')).toHaveLength(35);
  const breakfast = calendar!.querySelector('[data-day="Monday"][data-meal="breakfast"]')!;
  expect(Array.from(breakfast.querySelectorAll('a')).map((link) => link.textContent)).toEqual(["Fruit cup", "Oats"]);
  expect(breakfast.querySelector('a')?.getAttribute('href')).toBe('/food-library/recipes/snack-1');
  expect(Array.from(breakfast.querySelectorAll('select')).map((select) => Array.from(select.options).map((option) => option.value))).toEqual([["serving"], ["serving"]]);
  expect(container.querySelector('[role="dialog"][aria-label="Meal template editor"]')).toBeNull();
  const addToLunch = Array.from(calendar!.querySelectorAll('button')).find((button) => button.textContent === 'Add to Tuesday Lunch')!;
  await act(async () => addToLunch.click());
  expect(document.activeElement).toBe(container.querySelector('input[type="search"]'));

  vi.mocked(saveLibraryMealPlanTemplate).mockRejectedValueOnce(new Error('Could not save. Try again.'));
  const save = Array.from(container.querySelectorAll('button')).find((button) => button.textContent === 'Save template')!;
  await act(async () => save.click());
  expect(container.querySelector('[role="alert"]')?.textContent).toContain('Could not save. Try again.');
  expect(container.querySelectorAll('td[data-day="Monday"][data-meal="breakfast"] a')).toHaveLength(2);
  expect(saveLibraryMealPlanTemplate).toHaveBeenCalledWith(expect.objectContaining({ lines: [
    expect.objectContaining({ recipe_id: 'snack-1', day_of_week: 'Monday', meal_type: 'breakfast', quantity: 1.2, unit: 'serving' }),
    expect.objectContaining({ recipe_id: 'carb-1', quantity: 0.5, unit: 'serving' }),
  ] }), 'template-1');
});

it("previews the same calendar without editing or saving controls", async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="View Measured week"]')!.click());
  expect(container.querySelector('table[aria-label="Weekly menu calendar"]')).not.toBeNull();
  expect(container.querySelectorAll('td[data-day]')).toHaveLength(35);
  expect(container.querySelectorAll('td input, td select, td button')).toHaveLength(0);
  expect(container.querySelector('a')?.textContent).toBe('Fruit cup');
  expect(saveLibraryMealPlanTemplate).not.toHaveBeenCalled();
});
