// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import {
  addListItem, createShoppingList, generateByDates, getPurchaseOrder, getShoppingList,
  listPurchaseOrders, listShoppingLists, updateShoppingList, updateVendorGroup,
  type PurchaseOrder, type ShoppingList, type ShoppingListItem,
} from "@/services/procurementService";
import { searchCatalog, type CatalogItem } from "@/services/fsCatalogService";
import { ShoppingListCopyCard } from "@/components/foodservice/ShoppingListCopyCard";
import ProcurementPage from "./page";

vi.mock("next/navigation", () => ({ useSearchParams: () => new URLSearchParams() }));
vi.mock("@/contexts/AuthContext", () => ({ useAuth: () => ({ user: { role: "RND" } }) }));
vi.mock("@/hooks/useDebouncedValue", () => ({ useDebouncedValue: (value: string) => value }));
vi.mock("@/services/procurementService", async (importOriginal) => ({
  ...await importOriginal<typeof import("@/services/procurementService")>(),
  addListItem: vi.fn(),
  createShoppingList: vi.fn(),
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

const mockAddListItem = vi.mocked(addListItem);
const mockCreateShoppingList = vi.mocked(createShoppingList);
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
    supplier_id: null, supplier: null, po_number: "PO-001", or_number: null, order_date: "2026-10-04",
    received_date: null, total_amount: "642.50", actual_budget_per_head_per_day: null, status: "ordered",
    lifecycle_status: "open_execution", procurement_track: "food", converted_at: null, completed_at: null,
    archived_at: null, notes: null,
    vendor_groups: [{ id: "vendor-group-1", supplier_id: null, supplier: null, or_number: null, or_number_display: "—",
      status: "pending", can_change_vendor: false, vendor_change_blocker: null, total_amount: "642.50", received_at: null,
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
  default_supplier_id: null, vendor: null, vendor_locked: false, include_in_generated_lists: true,
};
const addedRice: ShoppingListItem = {
  id: "item-1", fs_item_id: 42, ingredient_name: "Rice", qty: "1", unit: "kg", supplier_id: null,
  item_type: "ingredient", unit_price: "50", total: "50", purchase_qty: null, purchase_unit: null,
  purchase_price: null, source: "manual", included_in_po: true, exclusion_note: null,
};

function setInputValue(input: HTMLInputElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value")?.set;
  setter?.call(input, value);
  input.dispatchEvent(new Event("input", { bubbles: true }));
  input.dispatchEvent(new Event("change", { bubbles: true }));
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
    mockAddListItem.mockResolvedValue(addedRice);
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

  async function openPurchaseOrder(overrides: Partial<PurchaseOrder> = {}) {
    const po = makePo(overrides);
    mockListPurchaseOrders.mockResolvedValue({ data: [po], meta });
    mockGetPurchaseOrder.mockResolvedValue(po);
    const tab = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Purchase Orders");
    await act(async () => { tab?.click(); });
    const edit = container.querySelector<HTMLButtonElement>('button[aria-label="Edit PO-001"]');
    await act(async () => { edit?.click(); await Promise.resolve(); await Promise.resolve(); });
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

  test("Manual Food List creates and opens a draft", async () => {
    const manual = makeList({ name: "Manual Food List" });
    mockCreateShoppingList.mockResolvedValue(manual);
    mockGetShoppingList.mockResolvedValue(manual);
    await renderPage();
    const action = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim().toLowerCase() === "manual food list");
    await act(async () => { action?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(mockCreateShoppingList).toHaveBeenCalledWith(expect.objectContaining({
      name: "Manual Food List", list_type: "manual", procurement_track: "food", status: "draft",
    }));
    expect(container.querySelector("h3")?.textContent).toBe("Manual Food List");
  });

  test("requires a name before generating a suggested shopping list", async () => {
    await renderPage();
    const suggest = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Suggest from Menu");
    await act(async () => { suggest?.click(); });
    const estimate = container.querySelector<HTMLInputElement>('input[type="number"]');
    await act(async () => { if (estimate) setInputValue(estimate, "100"); });
    const generate = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Generate");
    expect(generate?.disabled).toBe(true);
    expect(mockGenerateByDates).not.toHaveBeenCalled();
  });

  test("saves the suggested-list name and offers in-detail rename afterward", async () => {
    const generated = makeList({ id: "generated-list", name: "Weekly menu", list_type: "suggested" });
    mockGenerateByDates.mockResolvedValue(generated);
    mockGetShoppingList.mockResolvedValue(generated);
    await renderPage();
    const suggest = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Suggest from Menu");
    await act(async () => { suggest?.click(); });
    const estimate = container.querySelector<HTMLInputElement>('input[type="number"]');
    const name = container.querySelector<HTMLInputElement>('input[aria-label="Shopping list name"]');
    await act(async () => {
      if (estimate) setInputValue(estimate, "100");
      if (name) setInputValue(name, "Weekly menu");
    });
    const generate = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Generate");
    await act(async () => { generate?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(mockGenerateByDates).toHaveBeenCalledWith(expect.any(String), expect.any(String), 100, "Weekly menu");
    expect(container.querySelector("h3")?.textContent).toBe("Weekly menu");
    expect(container.querySelector('button[aria-label="Rename Weekly menu"]')).not.toBeNull();
  });

  test("renames a saved list from the detail-title pencil", async () => {
    await openList();
    const rename = container.querySelector<HTMLButtonElement>('button[aria-label="Rename Draft list"]');
    await act(async () => { rename?.click(); });
    const input = container.querySelector<HTMLInputElement>('input[aria-label="Shopping list name"]');
    await act(async () => { if (input) setInputValue(input, "Renamed list"); });
    const save = container.querySelector<HTMLButtonElement>('button[aria-label="Save name"]');
    await act(async () => { save?.click(); await Promise.resolve(); });

    expect(mockUpdateShoppingList).toHaveBeenCalledWith("list-1", { name: "Renamed list" });
    expect(container.querySelector("h3")?.textContent).toBe("Renamed list");
  });

  test("adds a selected Inventory item through the existing public-id flow", async () => {
    await openList();
    const search = container.querySelector<HTMLInputElement>('#shopping-list-item-search');
    await act(async () => { if (search) setInputValue(search, "Rice"); await Promise.resolve(); });
    const option = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Rice"));
    await act(async () => { option?.click(); });
    const add = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Add");
    await act(async () => { add?.click(); await Promise.resolve(); await Promise.resolve(); });

    expect(mockAddListItem).toHaveBeenCalledWith("list-1", {
      fs_item_id: "rice-public-uuid", qty: 1, unit: "kg", unit_price: 50, supplier_id: null,
    });
    expect(container.querySelector('[role="alert"]')).toBeNull();
  });

  test("shows a recoverable error when the Inventory item cannot be added", async () => {
    mockAddListItem.mockRejectedValue(new Error("Inventory item could not be added."));
    await openList();
    const search = container.querySelector<HTMLInputElement>('#shopping-list-item-search');
    await act(async () => { if (search) setInputValue(search, "Rice"); await Promise.resolve(); });
    const option = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.includes("Rice"));
    await act(async () => { option?.click(); });
    const add = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Add");
    await act(async () => { add?.click(); await Promise.resolve(); await Promise.resolve(); });

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

  test("shows friendly PO status, uses a pencil action, and hides completed delete", async () => {
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
    expect(container.querySelector('button[aria-label="Edit PO-001"]')).not.toBeNull();
    expect(container.querySelector('button[aria-label="Delete PO-001"]')).toBeNull();
  });

  test("formats actual PO inputs but keeps unchanged quantity and cost precision on save", async () => {
    await renderPage();
    await openPurchaseOrder();
    const openGroup = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Open");
    await act(async () => { openGroup?.click(); });

    const row = container.querySelector("tbody tr");
    const inputs = row?.querySelectorAll<HTMLInputElement>('input[type="number"]');
    expect(inputs?.[0]?.value).toBe("12.35");
    expect(inputs?.[1]?.value).toBe("52.35");
    const save = Array.from(container.querySelectorAll<HTMLButtonElement>("button"))
      .find((candidate) => candidate.textContent?.trim() === "Save actual values");
    await act(async () => { save?.click(); await Promise.resolve(); await Promise.resolve(); });
    expect(mockUpdateVendorGroup).toHaveBeenCalledWith("vendor-group-1", {
      items: [{ id: 1, actual_qty: 12.3456, actual_unit_price: 52.3456 }],
    });
  });

  test("shows the planned PO total in a collapsed disclosure", async () => {
    await renderPage();
    await openPurchaseOrder();
    const showPlannedTotal = container.querySelector<HTMLButtonElement>('button[aria-label="Show planned total"]');
    expect(showPlannedTotal).not.toBeNull();
    expect(showPlannedTotal?.getAttribute("data-state")).toBe("closed");
    await act(async () => { showPlannedTotal?.click(); });

    const plannedTotalContent = container.querySelector('[data-testid="planned-total-content"]');
    expect(plannedTotalContent?.getAttribute("data-state")).toBe("open");
    expect(plannedTotalContent?.textContent).toContain("₱642.50");
  });

  test("uses Inventory cost/unit wording for actual PO cost and keeps the Shopping List unit locked", async () => {
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
    const calculationDetails = container.querySelector<HTMLDetailsElement>("tbody tr details");
    expect(calculationDetails?.open).toBe(false);
    expect(calculationDetails?.querySelector("summary")?.textContent).toBe("Calculation details");
    expect(calculationDetails?.textContent).toContain("Planned purchase:");
    expect(calculationDetails?.textContent).toContain("Actual purchased:");
    for (const removedField of ["Calculated need:", "Quantity difference:", "Cost difference:"]) {
      expect(calculationDetails?.textContent).not.toContain(removedField);
    }
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
      .find((candidate) => candidate.textContent?.includes("Create and release PO"));
    expect(release?.disabled).toBe(true);
  });
});
