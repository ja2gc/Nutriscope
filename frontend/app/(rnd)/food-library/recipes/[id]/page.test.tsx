// @vitest-environment jsdom

import React, { act, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import EditRecipePage from "./page";
import { fetchFoodItems } from "@/services/foodLibraryService";

const { profileParams } = vi.hoisted(() => ({ profileParams: new URLSearchParams() }));
vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }), useSearchParams: () => profileParams }));
vi.mock("@/services/foodLibraryService", () => ({
  updateRecipe: vi.fn(), fetchFoodItems: vi.fn(),
  fetchRecipeById: vi.fn(async () => ({ id: "recipe-1", name: "Measured Meal", category: "meal", servings: 5, total_calories: '500', total_protein: '50', total_carbs: '100', total_fat: '10', micronutrients: { iron: 10 }, ingredients: [{ id: 1, quantity: "0.5", unit: "kg", food_item: { id: "food-1", name: "Measured Ingredient", serving_size: "100", serving_unit: "g", calories: "100", protein: "10", carbs: "20", fat: "2", micronutrients: { iron: 2 } } }] })),
}));

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

test("existing Menu Item keeps batch amounts and converts macro and micro previews", async () => {
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  const params = Promise.resolve({ id: "recipe-1" });
  try {
    await act(async () => root.render(<Suspense fallback={null}><EditRecipePage params={params} /></Suspense>));
    expect(container.textContent).toContain("Add Item");
    expect(container.textContent).toContain("Edit Menu Item");
    const unit = container.querySelector('[aria-label="Ingredient 1 unit"]') as HTMLSelectElement;
    expect(Array.from(unit.options).map((option) => option.value)).toEqual(["mg", "g", "kg", "oz", "lb"]);
    expect(unit.value).toBe("kg");
    expect(container.textContent).toContain("500.0");
    const micros = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("View Micronutrient"))!;
    await act(async () => micros.click());
    expect(container.textContent).toContain("10 mg");
  } finally {
    await act(async () => root.unmount());
    container.remove();
  }
});

test("recipe profile view displays the same complete profile without editing controls", async () => {
  profileParams.set("view", "1");
  const container = document.createElement("div"); document.body.append(container);
  const root = createRoot(container);
  const params = Promise.resolve({ id: "recipe-1" });
  try {
    await act(async () => root.render(<Suspense fallback={null}><EditRecipePage params={params} /></Suspense>));
    expect(container.querySelector('[aria-label="Menu Item profile"]')).not.toBeNull();
    expect(container.textContent).toContain("Recipe makes: 5 servings");
    expect(container.textContent).toContain("Measured Ingredient");
    expect(container.textContent).toContain("Iron");
    expect(container.querySelectorAll("input, select, textarea, button[type=submit]")).toHaveLength(0);
  } finally { profileParams.delete("view"); await act(async () => root.unmount()); container.remove(); }
});

test('selects a paginated ingredient into the added row without changing existing amounts', async () => {
  const laterFood = { id: 'food-2', name: 'Later Ingredient', serving_size: '250', serving_unit: 'mL', calories: '40', protein: '2', carbs: '5', fat: '1' };
  vi.mocked(fetchFoodItems).mockResolvedValueOnce({ data: [{ id: 'food-1', name: 'First Ingredient', serving_size: '100', serving_unit: 'g', calories: '10' }], meta: { current_page: 1, per_page: 10, total: 11, last_page: 2 } } as never)
    .mockResolvedValueOnce({ data: [laterFood], meta: { current_page: 2, per_page: 10, total: 11, last_page: 2 } } as never);
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  const user = userEvent.setup();
  try {
    await act(async () => root.render(<Suspense fallback={null}><EditRecipePage params={Promise.resolve({ id: 'recipe-1' })} /></Suspense>));
    await act(async () => user.click(Array.from(container.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Add Item')!));
    await act(async () => user.click(container.querySelector('[aria-label="Choose ingredient 2"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(fetchFoodItems).toHaveBeenLastCalledWith('', 'all', 1);
    const picker = container.querySelector('[role="dialog"]')!;
    await act(async () => user.click(picker.querySelector('[aria-label="Next page"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(fetchFoodItems).toHaveBeenLastCalledWith('', 'all', 2);
    await act(async () => user.click(picker.querySelector('[aria-label="Add Later Ingredient"]')!));
    expect((container.querySelector('[aria-label="Ingredient 2 unit"]') as HTMLSelectElement).value).toBe('mL');
    expect((container.querySelector('[aria-label="Ingredient 2 quantity"]') as HTMLInputElement).value).toBe('');
    expect((container.querySelector('[aria-label="Ingredient 1 unit"]') as HTMLSelectElement).value).toBe('kg');
    expect((container.querySelector('[aria-label="Ingredient 1 quantity"]') as HTMLInputElement).value).toBe('0.5');
  } finally { await act(async () => root.unmount()); container.remove(); }
});

test('keeps existing ingredient rows when the paginated picker search fails', async () => {
  vi.mocked(fetchFoodItems).mockRejectedValueOnce(new Error('Ingredient search failed. Try again.'));
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<Suspense fallback={null}><EditRecipePage params={Promise.resolve({ id: 'recipe-1' })} /></Suspense>));
    await act(async () => container.querySelector('[aria-label="Choose ingredient 1"]')!.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(container.querySelector('[role="dialog"] [role="alert"]')?.textContent).toContain('Ingredient search failed. Try again.');
    expect((container.querySelector('[aria-label="Ingredient 1 unit"]') as HTMLSelectElement).value).toBe('kg');
    expect((container.querySelector('[aria-label="Ingredient 1 quantity"]') as HTMLInputElement).value).toBe('0.5');
    expect(container.textContent).toContain('Measured Ingredient');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
