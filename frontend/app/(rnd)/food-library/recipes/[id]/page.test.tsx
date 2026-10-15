// @vitest-environment jsdom

import React, { act, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import EditRecipePage from "./page";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/foodLibraryService", () => ({
  updateRecipe: vi.fn(), fetchFoodItems: vi.fn(),
  fetchRecipeById: vi.fn(async () => ({ id: "recipe-1", name: "Measured Meal", category: "meal", servings: 5, ingredients: [{ quantity: "0.5", unit: "kg", food_item: { id: "food-1", name: "Measured Ingredient", serving_size: "100", serving_unit: "g", calories: "100", protein: "10", carbs: "20", fat: "2", micronutrients: { iron: 2 } } }] })),
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
