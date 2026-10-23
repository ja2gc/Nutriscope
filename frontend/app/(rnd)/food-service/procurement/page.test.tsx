// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import {
  approveShoppingList, createShoppingList, generateByDates, getPurchaseOrder, getShoppingList,
  listPurchaseOrders, listShoppingLists, updateShoppingList, updateVendorGroup,
  type PurchaseOrder, type ShoppingList,
} from "@/services/procurementService";
import { searchCatalog, type CatalogItem } from "@/services/fsCatalogService";
import { ShoppingListCopyCard } from "@/components/foodservice/ShoppingListCopyCard";
import ProcurementPage from "./page";

vi.mock("next/navigation", () => ({ useSearchParams: () => new URLSearchParams() }));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({ user: { role: "RND" } }) }));
vi.mock("@/hooks/useDebouncedValue", () => ({ useDebouncedValue: (value: string) => value }));
vi.mock("@/services/procurementService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/procurementService")>(),
  createShoppingList: vi.fn(),
  approveShoppingList: vi.fn(),
  generateByDates: vi.fn(),
  getShoppingList: vi.fn(),
  listShoppingLists: vi.fn(),
  listPurchaseOrders: vi.fn(),
  getPurchaseOrder: vi.fn(),
  updateShoppingList: vi.fn(),
  updateVendorGroup: vi.fn(),
}));
vi.mock("@/services/fsCatalogService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/fsCatalogService")>(),
  searchCatalog: vi.fn(),
}));
vi.mock("@/services/supplierService", () => ({ listAllSuppliers: vi.fn().mockResolvedValue([]) }));

const mockCreateShoppingList = vi.mocked(createShoppingList);
const mockApproveShoppingList = vi.mocked(approveShoppingList);
const mockGenerateByDates = vi.mocked(generateByDates);
const mockGetPurchaseOrder = vi.mocked(getPurchaseOrder);
const mockGetShoppingList = vi.mocked(getShoppingList);
const mockListPurchaseOrders = vi.mocked(listPurchaseOrders);
const mockListShoppingLists = vi.mocked(listShoppingLists);
const mockSearchCatalog = vi.mocked(searchCatalog);
const mockUpdateShoppingList = vi.mocked(updateShoppingList);
const mockUpdateVendorGroup = vi.mocked(updateVendorGroup);
const meta = { current_page: 1, per_page: 10, total: 1, last_page: 1 };

function makePo(overrides: Partial<PurchaseOrder> = {}): PurchaseOrder {
  return {
    id: "po-1", shopping_list_id: "list-1", shopping_list: { id: "list-1", name: "October menu" },
    supplier_id: null, supplier: null, po_number: "PO-001", display_name: "PO — October menu", or_number: null, order_date: "2026-10-04",
    received_date: null, total_amount: "642.50", current_total_amount: 700, actual_budget_per_head_per_day: null, status: "ordered",
    lifecycle_status: "open_execution", procurement_track: "food", converted_at: null, completed_at: null,
    archived_at: null, notes: null,
    vendor_groups: [{ id: "vendor-group-1", supplier_id: null, supplier: null, or_number: null, or_number_display: "—",
      status: "pending", can_change_vendor: false, vendor_change_blocker: null, total_amount: "700.00", received_at: null,
      stocked_at: null, items: [{ id: 1, vendor_group_id: 1, fs_item_id: 42, description: "Rice", qty: "12.3456", unit: "kg",
        unit_price: "50.1234", total_value: "618.75", purchase_qty: "12.3456", purchase_unit: "kg", purchase_price: "50.1234",
        actual_qty: "12.3456", actual_unit: "kg", actual_unit_price: "52.3456", actual_total: 646.30 }], attachments: [] }],
    ...overrides,
  };
}

function makeList(overrides: Partial<ShoppingList> = {}): ShoppingList {
  return {
    id: "list-1", name: "Draft list", list_date: "2026-10-04", list_type: "manual", procurement_track: "food",
    status: "draft", coverage_status: "full", uncovered_dates: [], days_span: null, period_start: null, period_end: null,
    total_served_population: null, estimate_population: null, estimate_population_updated_at: null,
    release_readiness: { ready: false, blockers: [{ code: "items", message: "Include at least one shopping-list item." }] }, items: [],
    ...overrides,
  };
}

const rice: CatalogItem = {
  id: "rice-public-uuid", name: "Rice", kind: "ingredient", category: "Dry Goods", base_unit: "kg",
  purchase_unit: "sack", purchase_price: "2500", units_per_purchase: "50", unit_cost: 50,
  default_supplier_id: null, vendor: null, include_in_generated_lists: true,
};
function setInputValue(input: HTMLInputElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")?.set;
  setter?.call(input, value);
  input.dispatchEvent(new Event("input", { bubbles: true }));
  input.dispatchEvent(new Event("change", { bubbles: true }));
}

