// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, expect, test, vi } from "vitest";
import { InventoryCorrectionEditor } from "./InventoryCorrectionEditor";
import { readCorrectionDraft, writeCorrectionDraft } from "@/lib/inventoryCorrectionDraft";
import type { CatalogItem } from "@/services/fsCatalogService";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));

const inventory: CatalogItem = { id: "rice-id", name: "Rice", kind: "ingredient", category: null, base_unit: "kg", purchase_unit: "kg", purchase_price: "50", units_per_purchase: "1", unit_cost: 50, default_supplier_id: null, vendor: null, include_in_generated_lists: false };
let root: Root;
let container: HTMLDivElement;

beforeEach(() => {
  (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
  container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  sessionStorage.clear(); push.mockClear();
  writeCorrectionDraft("flow-1", { editing: inventory, form: { name: "Rice", default_supplier_id: "", base_unit: "kg", purchase_price: "50", include_in_generated_lists: true }, affected: [{ recipe_id: "recipe-a", recipe_name: "Soup", line_index: 0, quantity: 2, unit: "tbsp" }], corrections: {} });
  vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { ingredients: [{ fs_item_id: 17, fs_item: { id: "rice-id" } }] } }) }));
});
afterEach(() => { act(() => root.unmount()); container.remove(); vi.unstubAllGlobals(); });

test("limits units to Inventory weight category and stages a deliberate quantity and unit", async () => {
  await act(async () => { root.render(<InventoryCorrectionEditor kind="recipe" id="recipe-a" token="flow-1" />); await Promise.resolve(); });
  const unit = container.querySelector<HTMLSelectElement>('select[aria-label="Soup new unit"]');
  expect(Array.from(unit?.options ?? [], (option) => option.value)).toEqual(["", "kg", "g", "oz"]);
  expect(container.querySelector('button[aria-label="About weight conversions"]')).not.toBeNull();
  expect(container.querySelector('button[aria-label="About liquid conversions"]')).not.toBeNull();
  expect(container.querySelector('button[aria-label="About piece and pack units"]')).not.toBeNull();
  await act(async () => { container.querySelector<HTMLButtonElement>("button:last-child")?.click(); });
  expect(push).not.toHaveBeenCalled();
  const quantity = container.querySelector<HTMLInputElement>('input[aria-label="Soup new quantity"]')!;
  await act(async () => { Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")?.set?.call(quantity, "0.5"); quantity.dispatchEvent(new Event("input", { bubbles: true })); });
  await act(async () => { if (unit) { unit.value = "g"; unit.dispatchEvent(new Event("change", { bubbles: true })); } });
  await act(async () => { Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Save correction")?.click(); });
  expect(readCorrectionDraft("flow-1")?.corrections["recipe-a:0"]).toEqual({ quantity: "0.5", unit: "g" });
  expect(push).toHaveBeenCalledWith("/food-service/inventory?inventoryCorrection=flow-1");
});
