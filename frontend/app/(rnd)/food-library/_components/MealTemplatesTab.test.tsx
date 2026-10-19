// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, expect, it, vi } from "vitest";
import { MealTemplatesTab } from "./MealTemplatesTab";
import { saveLibraryMealPlanTemplate } from "@/services/mealPlanService";
import { fetchRecipes, type Recipe } from "@/services/foodLibraryService";

vi.mock("@/services/foodLibraryService", () => ({
  fetchRecipes: vi.fn(async () => ({ data: [], meta: null })),
  fetchRecipeById: vi.fn(async () => ({ id: 'snack-1', name: 'Fruit cup', servings: 5.55, prep_notes: null, ingredients: [
    { id: 1, quantity: '750.000000', unit: 'g', food_item: { name: 'Papaya' } },
    { id: 2, quantity: '0.05', unit: 'g', food_item: { name: 'Cinnamon' } },
  ] })),
}));
vi.mock("@/services/mealPlanService", () => ({
  fetchMealPlanTemplates: vi.fn(async () => ({ data: [{ id: "template-1", name: "Measured week", created_at: "2026-10-15" }], meta: null })),
  fetchMealPlanTemplate: vi.fn(async () => ({
    id: "template-1", name: "Measured week", description: null,
    days: [{ day_of_week: "Monday", meal_type: "breakfast", items: [
      { id: "line-1", recipe_id: "snack-1", food_item_id: null, food_name: "Fruit cup", quantity: 1.25, unit: "serving", line_order: 0 },
      { id: "line-2", recipe_id: "carb-1", food_item_id: null, food_name: "Oats", quantity: 0.5, unit: "serving", line_order: 1 },
    ] }],
  })),
  saveLibraryMealPlanTemplate: vi.fn(), deleteMealPlanTemplate: vi.fn(),
}));

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let root: Root;
const recipeChoice: Recipe = {
  id: 'choice-1', rnd_user_id: 1, name: 'Measured rice meal', category: 'meal', meal_types: null,
  prep_notes: null, servings: 5, total_calories: '500', total_protein: '10', total_carbs: '100', total_fat: '5',
  prepared_portion_amount: '100', prepared_portion_unit: 'g', micronutrients: {}, ingredients: [], created_at: '', updated_at: '',
};
afterEach(async () => { if (root) await act(async () => root.unmount()); document.body.innerHTML = ""; vi.clearAllMocks(); });

it("edits ordered Menu Items without quantity controls and opens profiles from clickable slots", async () => {
  const container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  const calendar = container.querySelector('table[aria-label="Weekly menu calendar"]');
  expect(calendar).not.toBeNull();
  expect(calendar!.querySelectorAll('td[data-day]')).toHaveLength(35);
  const breakfast = calendar!.querySelector('[data-day="Monday"][data-meal="breakfast"]')!;
  expect(Array.from(breakfast.querySelectorAll('button[aria-label^="View "]')).map((button) => button.getAttribute('aria-label'))).toEqual(["View Fruit cup", "View Oats"]);
  expect(breakfast.querySelectorAll('[data-menu-editor-item]')).toHaveLength(2);
  expect(breakfast.querySelector('[data-menu-editor-item] > div > :first-child')?.hasAttribute('data-menu-editor-actions')).toBe(true);
  expect(breakfast.querySelectorAll('[data-menu-editor-add]')).toHaveLength(1);
  expect(breakfast.querySelector('[data-menu-editor-item]')?.className).toContain('h-32');
  expect(breakfast.querySelector('[data-menu-editor-add]')?.className).not.toContain('h-32');
  expect(breakfast.querySelector('[data-menu-editor-add]')?.previousElementSibling).toBe(breakfast.querySelectorAll('[data-menu-editor-item]')[1]);
  expect(breakfast.querySelectorAll('input, select')).toHaveLength(0);
  expect(breakfast.querySelector('[data-menu-editor-actions]')?.className).toContain('justify-end');
  expect(breakfast.querySelector('[data-menu-editor-action="remove"] svg')).not.toBeNull();
  expect(container.querySelector('[role="dialog"][aria-label="Meal template editor"]')).toBeNull();
  const addToLunch = calendar!.querySelector<HTMLButtonElement>('[data-menu-editor-add][aria-label="Add item to Tuesday Lunch"]')!;
  expect(addToLunch.textContent?.trim()).toBe('Add item');
  await act(async () => addToLunch.click());
  expect(document.activeElement).toBe(container.querySelector('input[type="search"]'));
  const close = container.querySelector<HTMLButtonElement>('[aria-label="Close Menu Item picker"]')!;
  await act(async () => (close as HTMLButtonElement).click());

  vi.mocked(saveLibraryMealPlanTemplate).mockRejectedValueOnce(new Error('Could not save. Try again.'));
  const save = Array.from(container.querySelectorAll('button')).find((button) => button.textContent === 'Save template')!;
  await act(async () => save.click());
  expect(container.querySelector('[role="alert"]')?.textContent).toContain('Could not save. Try again.');
  expect(container.querySelector('[role="alert"]')!.compareDocumentPosition(calendar!) & Node.DOCUMENT_POSITION_FOLLOWING).not.toBe(0);
  expect(container.querySelectorAll('td[data-day="Monday"][data-meal="breakfast"] button[aria-label^="View "]')).toHaveLength(2);
  expect(saveLibraryMealPlanTemplate).toHaveBeenCalledWith(expect.objectContaining({ lines: [
    expect.objectContaining({ recipe_id: 'snack-1', day_of_week: 'Monday', meal_type: 'breakfast', quantity: 1.25, unit: 'serving' }),
    expect.objectContaining({ recipe_id: 'carb-1', quantity: 0.5, unit: 'serving' }),
  ] }), 'template-1');
});

