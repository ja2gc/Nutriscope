// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import NewFoodPage from "./page";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/foodLibraryService", () => ({ createFoodItem: vi.fn() }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

test("new Ingredient offers canonical measured baselines and tenths for serving-size controls", async () => {
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<NewFoodPage />));
    const unit = container.querySelector('[aria-label="Nutrient baseline unit"]') as HTMLSelectElement;
    expect(unit).not.toBeNull();
    expect(Array.from(unit.options).map((option) => option.value)).toEqual(["mg", "g", "kg", "oz", "lb", "mL", "L", "tsp", "tbsp", "cup", "piece"]);
    const amount = container.querySelector('[aria-label="Nutrient baseline amount"]') as HTMLInputElement;
    expect(amount.min).toBe("0");
    expect(amount.step).toBe("0.1");
    expect(container.querySelector('[aria-label="Ingredient unit families"]')).not.toBeNull();
    expect(container.textContent).not.toContain("Ready-to-Eat Snack");
  } finally {
    await act(async () => root.unmount());
    container.remove();
  }
});
