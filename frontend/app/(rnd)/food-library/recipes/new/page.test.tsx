// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import NewRecipePage from "./page";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/foodLibraryService", () => ({
  createRecipe: vi.fn(),
  fetchFoodItems: vi.fn(async () => ({ data: [{ id: "food-1", name: "Measured Ingredient", serving_size: "100", serving_unit: "g", calories: "100", protein: "10", carbs: "20", fat: "2" }] })),
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
  expect(Array.from(category.options).map((option) => option.value)).toEqual(["", "meal", "carb", "snack"]);
  expect(container.textContent).toContain("Add Item");
  expect(container.textContent).toContain("Create Menu Item");
  expect(container.querySelector('[aria-label="Ingredient unit families"]')).not.toBeNull();

  const user = userEvent.setup();
  const inputs = container.querySelectorAll('input[type="text"]');
  await act(async () => user.type(inputs[1], "Measured"));
  const result = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Measured Ingredient"))!;
  await act(async () => user.click(result));
  const unit = container.querySelector('[aria-label="Ingredient 1 unit"]') as HTMLSelectElement;
  expect(Array.from(unit.options).map((option) => option.value)).toEqual(["mg", "g", "kg", "oz", "lb"]);
  await act(async () => user.selectOptions(unit, "kg"));
  const quantity = container.querySelector('[aria-label="Ingredient 1 quantity"]')!;
  expect(quantity.getAttribute("step")).toBe("0.000001");
  await act(async () => user.type(quantity, "0.5"));
  expect(container.textContent).toContain("500.0");
});
