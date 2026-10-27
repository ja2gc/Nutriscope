// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import FoodLibraryPage from "./page";
import { fetchFoodItems, fetchRecipes, searchUsda } from "@/services/foodLibraryService";
import { fetchMealPlanTemplates } from "@/services/mealPlanService";

vi.mock("@/services/foodLibraryService", () => ({
  fetchFoodItems: vi.fn(async () => ({ data: [], meta: null })),
  fetchRecipes: vi.fn(async () => ({ data: [], meta: null })),
  deleteFoodItem: vi.fn(),
  deleteRecipe: vi.fn(),
  searchUsda: vi.fn(),
  importUsdaFood: vi.fn(),
}));

vi.mock("@/services/mealPlanService", () => ({
  fetchMealPlanTemplates: vi.fn(async () => ({ data: [{ id: "template-1", name: "Simple week", description: null, goal_type: null, disease_stage: null, maternal_status: null, created_at: "2026-10-07" }], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } })),
  fetchMealPlanTemplate: vi.fn(),
  saveLibraryMealPlanTemplate: vi.fn(),
  deleteMealPlanTemplate: vi.fn(),
}));

const tomato = {
  fdc_id: 1,
  name: "Tomatoes, raw",
  data_type: "Foundation",
  food_category: "Vegetables",
  calories: 18,
  protein: 1,
  carbs: 4,
  fat: 0,
};

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

let container: HTMLDivElement;
let root: Root;

async function openUsdaSearch(): Promise<HTMLInputElement> {
  container = document.createElement("div");
  document.body.appendChild(container);
  root = createRoot(container);
  await act(async () => { root.render(<FoodLibraryPage />); });
  const open = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Import from USDA"));
  expect(open).toBeDefined();
  await act(async () => { open!.click(); });
  const label = Array.from(container.querySelectorAll("label")).find((item) => item.textContent === "Search USDA foods");
  expect(label).toBeDefined();
  return document.getElementById(label!.htmlFor) as HTMLInputElement;
}

async function changeSearch(input: HTMLInputElement, value: string): Promise<void> {
  await act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(input, value);
    input.dispatchEvent(new Event("input", { bubbles: true }));
  });
}

afterEach(async () => {
  if (root) await act(async () => root.unmount());
  document.body.innerHTML = "";
  vi.clearAllMocks();
  window.history.replaceState(null, '', '/');
});