it("opens a complete read-only profile from the editable template and returns without losing lines", async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  expect(container.querySelector('[aria-label="View Measured week"]')).toBeNull();
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  expect(container.querySelector('table[aria-label="Weekly menu calendar"]')).not.toBeNull();
  expect(container.querySelectorAll('td[data-day]')).toHaveLength(35);
  expect(container.querySelector('[data-day="Monday"][data-meal="breakfast"]')?.textContent).toContain('1.3 servings');
  expect(container.querySelectorAll('td input, td select')).toHaveLength(0);
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="View Fruit cup"]')!.click());
  const profile = container.querySelector('[aria-label="Menu Item profile"]')!;
  expect(profile.getAttribute('role')).not.toBe('dialog');
  expect(container.querySelector('table[aria-label="Weekly menu calendar"]')).toBeNull();
  expect(profile.textContent).toContain('Papaya');
  expect(profile.textContent).toContain('Recipe makes: 5.6 servings');
  expect(profile.textContent).toContain('750 g');
  expect(profile.textContent).toContain('<0.1 g');
  expect(profile.textContent).not.toContain('750.000000');
  const back = profile.querySelector<HTMLButtonElement>('[aria-label="Back"]')!;
  await act(async () => back.click());
  expect(container.querySelector('[aria-label="Menu Item profile"]')).toBeNull();
  expect(document.activeElement).toBe(container.querySelector('[aria-label="View Fruit cup"]'));
  expect(saveLibraryMealPlanTemplate).not.toHaveBeenCalled();
});

it("opens item search from a calendar cell with initial results instead of separate day and meal selectors", async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  expect(container.querySelector('input[type="search"]')).toBeNull();
  const add = container.querySelector<HTMLButtonElement>('[data-menu-editor-add][aria-label="Add item to Tuesday Lunch"]')!;
  expect(add.textContent?.trim()).toBe('Add item');
  await act(async () => add.click());
  const picker = container.querySelector('[role="dialog"][aria-label="Add Menu Item"]');
  expect(picker).not.toBeNull();
  expect(picker!.textContent).toContain('Tuesday Lunch');
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 250)); });
  expect(fetchRecipes).toHaveBeenCalledWith('', 'all', 1, 10);
  expect(picker!.textContent).toContain('No Menu Items found.');
});

it("returns from the template editor to the template list without saving", async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  const back = container.querySelector<HTMLButtonElement>('[aria-label="Back to templates"]')!;
  await act(async () => back.click());
  expect(container.querySelector('[aria-label="Edit Measured week"]')).not.toBeNull();
  expect(container.querySelector('table[aria-label="Weekly menu calendar"]')).toBeNull();
  expect(saveLibraryMealPlanTemplate).not.toHaveBeenCalled();
});

it("paginates item choices and adds to the selected cell without replacing other lines", async () => {
  vi.mocked(fetchRecipes).mockResolvedValueOnce({ data: Array.from({ length: 10 }, (_, index) => ({ ...recipeChoice, id: `first-${index}`, name: `Other meal ${index}` })), meta: { current_page: 1, per_page: 10, total: 11, last_page: 2, from: 1, to: 10, path: '/api/rnd/recipes' } })
    .mockResolvedValueOnce({ data: [recipeChoice], meta: { current_page: 2, per_page: 10, total: 11, last_page: 2, from: 11, to: 11, path: '/api/rnd/recipes' } });
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  await act(async () => container.querySelector<HTMLButtonElement>('[data-menu-editor-add][aria-label="Add item to Tuesday Lunch"]')!.click());
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 250)); });
  const picker = container.querySelector('[aria-label="Add Menu Item"]')!;
  await act(async () => picker.querySelector<HTMLButtonElement>('[aria-label="Next page"]')!.click());
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 250)); });
  expect(fetchRecipes).toHaveBeenLastCalledWith('', 'all', 2, 10);
  await act(async () => Array.from(picker.querySelectorAll('button')).find((button) => button.textContent?.includes(recipeChoice.name))!.click());
  expect(container.querySelector('[data-day="Tuesday"][data-meal="lunch"]')?.textContent).toContain(recipeChoice.name);
  expect(container.querySelectorAll('[data-day="Monday"][data-meal="breakfast"] button[aria-label^="View "]')).toHaveLength(2);
});

it("keeps the target cell and query when item search fails and recovers on another search", async () => {
  vi.mocked(fetchRecipes).mockRejectedValueOnce(new Error('Search failed. Try again.')).mockResolvedValueOnce({ data: [recipeChoice] });
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<MealTemplatesTab />));
  await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Edit Measured week"]')!.click());
  await act(async () => container.querySelector<HTMLButtonElement>('[data-menu-editor-add][aria-label="Add item to Wednesday Dinner"]')!.click());
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 250)); });
  const picker = container.querySelector('[aria-label="Add Menu Item"]')!;
  expect(picker.querySelector('[role="alert"]')?.textContent).toContain('Search failed. Try again.');
  const input = picker.querySelector<HTMLInputElement>('input[type="search"]')!;
  await act(async () => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(input, 'rice'); input.dispatchEvent(new Event('input', { bubbles: true })); });
  await act(async () => { await new Promise((resolve) => setTimeout(resolve, 250)); });
  expect(input.value).toBe('rice');
  expect(picker.textContent).toContain('Wednesday Dinner');
  expect(picker.querySelector('[role="alert"]')).toBeNull();
  expect(picker.textContent).toContain(recipeChoice.name);
});
