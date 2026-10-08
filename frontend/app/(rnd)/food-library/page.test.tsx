// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import FoodLibraryPage from "./page";
import { fetchFoodItems, searchUsda } from "@/services/foodLibraryService";
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
});

describe("Food Library USDA search", () => {
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
  });
});
