// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import NewRecipePage from "./page";
import { createRecipe, fetchFoodItems } from "@/services/foodLibraryService";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/foodLibraryService", () => ({
  createRecipe: vi.fn(),
  fetchFoodItems: vi.fn(async () => ({ data: [{ id: "food-1", name: "Measured Ingredient", serving_size: "100", serving_unit: "g", calories: "100", protein: "10", carbs: "20", fat: "2" }], meta: null })),
}));

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let container: HTMLDivElement;
let root: Root;

afterEach(async () => {
  if (root) await act(async () => root.unmount());
  document.body.innerHTML = "";
  vi.clearAllMocks();
});

test("Menu Item editor uses canonical categories, measured units, and converted preview", async () => {
  container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
  await act(async () => root.render(<NewRecipePage />));
  const category = container.querySelector("select")!;
  expect(Array.from(category.options).map((option) => option.value)).toEqual(["", "meal", "carb", "snack", "tube_feeding"]);
  expect(container.textContent).toContain("Add Item");
  expect(container.textContent).toContain("Create Menu Item");
  expect(container.querySelector('[aria-label="About Nutrition source"]')).not.toBeNull();
  expect(container.textContent).toContain('View Micronutrient Profile');
  expect(container.querySelector('[aria-label="Ingredient unit families"]')).not.toBeNull();

  const user = userEvent.setup();
  await act(async () => user.click(container.querySelector('[aria-label="Choose ingredient 1"]')!));
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
  expect(fetchFoodItems).toHaveBeenCalledWith('', 'all', 1);
  const result = container.querySelector('[role="dialog"] [aria-label="Add Measured Ingredient"]')!;
  await act(async () => user.click(result));
  const unit = container.querySelector('[aria-label="Ingredient 1 unit"]') as HTMLSelectElement;
  expect(Array.from(unit.options).map((option) => option.value)).toEqual(["mg", "g", "kg", "oz", "lb"]);
  await act(async () => user.selectOptions(unit, "kg"));
  const quantity = container.querySelector('[aria-label="Ingredient 1 quantity"]')!;
  expect(quantity.getAttribute("step")).toBe("0.1");
  await act(async () => user.type(quantity, "0.5"));
  expect(container.textContent).toContain("500.0");
});

test('loads paginated ingredients when opened and selects a paginated ingredient into the active row', async () => {
  const laterFood = { id: 'food-2', name: 'Later Ingredient', serving_size: '250', serving_unit: 'mL', calories: '40', protein: '2', carbs: '5', fat: '1' };
  vi.mocked(fetchFoodItems).mockResolvedValueOnce({ data: [{ id: 'food-1', name: 'First Ingredient', serving_size: '100', serving_unit: 'g', calories: '10' }], meta: { current_page: 1, per_page: 10, total: 11, last_page: 2 } } as never)
    .mockResolvedValueOnce({ data: [laterFood], meta: { current_page: 2, per_page: 10, total: 11, last_page: 2 } } as never);
  container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<NewRecipePage />));
  const user = userEvent.setup();
  try {
    await act(async () => user.click(container.querySelector('[aria-label="Choose ingredient 1"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(fetchFoodItems).toHaveBeenLastCalledWith('', 'all', 1);
    const picker = container.querySelector('[role="dialog"]')!;
    await act(async () => user.click(picker.querySelector('[aria-label="Next page"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(fetchFoodItems).toHaveBeenLastCalledWith('', 'all', 2);
    await act(async () => user.click(picker.querySelector('[aria-label="Add Later Ingredient"]')!));
    expect((container.querySelector('[aria-label="Ingredient 1 unit"]') as HTMLSelectElement).value).toBe('mL');
    expect((container.querySelector('[aria-label="Ingredient 1 quantity"]') as HTMLInputElement).value).toBe('');
  } finally { await act(async () => root.unmount()); }
});

test('recovers from an ingredient search error without losing the active row', async () => {
  vi.mocked(fetchFoodItems).mockRejectedValueOnce(new Error('Ingredient search failed. Try again.'));
  container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<NewRecipePage />));
  try {
    const quantity = container.querySelector('[aria-label="Ingredient 1 quantity"]') as HTMLInputElement;
    await act(async () => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(quantity, '3'); quantity.dispatchEvent(new Event('input', { bubbles: true })); });
    await act(async () => container.querySelector('[aria-label="Choose ingredient 1"]')!.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(container.querySelector('[role="dialog"] [role="alert"]')?.textContent).toContain('Ingredient search failed. Try again.');
    expect((container.querySelector('[aria-label="Ingredient 1 quantity"]') as HTMLInputElement).value).toBe('3');
    expect(container.querySelector('[aria-label="Choose ingredient 1"]')).not.toBeNull();
  } finally { await act(async () => root.unmount()); }
});

test("Create Menu Item accepts branded nutrition facts in the same form", async () => {
  container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
  await act(async () => root.render(<NewRecipePage />));
  const source = container.querySelector('[aria-label="Nutrition source"]');
  expect(source).not.toBeNull();
  const user = userEvent.setup();
  await act(async () => user.selectOptions(source!, "label"));
  await act(async () => user.type(container.querySelector('[aria-label="Menu Item Name"]')!, "Demo branded drink"));
  await act(async () => user.selectOptions(container.querySelector('[aria-label="Category"]')!, "snack"));
  await act(async () => user.type(container.querySelector('[aria-label="Recipe yield (servings)"]')!, "6"));
  const amount = container.querySelector('[aria-label="Label serving amount"]')!;
  await act(async () => { await user.clear(amount); await user.type(amount, "250"); });
  await act(async () => user.selectOptions(container.querySelector('[aria-label="Label serving unit"]')!, "mL"));
  await act(async () => user.type(container.querySelector('[aria-label="Calories (kcal)"]')!, "120"));
  await act(async () => user.type(container.querySelector('[aria-label="Calcium (mg)"]')!, "300"));
  await act(async () => user.click(container.querySelector('[aria-label="Contains milk"]')!));
  const submit = container.querySelector('button[type="submit"]')!;
  await act(async () => user.click(submit));
  expect(createRecipe).toHaveBeenCalledWith(expect.objectContaining({
    name: "Demo branded drink", category: "snack", servings: 6,
    nutrition_label: expect.objectContaining({ serving_size: 250, serving_unit: "mL", calories: 120, micronutrients: { calcium: 300 }, allergens: ["milk"] }),
  }));
  expect(vi.mocked(createRecipe).mock.calls[0][0]).not.toHaveProperty("ingredients");
  expect(container.textContent).not.toContain("Single Item");
});