describe("Food Library USDA search", () => {
  it('removes padded serving decimals without rounding meaningful quantities', async () => {
    vi.mocked(fetchFoodItems).mockResolvedValueOnce({ data: [
      { id: 'food-100', name: 'Fictional USDA food', serving_size: '100.000000', serving_unit: 'g', calories: '100', protein: '1', carbs: '2', fat: '3', micronutrients: {}, allergens: [] },
      { id: 'food-small', name: 'Fictional small serving', serving_size: '0.125000', serving_unit: 'g', calories: '100', protein: '1', carbs: '2', fat: '3', micronutrients: {}, allergens: [] },
    ], meta: null } as never);
    container = document.createElement('div'); document.body.appendChild(container); root = createRoot(container);
    await act(async () => { root.render(<FoodLibraryPage />); });
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 300)); });
    const micros = Array.from(container.querySelectorAll('button')).filter(button => button.textContent?.trim() === 'Micros');
    await act(async () => { micros[0].click(); });
    expect(container.textContent).toContain('per 100 g serving');
    expect(container.textContent).not.toContain('100.000000');
    await act(async () => { container.querySelector<HTMLButtonElement>('[aria-label="Close food micronutrients"]')!.click(); });
    await act(async () => { micros[1].click(); });
    expect(container.textContent).toContain('per 0.125 g serving');
  });
  it('asks before switching tabs away from an unsaved template', async () => {
    window.history.replaceState(null, '', '/food-library?tab=templates');
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
    await act(async () => root.render(<FoodLibraryPage />));
    await act(async () => Array.from(container.querySelectorAll<HTMLButtonElement>('button')).find(el => el.textContent === 'New Template')!.click());
    await changeSearch(container.querySelector<HTMLInputElement>('[aria-label="Create meal template"] input')!, 'Unsaved template');
    const menuTab = Array.from(container.querySelectorAll<HTMLButtonElement>('[role="tab"]')).find(el => el.textContent === 'Menu Items')!;
    await act(async () => menuTab.click());
    expect(container.querySelector('[role="dialog"][aria-label="Unsaved changes"]')).not.toBeNull();
    expect(container.querySelector<HTMLInputElement>('[aria-label="Create meal template"] input')?.value).toBe('Unsaved template');
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Stay on this page"]')!.click());
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    await act(async () => menuTab.click());
    await act(async () => Array.from(container.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')).find(el => el.textContent === 'Discard')!.click());
    expect(menuTab.getAttribute('aria-selected')).toBe('true');
  });
  it('returns to Menu Items when requested by the editor back link', async () => {
    window.history.replaceState(null, '', '/food-library?tab=recipes');
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
    await act(async () => root.render(<FoodLibraryPage />));
    const menuTab = Array.from(container.querySelectorAll('[aria-label="Food Library sections"] button')).find((button) => button.textContent === 'Menu Items');
    expect(menuTab?.getAttribute('aria-selected')).toBe('true');
    expect(container.textContent).not.toContain('No Menu Items Found');
  });
  it("offers Ingredients, Menu Items, and Templates with canonical Menu Item filters", async () => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => { root.render(<FoodLibraryPage />); });
    const tabs = Array.from(container.querySelectorAll('[aria-label="Food Library sections"] button'));
    expect(container.querySelector('[role="tablist"][aria-label="Food Library sections"]')).not.toBeNull();
    expect(tabs[0].getAttribute('aria-selected')).toBe('true');
    expect(container.querySelector('[aria-label="Food Library sections"]')?.className).not.toContain('overflow-x-auto');
    expect(tabs.map((tab) => tab.textContent)).toEqual(["Ingredients", "Menu Items", "Templates", "Food Calculator"]);
    const ingredientFilters = container.querySelector('[aria-label="Ingredient groups"]')!;
    expect(Array.from(ingredientFilters.querySelectorAll("button")).map((button) => button.textContent)).toEqual(["All", "protein", "carbs", "vegetable", "fat", "dairy", "fruit"]);
    await act(async () => { (tabs[1] as HTMLButtonElement).click(); });
    const filters = container.querySelector('[aria-label="Menu Item categories"]')!;
    expect(Array.from(filters.querySelectorAll("button")).map((button) => button.textContent)).toEqual(["All", "Meal", "Carb", "Snack", "Tube Feeding"]);
    await act(async () => { (filters.querySelectorAll("button")[2]).click(); });
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 300)); });
    expect(fetchRecipes).toHaveBeenLastCalledWith("", "carb", 1, 10, "library");
    expect(container.textContent).not.toMatch(/Breakfast|Lunch|Dinner|Diabetic-Friendly|High Protein/);
  });

  it("searches from the first letter", async () => {
    vi.mocked(searchUsda).mockResolvedValue([]);
    const input = await openUsdaSearch();
    await changeSearch(input, "a");
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 550)); });
    expect(searchUsda).toHaveBeenCalledWith("a");
    expect(fetchFoodItems).toHaveBeenCalled();
  });

  it("removes previous food results as soon as the query changes", async () => {
    vi.mocked(searchUsda).mockResolvedValueOnce([tomato]).mockRejectedValueOnce(new Error("USDA API search failed: 400"));
    const search = await openUsdaSearch();

    await changeSearch(search, "tomato");
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 550)); });
    expect(container.textContent).toContain("Tomatoes, raw");

    await changeSearch(search, "apple");
    expect(container.textContent).not.toContain("Tomatoes, raw");
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 550)); });
    expect(container.textContent).not.toContain("Tomatoes, raw");
  });
});

describe("Food Library meal templates", () => {
  it("shows owned templates with visible edit and delete actions plus a new-template form", async () => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => { root.render(<FoodLibraryPage />); });
    const tab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Templates"));
    expect(tab).toBeDefined();
    await act(async () => { tab!.click(); });
    expect(fetchMealPlanTemplates).toHaveBeenCalled();
    expect(container.textContent).toContain("Simple week");
    expect(container.querySelector('[aria-label="Edit Simple week"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="Delete Simple week"]')).not.toBeNull();
    const create = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("New Template"));
    expect(create).toBeDefined();
    await act(async () => { create!.click(); });
    expect(container.textContent).toContain("Create meal template");
    const addBreakfast = container.querySelector<HTMLButtonElement>('[data-menu-editor-add][aria-label="Add item to Monday Breakfast"]');
    expect(addBreakfast?.textContent?.trim()).toBe("Add item");
    expect(container.textContent).not.toContain("Item type");
  });
});
