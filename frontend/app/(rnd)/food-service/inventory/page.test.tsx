// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { listCatalog, updateFsItem, RecipeCorrectionsRequiredError, type CatalogItem } from "@/services/fsCatalogService";
import InventoryCatalogPage from "./page";
import { correctionKey, readCorrectionDraft, writeCorrectionDraft } from "@/lib/inventoryCorrectionDraft";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));

vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({ user: { role: "RND" } }) }));
vi.mock("@/hooks/useDebouncedValue", () => ({ useDebouncedValue: (value: string) => value }));
vi.mock("@/services/fsCatalogService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/fsCatalogService")>(),
  listCatalog: vi.fn(),
  listAllSuppliers: vi.fn().mockResolvedValue([]),
  updateFsItem: vi.fn(),
}));
vi.mock("@/services/supplierService", () => ({ listAllSuppliers: vi.fn().mockResolvedValue([]) }));

const mockListCatalog = vi.mocked(listCatalog);
const item: CatalogItem = {
  id: "rice-id", name: "Rice", kind: "ingredient", category: "Dry Goods", base_unit: "kg", purchase_unit: "sack",
  purchase_price: "2500", units_per_purchase: "50", unit_cost: 50, default_supplier_id: null, vendor: null,
  include_in_generated_lists: false,
};
const meta = { current_page: 1, per_page: 10, total: 1, last_page: 1 };

describe("Food Service inventory controls", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    mockListCatalog.mockResolvedValue({ data: [item], meta });
    sessionStorage.clear();
    window.history.replaceState(null, "", "/food-service/inventory");
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  test("shows shopping-list behavior in separate column", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });

    const headers = Array.from(container.querySelectorAll("th"), (header) => header.textContent?.trim());
    expect(headers).toContain("Shopping list behavior");
    expect(container.textContent).toContain("Purchase when needed");
    expect(container.querySelector("tr td:first-child")?.textContent?.trim()).toBe("Rice");
    expect(container.textContent).toContain("Cost/unit");
  });

  test("uses category select and keeps legacy category available while editing", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    const edit = container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice"]');
    await act(async () => { edit?.click(); });

    const category = container.querySelector<HTMLSelectElement>('select[aria-label="Food Service Category"]');
    expect(category).not.toBeNull();
    expect(category?.value).toBe("Dry Goods");
    expect(Array.from(category?.options ?? [], (option) => option.value)).toContain("Vegetable");
    expect(Array.from(category?.options ?? [], (option) => option.value)).toContain("Dry Goods");
  });

  test("filters ingredients by grocery behavior and explains it with help", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    const filter = container.querySelector<HTMLSelectElement>('select[aria-label="Shopping list behavior filter"]');
    expect(filter).not.toBeNull();
    await act(async () => { if (filter) { filter.value = "non_auto"; filter.dispatchEvent(new Event("change", { bubbles: true })); } });
    expect(mockListCatalog).toHaveBeenCalledWith("ingredient", 1, "", "non_auto");
    expect(container.querySelector('button[aria-label="About Auto Grocery"]')).not.toBeNull();
  });

  test("lets the server preserve cost when only the inventory unit changes", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice"]')?.click(); });
    const unit = Array.from(container.querySelectorAll("select")).find((select) => select.value === "kg");
    await act(async () => { if (unit) { unit.value = "g"; unit.dispatchEvent(new Event("change", { bubbles: true })); } });
    const save = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Save");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(vi.mocked(updateFsItem)).toHaveBeenCalledWith("rice-id", expect.not.objectContaining({ purchase_price: expect.anything() }));
  });

  test("lists affected Foods as links and commits only after every correction was staged", async () => {
    vi.mocked(updateFsItem)
      .mockRejectedValueOnce(new RecipeCorrectionsRequiredError("Update recipes first.", [
        { recipe_id: "recipe-a", recipe_name: "Soup", line_index: 0, quantity: 2, unit: "tbsp" },
        { recipe_id: "recipe-b", recipe_name: "Stew", line_index: 0, quantity: 1, unit: "cup" },
      ]))
      .mockResolvedValueOnce({ ...item, include_in_generated_lists: true });
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice"]')?.click(); });
    const auto = container.querySelector<HTMLInputElement>('input[type="checkbox"]');
    await act(async () => { auto?.click(); });
    const save = () => Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Save");
    await act(async () => { save()?.click(); await Promise.resolve(); });
    expect(container.textContent).toContain("Soup");
    expect(container.textContent).toContain("Stew");
    expect(container.querySelector('input[aria-label="Soup new quantity"]')).toBeNull();
    await act(async () => { save()?.click(); await Promise.resolve(); });
    expect(vi.mocked(updateFsItem)).toHaveBeenCalledTimes(1);
    const token = new URLSearchParams(window.location.search).get("inventoryCorrection") ?? "";
    const draft = readCorrectionDraft(token);
    expect(draft?.affected).toHaveLength(2);
    await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Correct")?.click(); });
    expect(push).toHaveBeenCalledWith(`/food-service/recipes/recipe-a?inventoryCorrection=${token}`);
    writeCorrectionDraft(token, { ...draft!, corrections: { "recipe-a:0": { quantity: "0.5", unit: "kg" } } });
    await act(async () => { root.unmount(); root = createRoot(container); root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    await act(async () => { save()?.click(); await Promise.resolve(); });
    expect(vi.mocked(updateFsItem)).toHaveBeenCalledTimes(1);
    writeCorrectionDraft(token, { ...draft!, corrections: Object.fromEntries(draft!.affected.map((row) => [correctionKey(row), { quantity: "0.5", unit: "kg" }])) });
    // Returning to Inventory reloads the same staged list from the URL.
    await act(async () => { root.unmount(); root = createRoot(container); root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    await act(async () => { save()?.click(); await Promise.resolve(); });
    expect(vi.mocked(updateFsItem)).toHaveBeenLastCalledWith("rice-id", expect.objectContaining({
      recipe_corrections: [
        { recipe_id: "recipe-a", line_index: 0, quantity: 0.5, unit: "kg" },
        { recipe_id: "recipe-b", line_index: 0, quantity: 0.5, unit: "kg" },
      ],
    }));
  });

  test("links editable Menu Calendar items to their correction page", async () => {
    vi.mocked(updateFsItem).mockRejectedValueOnce(new RecipeCorrectionsRequiredError("Update menu items first.", [
      { slot_id: "slot-a", cycle_id: "cycle-a", recipe_name: "Ward Adobo — Week A / Monday lunch", line_index: 0, quantity: 2, unit: "tbsp" },
    ])).mockResolvedValueOnce({ ...item, include_in_generated_lists: true });
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice"]')?.click(); });
    await act(async () => { container.querySelector<HTMLInputElement>('input[type="checkbox"]')?.click(); });
    const save = () => Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.trim() === "Save");
    await act(async () => { save()?.click(); await Promise.resolve(); });
    expect(container.textContent).toContain("Week A / Monday lunch");
    await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Correct")?.click(); });
    expect(push).toHaveBeenCalledWith(expect.stringContaining("/food-service/menu-cycle/cycle-a/lines/slot-a?inventoryCorrection="));
    expect(vi.mocked(updateFsItem)).toHaveBeenCalledTimes(1);
    expect(container.querySelector('input[aria-label="Ward Adobo — Week A / Monday lunch new quantity"]')).toBeNull();
  });
});
