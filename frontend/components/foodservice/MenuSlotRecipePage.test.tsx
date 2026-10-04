// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import { getMenuSlotRecipe, updateMenuSlotRecipe } from "@/services/menuCycleService";
import { searchCatalog } from "@/services/fsCatalogService";
import { MenuSlotRecipePage } from "./MenuSlotRecipePage";

vi.mock("next/navigation", () => ({
  useParams: () => ({ cycleId: "cycle-1", day: "Monday", meal: "lunch" }),
}));
vi.mock("@/services/menuCycleService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/menuCycleService")>(),
  getMenuSlotRecipe: vi.fn(),
  updateMenuSlotRecipe: vi.fn(),
  restoreMenuSlotRecipe: vi.fn(),
}));
vi.mock("@/services/fsCatalogService", () => ({ searchCatalog: vi.fn() }));

const loadMock = vi.mocked(getMenuSlotRecipe);
const updateMock = vi.mocked(updateMenuSlotRecipe);
const searchMock = vi.mocked(searchCatalog);
const slot = {
  id: "line-1",
  cycle_id: "cycle-1",
  day: "Monday" as const,
  meal: "lunch" as const,
  line_order: 1,
  source: "master" as const,
  locked: false,
  editable: true,
  name: "Chicken Adobo",
  reference_servings: 25,
  planned_servings: 100,
  purchase_estimate_set: true,
  prep_notes: "Simmer until tender.",
  ingredients: [{ fs_item_id: "item-1", name: "Chicken", quantity: 3, unit: "kg", scaled_quantity: 12, scaled_cost: 1200, include_in_generated_lists: true }],
  total_cost: 1200,
  baseline_total_cost: 300,
  cost_per_head: 12,
};

describe("MenuSlotRecipePage", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    loadMock.mockResolvedValue(slot);
    updateMock.mockResolvedValue(slot);
    searchMock.mockResolvedValue([]);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  test("shows slot-only editing controls to RND", async () => {
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });

    expect(container.querySelector('button[aria-label="About menu slot changes"]')).not.toBeNull();
    expect(container.textContent).toContain("Add ingredient");
    expect(container.querySelector('button[type="submit"]')?.textContent).toContain("Save slot changes");
  });

  test("uses the shared unit picker while retaining a legacy unit value", async () => {
    loadMock.mockResolvedValue({
      ...slot,
      ingredients: [{ ...slot.ingredients[0], unit: "cup" }],
    });
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });

    const unit = container.querySelector<HTMLSelectElement>('select[aria-label="Unit for Chicken"]');
    expect(unit).not.toBeNull();
    expect(Array.from(unit?.options ?? []).map((option) => option.value)).toContain("kg");
    expect(Array.from(unit?.options ?? []).map((option) => option.value)).toContain("cup");
    expect(unit?.value).toBe("cup");
  });

  test("PO-locked view shows only total menu quantity and a plain unit", async () => {
    loadMock.mockResolvedValue({ ...slot, locked: true });
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });

    expect(container.textContent).not.toContain("Recipe makes");
    expect(container.textContent).not.toContain("Baseline used to scale every ingredient");
    expect(container.textContent).not.toContain("Baseline recipe cost");
    expect(container.textContent).not.toContain("View baseline recipe values");
    expect(container.textContent).not.toContain("Baseline qty");
    expect(container.textContent).not.toContain("Recipe qty");
    expect(container.textContent).not.toContain("View quantities for the current menu servings.");
    expect(container.textContent).not.toContain("Quantity needed is scaled to the estimated servings.");
    expect(container.textContent).toContain("Qty needed");
    expect(container.textContent).toContain("12 kg");
    expect(container.querySelector('select[aria-label="Unit for Chicken"]')).toBeNull();
    expect(container.textContent).toContain("Unitkg");
  });

  test("keeps shopping-list behavior in its own ingredient column", async () => {
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });
    const ingredientLabel = Array.from(container.querySelectorAll("span"))
      .find((label) => label.textContent?.trim() === "Ingredient");
    const behaviorLabel = Array.from(container.querySelectorAll("span"))
      .find((label) => label.textContent?.trim() === "Shopping list");

    expect(ingredientLabel).toBeDefined();
    expect(behaviorLabel).toBeDefined();
    expect(ingredientLabel?.parentElement?.textContent ?? "").not.toContain("Purchase when needed");
    expect(behaviorLabel?.parentElement?.textContent ?? "").toContain("Auto grocery");
  });

  test("shows slot explanation through the shared tooltip", async () => {
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });
    expect(container.querySelector('button[aria-label="About menu slot changes"]')).not.toBeNull();
    expect(container.textContent).not.toContain("Changes apply only to this menu slot. The original recipe stays unchanged.");
    const help = container.querySelector<HTMLButtonElement>('button[aria-label="About menu slot changes"]');
    if (!help) throw new Error("Menu slot help button missing.");
    await userEvent.setup().hover(help);
    expect(document.body.textContent).toContain("Changes apply only to this menu slot. The original recipe stays unchanged.");
  });

  test("adds selected Inventory item through existing menu slot payload", async () => {
    const rice = {
      id: "inventory-rice-uuid", name: "Rice", kind: "ingredient" as const, category: "Grain",
      base_unit: "kg", purchase_unit: "kg", purchase_price: "52", units_per_purchase: null,
      unit_cost: 52, default_supplier_id: null, vendor: null, vendor_locked: false, include_in_generated_lists: true,
    };
    const user = userEvent.setup();
    searchMock.mockResolvedValue([rice]);
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });
    const searchInput = container.querySelector<HTMLInputElement>('input[aria-label="Search inventory items"]');
    if (!searchInput) throw new Error("Ingredient search input missing.");
    await act(async () => { await user.type(searchInput, "Rice"); });
    const search = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Search"));
    await act(async () => { search?.click(); await Promise.resolve(); });
    const riceResult = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Rice"));
    await act(async () => { riceResult?.click(); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[type="submit"]')?.click(); await Promise.resolve(); });

    expect(searchMock).toHaveBeenCalledWith("Rice", "ingredient");
    expect(updateMock).toHaveBeenCalledWith("cycle-1", "Monday", "lunch", expect.objectContaining({
      ingredients: [
        { fs_item_id: "item-1", quantity: 3, unit: "kg" },
        { fs_item_id: "inventory-rice-uuid", quantity: 1, unit: "kg" },
      ],
    }));
  });

  test("limits scaled quantity display to two decimals", async () => {
    loadMock.mockResolvedValue({
      ...slot,
      ingredients: [{ ...slot.ingredients[0], quantity: 3.14159 }],
    });
    await act(async () => { root.render(<MenuSlotRecipePage backHref="/food-service/menu-cycle?cycle=cycle-1" />); });

    expect(container.textContent).toContain("12.57 kg");
    expect(container.textContent).not.toContain("12.566 kg");
  });
});