function setSelectValue(select: HTMLSelectElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value")?.set;
  setter?.call(select, value);
  select.dispatchEvent(new Event("change", { bubbles: true }));
}

describe("Food Service procurement display", () => {
  let container: HTMLDivElement;
  let root: Root;
  let clipboardWrite: ReturnType<typeof vi.fn>;
  let originalClipboard: Clipboard | undefined;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
    mockListPurchaseOrders.mockResolvedValue({ data: [makePo()], meta });
    mockGetPurchaseOrder.mockResolvedValue(makePo());
    mockListShoppingLists.mockResolvedValue({ data: [makeList()], meta });
    mockGetShoppingList.mockResolvedValue(makeList());
    mockCreateShoppingList.mockResolvedValue(makeList());
    mockGenerateByDates.mockResolvedValue(makeList({ id: "generated-list", name: "Weekly menu", list_type: "suggested" }));
    mockSearchCatalog.mockResolvedValue([rice]);
    mockUpdateShoppingList.mockResolvedValue(makeList({ name: "Renamed list" }));
    mockUpdateVendorGroup.mockResolvedValue(makePo());
    originalClipboard = navigator.clipboard;
    clipboardWrite = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText: clipboardWrite } });
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
    Object.defineProperty(navigator, "clipboard", { configurable: true, value: originalClipboard });
    vi.clearAllMocks();
  });

  async function renderPage() {
    await act(async () => { root.render(<ProcurementPage />); await Promise.resolve(); });
  }

  test("purchase quantity and editable total synchronize before leaving either field", async () => {
    await openList(makeList({ items: [{ id: 'item-1', fs_item_id: 1, fs_item_uuid: 'rice-public-uuid', ingredient_name: 'Rice', source: 'generated', item_type: 'ingredient', qty: '10', unit: 'kg', purchase_qty: '10', purchase_unit: 'kg', purchase_price: '5', unit_price: '5', total: '50', supplier_id: null, included_in_po: true, exclusion_note: null }] }));
    const qty = container.querySelector<HTMLInputElement>('[aria-label="Purchase quantity for Rice"]');
    expect(qty).not.toBeNull();
    const table = qty!.closest('table')!;
    expect(table.className).toContain('min-w-[1100px]');
    expect(table.parentElement?.className).toContain('overflow-x-auto');
    await act(async () => { setInputValue(qty!, '20'); });
    const total = container.querySelector<HTMLInputElement>('[aria-label="Total for Rice"]');
    expect(Number(total?.value)).toBe(100);
    await act(async () => { setInputValue(total!, '25'); });
    expect(Number(qty?.value)).toBe(5);
    const price = container.querySelector<HTMLInputElement>('[aria-label="Unit cost for Rice"]')!;
    await act(async () => { setInputValue(price, '3'); });
    await act(async () => { setInputValue(total!, '100'); });
    expect(qty?.value).toBe('33.33');
    expect(Number(total?.value)).toBe(100);
    expect(container.querySelector('[aria-label="Quantity mode for Rice"]')).toBeNull();
    expect(container.querySelector('[aria-label="Remove Rice"]')).toBeNull();
    const save = Array.from(container.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Save Shopping List')!;
    await act(async () => { save.click(); });
    const savedQuantity = mockUpdateShoppingList.mock.calls[0][1].items![0].purchase_qty;
    expect(savedQuantity).toBeCloseTo(33.333333333333, 10);
    expect(savedQuantity * 3).toBeCloseTo(100, 10);
  });

  test('restoring the original quantity leaves a saved shopping list clean', async () => {
    await openList(makeList({ items: [{ id: 'item-1', fs_item_id: 1, fs_item_uuid: 'rice-public-uuid', ingredient_name: 'Rice', source: 'generated', item_type: 'ingredient', qty: '340.000000', unit: 'kg', purchase_qty: '340.000000', purchase_unit: 'kg', purchase_price: '5.000000', unit_price: '5.000000', total: '1700.00', quantity_mode: 'calculated', supplier_id: null, included_in_po: true, exclusion_note: null }] }));
    const qty = container.querySelector<HTMLInputElement>('[aria-label="Purchase quantity for Rice"]')!;
    await act(async () => { setInputValue(qty, '341'); });
    await act(async () => { setInputValue(qty, '340'); });
    const back = container.querySelector<HTMLButtonElement>('button[aria-label="Back to Shopping Lists"]')!;
    expect(back).not.toBeNull();
    await act(async () => back.click());
    expect(container.querySelector('[role="dialog"][aria-label="Unsaved Shopping List"]')).toBeNull();
    expect(container.textContent).toContain('Procurement');
  });

  test('reset restores generated prices and purchases scaled to current servings, keeping manual items and exclusions', async () => {
    const generated = { id: 'item-1', fs_item_id: 1, fs_item_uuid: 'rice-public-uuid', ingredient_name: 'Rice', source: 'generated' as const, item_type: 'ingredient' as const, qty: '10', unit: 'kg', purchase_qty: '50', purchase_unit: 'kg', purchase_price: '99', unit_price: '5', total: '4950', supplier_id: null, included_in_po: false, exclusion_note: 'Already available', generation_baseline: { estimate_population: 10, qty: 10, unit: 'kg', unit_price: 5, purchase_qty: 10, purchase_unit: 'kg', purchase_price: 5, supplier_id: null, scaled_quantity: 10, scaled_unit: 'kg' } };
    await openList(makeList({ list_type: 'suggested', period_start: '2026-10-19', period_end: '2026-10-19', days_span: 1, estimate_population: 20, items: [generated, { ...generated, id: 'manual-1', ingredient_name: 'Extra rice', source: 'manual', generation_baseline: null }] }));
    expect(container.querySelector('[aria-label="Remove Rice"]')).toBeNull();
    expect(container.querySelector('[aria-label="Remove Extra rice"]')).not.toBeNull();
    const reset = Array.from(container.querySelectorAll<HTMLButtonElement>('button')).find((button) => button.textContent === 'Reset generated items');
    expect(reset).toBeDefined();
    await act(async () => reset!.click());
    expect(container.querySelector<HTMLInputElement>('[aria-label="Estimated servings"]')?.value).toBe('20');
    expect(Number(container.querySelector<HTMLInputElement>('[aria-label="Purchase quantity for Rice"]')?.value)).toBe(20);
    expect(Number(container.querySelector<HTMLInputElement>('[aria-label="Unit cost for Rice"]')?.value)).toBe(5);
    expect(Number(container.querySelector<HTMLInputElement>('[aria-label="Total for Rice"]')?.value)).toBe(100);
    expect(Number(container.querySelector<HTMLInputElement>('[aria-label="Purchase quantity for Extra rice"]')?.value)).toBe(50);
    const servings = container.querySelector<HTMLInputElement>('[aria-label="Estimated servings"]')!;
    await act(async () => setInputValue(servings, ''));
    await act(async () => setInputValue(servings, '40'));
    expect(Number(container.querySelector<HTMLInputElement>('[aria-label="Purchase quantity for Rice"]')?.value)).toBe(40);
  });

  async function openPurchaseOrder(overrides: Partial<PurchaseOrder> = {}) {
    const po = makePo(overrides);
    mockListPurchaseOrders.mockResolvedValue({ data: [po], meta });
    mockGetPurchaseOrder.mockResolvedValue(po);
    const tab = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Purchase Orders");
    await act(async () => { tab?.click(); });
    const edit = container.querySelector<HTMLButtonElement>('button[aria-label="Edit PO — October menu"]');
    await act(async () => { edit?.click(); await Promise.resolve(); await Promise.resolve(); });
  }

  async function chooseSuggestedStart() {
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Start date"]')!.click());
    await act(async () => {
      const year = document.querySelector<HTMLSelectElement>('[aria-label="Calendar year"]')!;
      year.value = '2026'; year.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => {
      const month = document.querySelector<HTMLSelectElement>('[aria-label="Calendar month"]')!;
      month.value = '10'; month.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => document.querySelector<HTMLButtonElement>('[aria-label="2026-10-04"]')!.click());
  }

  async function openList(list = makeList()) {
    mockListShoppingLists.mockResolvedValue({ data: [list], meta });
    mockGetShoppingList.mockResolvedValue(list);
    await renderPage();
    const open = container.querySelector<HTMLButtonElement>(`button[aria-label="Open list: ${list.name}"]`);
    await act(async () => { open?.click(); await Promise.resolve(); await Promise.resolve(); });
  }

  test("uses the row pencil to open the plain-text list name", async () => {
    await renderPage();
    const name = Array.from(container.querySelectorAll("td span"))
      .find((candidate) => candidate.textContent?.trim() === "Draft list");
    expect(name?.closest("td")?.querySelector("button")).toBeNull();
    expect(container.querySelector('button[aria-label="Open list: Draft list"]')).not.toBeNull();
    expect(Array.from(container.querySelectorAll("button"), (button) => button.textContent?.trim()).filter((text) => text === "Open")).toHaveLength(0);
  });

  test("Manual Food List waits for a selected procurement date", async () => {
    const manual = makeList({ name: "Manual Food List" });
    mockCreateShoppingList.mockResolvedValue(manual);
    mockGetShoppingList.mockResolvedValue(manual);
    await renderPage();
    const action = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim().toLowerCase() === "manual food list");
    await act(async () => { action?.click(); });
    expect(mockCreateShoppingList).not.toHaveBeenCalled();

    await act(async () => {
      setSelectValue(container.querySelector<HTMLSelectElement>('select[aria-label="Procurement date month"]')!, "10");
      setSelectValue(container.querySelector<HTMLSelectElement>('select[aria-label="Procurement date day"]')!, "4");
      setSelectValue(container.querySelector<HTMLSelectElement>('select[aria-label="Procurement date year"]')!, "2026");
    });
    const create = container.querySelector<HTMLButtonElement>('button[aria-label="Create manual shopping list"]');
    await act(async () => { create?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(mockCreateShoppingList).toHaveBeenCalledWith(expect.objectContaining({
      name: "Manual Food List", list_date: "2026-10-04", list_type: "manual", procurement_track: "food", status: "draft",
    }));
    expect(container.querySelector("h3")?.textContent).toBe("Manual Food List");
  });

  test("Supplies list requires a user-selected procurement date", async () => {
    const supplies = makeList({ name: "Cleaning supplies", procurement_track: "supplies" });
    mockCreateShoppingList.mockResolvedValue(supplies);
    mockGetShoppingList.mockResolvedValue(supplies);
    await renderPage();
    const tab = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Supplies Lists");
    await act(async () => { tab?.click(); });
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Supplies list name"]');
    await act(async () => { if (name) setInputValue(name, "Cleaning supplies"); });
    const open = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("New supplies list"));
    await act(async () => { open?.click(); });
    expect(mockCreateShoppingList).not.toHaveBeenCalled();
    await act(async () => {
      setSelectValue(container.querySelector<HTMLSelectElement>('select[aria-label="Procurement date month"]')!, "10");
      setSelectValue(container.querySelector<HTMLSelectElement>('select[aria-label="Procurement date day"]')!, "4");
      setSelectValue(container.querySelector<HTMLSelectElement>('select[aria-label="Procurement date year"]')!, "2026");
    });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Create manual shopping list"]')?.click(); await Promise.resolve(); });
    expect(mockCreateShoppingList).toHaveBeenCalledWith(expect.objectContaining({
      name: "Cleaning supplies", list_date: "2026-10-04", procurement_track: "supplies",
    }));
  });

  test("requires a name before generating a suggested shopping list", async () => {
    await renderPage();
    const suggest = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Suggest from Menu");
    await act(async () => { suggest?.click(); });
    await chooseSuggestedStart();
    const estimate = container.querySelector<HTMLInputElement>('input[type="number"]');
    await act(async () => { if (estimate) setInputValue(estimate, "100"); });
    const generate = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Generate");
    expect(generate?.disabled).toBe(true);
    expect(mockGenerateByDates).not.toHaveBeenCalled();
  });

  test("explains suggested-list draft and purchase-order steps accessibly", async () => {
    await renderPage();
    const suggest = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Suggest from Menu");
    await act(async () => { suggest?.click(); });
    await chooseSuggestedStart();
    const hint = container.querySelector<HTMLButtonElement>('button[aria-label="About suggested lists"]');
    expect(hint).not.toBeNull();
    await act(async () => { await userEvent.setup().hover(hint!); });
    await vi.waitFor(() => expect(document.body.textContent).toContain("Review the suggested quantities before saving. Turn Into PO is a separate step and locks the list."));
  });

  test("warns that Turn Into PO locks the Shopping List and waits for confirmation", async () => {
    const readyList = makeList({
      release_readiness: { ready: true, blockers: [] },
    });
    mockApproveShoppingList.mockResolvedValue({ purchase_order_id: "po-1", purchase_order_ids: ["po-1"] });
    await openList(readyList);

    const release = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.trim() === "Turn Into PO");
    expect(release?.disabled).toBe(false);
    await act(async () => { release?.click(); });

    const warning = container.querySelector<HTMLElement>('[role="dialog"][aria-label="Turn Into PO warning"]');
    expect(warning?.textContent).toContain("This will lock the Shopping List");
    expect(warning?.textContent).toContain("The Shopping List will become read-only.");
    expect(warning?.textContent).toContain("Are you sure you want to turn it into a PO and lock the list?");
    expect(mockApproveShoppingList).not.toHaveBeenCalled();

    const cancel = Array.from(warning?.querySelectorAll<HTMLButtonElement>("button") ?? [])
      .find((button) => button.textContent?.trim() === "Cancel");
    await act(async () => { cancel?.click(); });
    expect(container.querySelector('[role="dialog"][aria-label="Turn Into PO warning"]')).toBeNull();
    expect(mockApproveShoppingList).not.toHaveBeenCalled();

    await act(async () => { release?.click(); });
    const confirm = Array.from(container.querySelectorAll<HTMLButtonElement>('[role="dialog"] button'))
      .find((button) => button.textContent?.trim() === "Turn Into PO and lock list");
    await act(async () => { confirm?.click(); await Promise.resolve(); });
    expect(mockApproveShoppingList).toHaveBeenCalledExactlyOnceWith("list-1");
  });

  test("saves the suggested-list name and offers in-detail rename afterward", async () => {
    const generated = makeList({ id: "generated-list", name: "Weekly menu", list_type: "suggested", estimate_population: 100, period_start: "2026-10-04", period_end: "2026-10-05", days_span: 2 });
    mockGenerateByDates.mockResolvedValue(generated);
    mockGetShoppingList.mockResolvedValue(generated);
    await renderPage();
    const suggest = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Suggest from Menu");
    await act(async () => { suggest?.click(); });
    await chooseSuggestedStart();
    const estimate = container.querySelector<HTMLInputElement>('input[type="number"]');
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Shopping list name"]');
    await act(async () => {
      if (estimate) setInputValue(estimate, "100");
      if (name) setInputValue(name, "Weekly menu");
    });
    const generate = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Generate");
    await act(async () => { generate?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(mockGenerateByDates).toHaveBeenCalledWith(expect.any(String), expect.any(String), 100, "Weekly menu", { preview: true });
    expect(container.querySelector("h3")?.textContent).toBe("Weekly menu");
    expect(container.querySelector('button[aria-label="Rename Weekly menu"]')).not.toBeNull();
    expect(mockGenerateByDates).toHaveBeenCalledTimes(1);
    const saveList = Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { saveList?.click(); });
    expect(container.querySelector('[role="dialog"]')?.textContent).toContain("Saving does not create a purchase order");
    const confirmSave = Array.from(container.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')).find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { confirmSave?.click(); await Promise.resolve(); });
    expect(mockGenerateByDates).toHaveBeenCalledTimes(2);
  });

  test("renames a saved list from the detail-title pencil", async () => {
    await openList();
    const rename = container.querySelector<HTMLButtonElement>('button[aria-label="Rename Draft list"]');
    await act(async () => { rename?.click(); });
    const input = container.querySelector<HTMLInputElement>('input[aria-label="Shopping list name"]');
    await act(async () => { if (input) setInputValue(input, "Renamed list"); });
    const apply = container.querySelector<HTMLButtonElement>('button[aria-label="Apply name"]');
    await act(async () => { apply?.click(); await Promise.resolve(); });

    expect(mockUpdateShoppingList).not.toHaveBeenCalled();
    expect(container.querySelector("h3")?.textContent).toBe("Renamed list");
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(mockUpdateShoppingList).toHaveBeenCalledWith("list-1", { name: "Renamed list", items: [] });
  });

  test("saves one edited Estimated servings value on an active Suggested list", async () => {
    const suggested = makeList({
      list_type: "suggested", period_start: "2026-10-04", period_end: "2026-10-05",
      days_span: 2, estimate_population: 100,
    });
    mockUpdateShoppingList.mockResolvedValue(makeList({ ...suggested, estimate_population: 120 }));
    await openList(suggested);

    const estimate = container.querySelector<HTMLInputElement>('input[aria-label="Estimated servings"]');
    expect(estimate?.value).toBe("100");
    expect(container.querySelector('button[aria-label="About Estimated servings"]')).not.toBeNull();
    await act(async () => { if (estimate) setInputValue(estimate, "120"); });
    expect(mockUpdateShoppingList).not.toHaveBeenCalled();

    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(mockUpdateShoppingList).toHaveBeenCalledWith("list-1", {
      name: "Draft list", estimate_population: 120, items: [],
    });
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Estimated servings"]')?.value).toBe("120");
  });

  test("does not offer Estimated servings edits after Turn Into PO", async () => {
    await openList(makeList({
      list_type: "suggested", period_start: "2026-10-04", period_end: "2026-10-05",
      days_span: 2, estimate_population: 100, status: "converted",
    }));
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Estimated servings"]')).toBeNull();
    expect(container.textContent).toContain("100");
  });

  test("Back discards only unsaved edits to an existing Shopping List", async () => {
    await openList();
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Rename Draft list"]')?.click(); });
    const input = container.querySelector<HTMLInputElement>('input[aria-label="Shopping list name"]');
    await act(async () => { if (input) setInputValue(input, "Temporary name"); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Apply name"]')?.click(); });
    await act(async () => { container.querySelector<HTMLButtonElement>('button[aria-label="Back to Shopping Lists"]')?.click(); });
    expect(container.querySelector('[role="dialog"]')?.textContent).toContain("Unsaved Shopping List");
    await act(async () => { Array.from(container.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')).find((button) => button.textContent?.trim() === "Discard")?.click(); });
    expect(mockUpdateShoppingList).not.toHaveBeenCalled();
    expect(container.textContent).toContain("Draft list");
  });

  test("adds a selected Inventory item through the existing public-id flow", async () => {
    mockSearchCatalog.mockResolvedValue([{ ...rice, default_supplier_id: 'inventory-vendor-uuid', vendor: 'Inventory vendor' }]);
    await openList();
    const search = container.querySelector<HTMLInputElement>('#shopping-list-item-search');
    await act(async () => { if (search) setInputValue(search, "Rice"); await Promise.resolve(); });
    const option = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Rice"));
    await act(async () => { option?.click(); });
    const add = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Add");
    await act(async () => { add?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(container.textContent).toContain("Rice");
    expect(mockUpdateShoppingList).not.toHaveBeenCalled();
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(mockUpdateShoppingList).toHaveBeenCalledWith("list-1", expect.objectContaining({ items: [expect.objectContaining({ fs_item_uuid: "rice-public-uuid", source: "manual", supplier_id: 'inventory-vendor-uuid' })] }));
    expect(container.querySelector('[role="alert"]')).toBeNull();
  });

  test("empty Shopping List search suggests Non-Auto Grocery items first", async () => {
    await openList();
    const search = container.querySelector<HTMLInputElement>('#shopping-list-item-search');
    await act(async () => { search?.focus(); await Promise.resolve(); });
    expect(mockSearchCatalog).toHaveBeenCalledWith("", "ingredient", 5, "non_auto");
  });

  test("adds an Inventory item to a Suggested draft before its first Save", async () => {
    const draft = makeList({
      id: "preview", name: "Weekly menu", list_type: "suggested",
      period_start: "2026-10-04", period_end: "2026-10-04", estimate_population: 100,
    });
    mockGenerateByDates.mockResolvedValueOnce(draft).mockResolvedValueOnce(makeList({ ...draft, id: "saved-list" }));
    await renderPage();
    const suggest = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.trim() === "Suggest from Menu");
    await act(async () => { suggest?.click(); });
    await chooseSuggestedStart();
    const estimate = container.querySelector<HTMLInputElement>('input[type="number"]');
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Shopping list name"]');
    await act(async () => {
      if (estimate) setInputValue(estimate, "100");
      if (name) setInputValue(name, "Weekly menu");
    });
    const generate = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.trim() === "Generate");
    await act(async () => { generate?.click(); await Promise.resolve(); });
    const search = container.querySelector<HTMLInputElement>('#shopping-list-item-search');
    await act(async () => { if (search) setInputValue(search, "Rice"); await Promise.resolve(); });
    const option = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.includes("Rice"));
    await act(async () => { option?.click(); });
    const add = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.trim() === "Add");
    await act(async () => { add?.click(); });
    expect(mockGenerateByDates).toHaveBeenCalledTimes(1);
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { save?.click(); });
    const confirm = Array.from(container.querySelectorAll<HTMLButtonElement>('[role="dialog"] button'))
      .find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { confirm?.click(); await Promise.resolve(); });
    expect(mockGenerateByDates).toHaveBeenLastCalledWith("2026-10-04", "2026-10-04", 100, "Weekly menu", {
      items: [expect.objectContaining({ fs_item_uuid: "rice-public-uuid", source: "manual" })],
    });
  });

  test("shows a recoverable error when the Inventory item cannot be added", async () => {
    mockUpdateShoppingList.mockRejectedValue(new Error("Inventory item could not be added."));
    await openList();
    const search = container.querySelector<HTMLInputElement>('#shopping-list-item-search');
    await act(async () => { if (search) setInputValue(search, "Rice"); await Promise.resolve(); });
    const option = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Rice"));
    await act(async () => { option?.click(); });
    const add = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Add");
    await act(async () => { add?.click(); await Promise.resolve(); await Promise.resolve(); });
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button")).find((button) => button.textContent?.trim() === "Save Shopping List");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(container.querySelector('[role="alert"]')?.textContent).toContain("Inventory item could not be added.");
  });

  test("keeps Shopping List copy content compact and collapsed until opened", async () => {
    await act(async () => { root.render(<ShoppingListCopyCard items={[{ id: "rice", ingredient_name: "Rice", qty: "2.5000", unit: "kg" }]} />); });
    expect(container.querySelector('button[aria-label="Show shopping list"]')?.getAttribute("data-state")).toBe("closed");
    expect(container.textContent).not.toContain("Rice");
    const show = container.querySelector<HTMLButtonElement>('button[aria-label="Show shopping list"]');
    await act(async () => { show?.click(); });
    expect(container.textContent).toContain("Rice");
    expect(container.textContent).toContain("2.5 kg");
  });

  test("copies each Shopping List item with quantity and unit", async () => {
    await act(async () => { root.render(<ShoppingListCopyCard items={[
      { id: "rice", ingredient_name: "Rice", qty: "2.5000", unit: "kg" },
      { id: "oil", ingredient_name: "Oil", qty: "1", unit: "L" },
    ]} />); });
    const copy = container.querySelector<HTMLButtonElement>('button[aria-label="Copy shopping list"]');
    await act(async () => { copy?.click(); await Promise.resolve(); });
    expect(clipboardWrite).toHaveBeenCalledWith("Rice\t2.5 kg\nOil\t1 L");
  });

  test("shows friendly PO status and explains why a completed PO cannot be deleted", async () => {
    const completed = makePo({ lifecycle_status: "completed" });
    mockListPurchaseOrders.mockResolvedValue({ data: [completed], meta });
    await renderPage();
    const tab = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Purchase Orders");
    await act(async () => { tab?.click(); });

    expect(container.textContent).toContain("Status");
    expect(container.textContent).not.toContain("Lifecycle");
    expect(container.textContent).toContain("Completed");
    expect(container.textContent).not.toContain("open_execution");
    expect(container.querySelector('button[aria-label="Edit PO — October menu"]')).not.toBeNull();
    const deleteButton = container.querySelector<HTMLButtonElement>('button[aria-label="Delete PO — October menu"]');
    expect(deleteButton).not.toBeNull();
    await act(async () => { deleteButton?.click(); });
    const dialog = container.querySelector('[role="dialog"]');
    expect(dialog?.textContent).toContain("Purchase order can't be deleted");
    expect(dialog?.textContent).toContain("Completed POs can't be deleted.");
    expect(dialog?.textContent).toContain("This PO is kept in the procurement record.");
  });

  test("saves changed OR and actual PO values through one Save action", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });

    const row = container.querySelector("tbody tr");
    const inputs = row?.querySelectorAll<HTMLInputElement>('input[type="number"]');
    expect(inputs?.[0]?.value).toBe("12.35");
    expect(inputs?.[1]?.value).toBe("52.3456");
    const orInput = Array.from(container.querySelectorAll<HTMLLabelElement>("label"))
      .find((label) => label.textContent?.includes("OR number (optional)"))?.querySelector("input") ?? null;
    await act(async () => {
      if (orInput) setInputValue(orInput, "OR-9001");
      if (inputs?.[0]) setInputValue(inputs[0], "13.5");
    });
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Save");
    await act(async () => { save?.click(); await Promise.resolve(); await Promise.resolve(); });
    expect(mockUpdateVendorGroup).toHaveBeenCalledWith("vendor-group-1", {
      or_number: "OR-9001",
      use_calculated_total: true,
      items: [{ id: 1, actual_qty: 13.5, actual_unit_price: 52.3456 }],
    });
  });

  test("receipt total starts automatic, freezes on edit and resumes live totals when checked", async () => {
    await renderPage();
    await openPurchaseOrder();
    const open = Array.from(container.querySelectorAll<HTMLButtonElement>('button')).find((button) => button.textContent?.trim() === 'Open');
    await act(async () => { open?.click(); });
    const checkbox = () => container.querySelector<HTMLButtonElement>('[role="checkbox"][aria-label="Use calculated total"]')!;
    const receipt = () => container.querySelector<HTMLInputElement>('[aria-label="Actual receipt total"]')!;
    expect(checkbox()?.getAttribute('aria-checked')).toBe('true');
    expect(receipt()?.value).toBe((12.3456 * 52.3456).toFixed(2));
    const quantity = container.querySelector<HTMLInputElement>('[aria-label="Actual quantity for Rice"]')!;
    await act(async () => { setInputValue(receipt(), '650'); });
    expect(checkbox().getAttribute('aria-checked')).toBe('false');
    expect(quantity.value).toBe('12.35');
    await act(async () => { setInputValue(quantity, '14'); });
    expect(receipt().value).toBe('650');
    await act(async () => { checkbox().click(); });
    expect(checkbox().getAttribute('aria-checked')).toBe('true');
    expect(receipt().value).toBe((14 * 52.3456).toFixed(2));
    await act(async () => { setInputValue(quantity, '15'); });
    expect(receipt().value).toBe((15 * 52.3456).toFixed(2));
  });

  test("saves manual receipt amount independently of item quantities and explains cash rounding", async () => {
    await renderPage();
    await openPurchaseOrder();
    const open = Array.from(container.querySelectorAll<HTMLButtonElement>('button')).find((button) => button.textContent?.trim() === 'Open');
    await act(async () => { open?.click(); });
    const receipt = container.querySelector<HTMLInputElement>('[aria-label="Actual receipt total"]')!;
    expect(receipt).not.toBeNull();
    await act(async () => { setInputValue(receipt, '650'); });
    const help = container.querySelector<HTMLButtonElement>('[aria-label="About actual receipt total"]');
    await act(async () => { help?.click(); });
    expect(document.body.textContent).toContain('cash rounding');
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>('button')).find((button) => button.textContent?.trim() === 'Save');
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(mockUpdateVendorGroup).toHaveBeenCalledWith('vendor-group-1', expect.objectContaining({
      use_calculated_total: false, total_amount: 650,
    }));
    expect(mockUpdateVendorGroup.mock.calls[0][1]).not.toHaveProperty('items');
  });

  test("asks before leaving dirty vendor details and skips prompt when unchanged", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });

    const orInput = Array.from(container.querySelectorAll<HTMLLabelElement>("label"))
      .find((label) => label.textContent?.includes("OR number (optional)"))?.querySelector("input") ?? null;
    await act(async () => { if (orInput) setInputValue(orInput, "OR-9002"); });
    const back = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Procurement");
    await act(async () => { back?.click(); });
    const dialog = container.querySelector('[role="dialog"][aria-label="Unsaved vendor changes"]');
    expect(dialog?.textContent).toContain("Save your changes before leaving?");
    expect(dialog?.textContent).toContain("Discard");
    expect(dialog?.textContent).toContain("Save");
  });

  test("leaves unchanged vendor details without a prompt", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });
    const back = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Procurement");
    await act(async () => { back?.click(); });
    expect(container.querySelector('[role="dialog"][aria-label="Unsaved vendor changes"]')).toBeNull();
  });

  test("prompts before same-app page navigation when vendor details changed", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });
    const orInput = container.querySelector<HTMLInputElement>('input[aria-label="OR number (optional)"]');
    await act(async () => { if (orInput) setInputValue(orInput, "OR-9003"); });

    const link = document.createElement("a");
    link.href = "/dashboard";
    link.textContent = "Dashboard";
    container.append(link);
    const click = new MouseEvent("click", { bubbles: true, cancelable: true, button: 0 });
    await act(async () => { link.dispatchEvent(click); });
    expect(click.defaultPrevented).toBe(true);
    expect(container.querySelector('[role="dialog"][aria-label="Unsaved vendor changes"]')).not.toBeNull();
  });

  test("editing one item Actual Total derives quantity through the PO update", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });
    const total = container.querySelector<HTMLInputElement>('input[aria-label="Actual Total for Rice"]');
    await act(async () => { if (total) setInputValue(total, "550"); });
    expect(container.querySelector<HTMLInputElement>('[aria-label="Actual quantity for Rice"]')?.value).toBe('10.51');
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Save");
    await act(async () => { save?.click(); await Promise.resolve(); });
    expect(mockUpdateVendorGroup).toHaveBeenCalledWith("vendor-group-1", {
      or_number: null,
      items: [{ id: 1, receipt_total: 550, actual_unit_price: 52.3456 }],
      use_calculated_total: true,
    });
  });

  test("shows a readable PO title and current vendor total without a disclosure", async () => {
    await renderPage();
    await openPurchaseOrder();
    expect(container.querySelector("h3")?.textContent).toContain("PO — October menu");
    expect(container.textContent).not.toContain("PO-001");
    expect(container.querySelector('button[aria-label="Show planned total"]')).toBeNull();
    const currentTotalContent = container.querySelector('[data-testid="po-current-total"]');
    expect(currentTotalContent).not.toBeNull();
    expect(currentTotalContent?.textContent).toContain("Current total");
    expect(currentTotalContent?.textContent).toContain("₱700.00");
    expect(currentTotalContent?.textContent).not.toContain("₱642.50");
  });

  test("shows editable actual values and keeps the Shopping List unit locked", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });

    expect(container.textContent).toContain("Actual Quantity");
    expect(container.textContent).toContain("Actual Cost/unit");
    expect(container.textContent).toContain("Actual Total");
    expect(container.textContent).not.toContain("Reviewed");
    expect(container.textContent).not.toContain("Not reviewed");
    expect(container.querySelector<HTMLDetailsElement>("tbody tr details")?.open).toBe(false);
    expect(container.querySelector("tbody tr details summary")?.textContent).toBe("Calculation details");
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Actual Total for Rice"]')).not.toBeNull();
    expect(container.textContent).toContain("Actual receipt total");
    const unit = Array.from(container.querySelectorAll<HTMLTableCellElement>("tbody td"))
      .find((cell) => cell.textContent?.trim() === "kg");
    expect(unit?.querySelector("input, select")).toBeNull();
  });

  test("hides the Before PO release blocker panel while preserving release readiness", async () => {
    await renderPage();
    const openList = container.querySelector<HTMLButtonElement>('button[aria-label="Open list: Draft list"]');
    await act(async () => { openList?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(container.textContent).not.toContain("Before PO release");
    expect(container.textContent).not.toContain("Include at least one shopping-list item.");
    const release = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Turn Into PO"));
    expect(release?.disabled).toBe(true);
  });
});
