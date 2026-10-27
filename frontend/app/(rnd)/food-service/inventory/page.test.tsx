// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { listCatalog, createFsItem, updateFsItem, RecipeCorrectionsRequiredError, type CatalogItem } from "@/services/fsCatalogService";
import InventoryCatalogPage from "./page";
import { correctionKey, readCorrectionDraft, writeCorrectionDraft } from "@/lib/inventoryCorrectionDraft";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));

const role = vi.hoisted(() => ({ current: "RND" }));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({ user: { role: role.current } }) }));
vi.mock("@/hooks/useDebouncedValue", () => ({ useDebouncedValue: (value: string) => value }));
vi.mock("@/services/fsCatalogService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/fsCatalogService")>(),
  listCatalog: vi.fn(),
  createFsItem: vi.fn(),
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
    role.current = "RND";
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
    vi.restoreAllMocks();
  });

  test("opens existing Food Service recipes under Menu Items while templates stay in Menu Calendar", async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ data: [], meta }), { status: 200 }));
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    const tabs = container.querySelector('[role="tablist"][aria-label="Inventory sections"]')!;
    expect(Array.from(tabs.querySelectorAll('button')).map((button) => button.textContent)).toEqual(['Ingredients', 'Menu Items', 'Supplies']);
    await act(async () => { Array.from(tabs.querySelectorAll('button')).find((button) => button.textContent === 'Menu Items')!.click(); });
    expect(container.textContent).toContain('Single food item');
    expect(container.textContent).toContain('Search Menu Items');
    const controls = container.querySelector('[aria-label="Menu Item filters"]');
    expect(controls).not.toBeNull();
    expect(controls?.querySelector('input[type="search"]')).not.toBeNull();
    expect(controls?.querySelector('[aria-label="Menu Item categories"]')).not.toBeNull();
    expect(container.querySelector('[role="group"][aria-label="Shopping list behavior filter"]')).toBeNull();
    expect(globalThis.fetch).toHaveBeenCalledWith(expect.stringContaining('/api/fss/food-service-recipes?'));
  });

  test("Food Service staff can browse Menu Items without recipe mutation controls", async () => {
    role.current = 'FSS';
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ data: [{ id: 'food-1', name: 'Soup', category: 'meal', servings: 50 }], meta }), { status: 200 }));
    window.history.replaceState(null, '', '/food-service/inventory?tab=menu-items');
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    expect(container.textContent).toContain('Soup');
    expect(container.textContent).not.toContain('Single food item');
    expect(container.querySelector('a[href="/food-service/foods/food-1"]')).toBeNull();
  });

  test("shows shopping-list behavior in separate column", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });

    const headers = Array.from(container.querySelectorAll("th"), (header) => header.textContent?.trim());
    expect(headers).toContain("Shopping list behavior");
    expect(container.textContent).toContain("Purchase when needed");
    expect(container.querySelector("tr td:first-child")?.textContent?.trim()).toBe("Rice");
    expect(container.textContent).toContain("Cost/unit");
  });

  test("omits category from inventory editing and table columns", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    const edit = container.querySelector<HTMLButtonElement>('button[aria-label="Edit Rice"]');
    await act(async () => { edit?.click(); });

    expect(container.querySelector('select[aria-label="Food Service Category"]')).toBeNull();
    expect(Array.from(container.querySelectorAll("th"), (header) => header.textContent?.trim())).not.toContain("Category");
    expect(container.textContent).not.toContain("Dry Goods");
  });

  test("does not submit category when creating an inventory item", async () => {
    vi.mocked(createFsItem).mockResolvedValue({ ...item, name: "Soy sauce (900 g)", base_unit: "pack" });
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.includes("New Ingredient"))?.click(); });
    const modal = container.querySelector(".fixed.inset-0")!;
    const name = modal.querySelector<HTMLInputElement>('input:not([type="number"])')!;
    const unit = Array.from(modal.querySelectorAll<HTMLSelectElement>("select")).find((select) => Array.from(select.options).some((option) => option.value === "pack"))!;
    const cost = modal.querySelector<HTMLInputElement>('input[type="number"]')!;
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(name, "Soy sauce (900 g)");
      name.dispatchEvent(new Event("input", { bubbles: true }));
      unit.value = "pack";
      unit.dispatchEvent(new Event("change", { bubbles: true }));
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")!.set!.call(cost, "100");
      cost.dispatchEvent(new Event("input", { bubbles: true }));
    });
    await act(async () => { Array.from(modal.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Create")?.click(); await Promise.resolve(); });
    expect(createFsItem).toHaveBeenCalledWith(expect.objectContaining({ name: "Soy sauce (900 g)", kind: "ingredient", base_unit: "pack" }));
    expect(vi.mocked(createFsItem).mock.calls[0][0]).not.toHaveProperty("category");
  });

  test("explains package-based quantities beside ingredient filters", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    const hint = container.querySelector<HTMLButtonElement>('button[aria-label="About package units"]');
    expect(hint).not.toBeNull();
    expect(hint?.closest('[role="tablist"]')).toBeNull();
    await act(async () => { hint?.click(); });
    expect(document.body.textContent).toContain("For fixed packages, add the size to the name (e.g., “Soy sauce (900 g)”). Use a matching unit like pc or pack in inventory and recipes so Auto Grocery shows how many to buy, not just weight.");
  });

  test("drops the legacy category from restored inventory correction drafts", () => {
    sessionStorage.setItem("inventory-correction:legacy", JSON.stringify({
      editing: item,
      form: { name: item.name, category: "Dry Goods", default_supplier_id: "", base_unit: "kg", purchase_price: "2500", include_in_generated_lists: false },
      affected: [], corrections: {},
    }));
    expect(readCorrectionDraft("legacy")?.form).not.toHaveProperty("category");
  });

  test("filters ingredients by grocery behavior and explains it with help", async () => {
    await act(async () => { root.render(<InventoryCatalogPage />); await Promise.resolve(); });
    expect(container.querySelector('[role="tablist"][aria-label="Inventory sections"]')).not.toBeNull();
    const filters = container.querySelector('[role="group"][aria-label="Shopping list behavior filter"]');
    expect(filters).not.toBeNull();
    expect(Array.from(filters!.querySelectorAll('button')).map((button) => button.textContent)).toEqual(['All', 'Non-Auto Grocery', 'Auto Grocery']);
    await act(async () => { Array.from(filters!.querySelectorAll('button')).find((button) => button.textContent === 'Non-Auto Grocery')!.click(); });
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
    expect(vi.mocked(updateFsItem).mock.calls[0][1]).not.toHaveProperty("category");
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
