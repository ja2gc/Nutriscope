// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { getMenuSlotRecipe } from "@/services/menuCycleService";
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
const slot = {
  cycle_id: "cycle-1",
  day: "Monday" as const,
  meal: "lunch" as const,
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
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  test("shows a read-only FSS details page", async () => {
    await act(async () => { root.render(<MenuSlotRecipePage readOnly backHref="/fss/menu?cycle=cycle-1" />); });

    expect(container.textContent).toContain("Menu Item Details");
    expect(container.textContent).toContain("Recipe makes");
    expect(container.textContent).toContain("12.000 kg");
    expect(container.querySelector('button[type="submit"]')).toBeNull();
  });

  test("shows slot-only editing controls to RND", async () => {
    await act(async () => { root.render(<MenuSlotRecipePage readOnly={false} backHref="/food-service/menu-cycle?cycle=cycle-1" />); });

    expect(container.textContent).toContain("Changes apply only to this menu slot");
    expect(container.textContent).toContain("Add ingredient");
    expect(container.querySelector('button[type="submit"]')?.textContent).toContain("Save slot changes");
  });
});
