// @vitest-environment jsdom

import React, { act, Suspense } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import EditFoodPage from "./page";
import { fetchFoodItemById, updateFoodItem } from "@/services/foodLibraryService";
import { RecipeCorrectionsRequiredError } from "@/services/fsCatalogService";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/foodLibraryService", () => ({
  updateFoodItem: vi.fn(),
  fetchFoodItemById: vi.fn(async () => ({ id: "food-1", name: "Measured Ingredient", serving_size: "100", serving_unit: "g", calories: "100", protein: "10", carbs: "20", fat: "2", allergens: [], micronutrients: {} })),
}));

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let container: HTMLDivElement;
let root: Root;
afterEach(async () => {
  if (root) await act(async () => root.unmount());
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

async function renderEditor() {
  container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
  const params = Promise.resolve({ id: "food-1" });
  await act(async () => root.render(<Suspense fallback={null}><EditFoodPage params={params} /></Suspense>));
  return userEvent.setup();
}

test("Other category stays selected and is preserved on save", async () => {
  vi.mocked(fetchFoodItemById).mockResolvedValueOnce({
    id: "food-1", name: "Water", category: "other", usda_fdc_id: null,
    serving_size: "100", serving_unit: "mL", calories: "0", protein: "0", carbs: "0", fat: "0", water_g: 100,
    allergens: [], micronutrients: {}, created_at: "", updated_at: "",
  });
  await renderEditor();
  const category = container.querySelector('[aria-label="Category"]') as HTMLSelectElement;
  expect(category.value).toBe("other");
  expect(category.selectedOptions[0].textContent).toBe("Other");
  await act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
  expect(updateFoodItem).toHaveBeenCalledWith("food-1", expect.objectContaining({ category: "other", serving_unit: "mL", serving_size: 100 }));
});

test("baseline unit change converts size and preserves nutrient values", async () => {
  const user = await renderEditor();
  const unit = container.querySelector('[aria-label="Nutrient baseline unit"]') as HTMLSelectElement;
  expect(unit).not.toBeNull();
  expect(container.textContent).not.toContain("Ready-to-Eat Snack");
  expect(Array.from(unit.options).map((option) => option.value)).toEqual(["mg", "g", "kg", "oz", "lb", "mL", "L", "tsp", "tbsp", "cup", "piece"]);
  await act(async () => user.selectOptions(unit, "kg"));
  const size = container.querySelector('[aria-label="Nutrient baseline amount"]') as HTMLInputElement;
  expect(size.value).toBe("0.1");
  expect(size.step).toBe("0.1");
  await act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
  expect(updateFoodItem).toHaveBeenCalledWith("food-1", expect.objectContaining({ serving_size: 0.1, serving_unit: "kg", calories: 100 }));
});

test("incompatible baseline presents every affected Menu Item for measured correction", async () => {
  vi.mocked(updateFoodItem).mockRejectedValueOnce(new RecipeCorrectionsRequiredError("Correct affected Menu Items.", [{ recipe_id: "recipe-1", recipe_name: "Measured Meal", line_index: 0, quantity: "500", unit: "g" }]));
  const user = await renderEditor();
  await act(async () => user.selectOptions(container.querySelector('[aria-label="Nutrient baseline unit"]')!, "piece"));
  const size = container.querySelector('[aria-label="Nutrient baseline amount"]')!;
  await act(async () => { await user.clear(size); await user.type(size, "1"); });
  await act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
  expect(container.textContent).toContain("Measured Meal");
  expect(container.textContent).toContain("500 g");
  await act(async () => user.type(container.querySelector('[aria-label="Measured Meal correction quantity 1"]')!, "2"));
  await act(async () => container.querySelector("form")!.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true })));
  expect(updateFoodItem).toHaveBeenLastCalledWith("food-1", expect.objectContaining({ recipe_corrections: [{ recipe_id: "recipe-1", line_index: 0, quantity: 2, unit: "piece" }] }));
});
