// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import { listCatalog, type CatalogItem } from "@/services/fsCatalogService";
import InventoryCatalogPage from "./page";

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
  vendor_locked: false, include_in_generated_lists: false,
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
});
