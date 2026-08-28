import { beforeEach, describe, expect, test, vi } from "vitest";

import { apiFetch } from "@/lib/apiFetch";
import { listAllSuppliers } from "./supplierService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

describe("listAllSuppliers", () => {
  beforeEach(() => vi.resetAllMocks());

  test("collects every paginated supplier page for vendor dropdowns", async () => {
    vi.mocked(apiFetch)
      .mockResolvedValueOnce(new Response(JSON.stringify({
        data: [{ id: "supplier-1", name: "First" }],
        meta: { current_page: 1, per_page: 10, total: 2, last_page: 2 },
      })))
      .mockResolvedValueOnce(new Response(JSON.stringify({
        data: [{ id: "supplier-2", name: "Second" }],
        meta: { current_page: 2, per_page: 10, total: 2, last_page: 2 },
      })));

    const suppliers = await listAllSuppliers();

    expect(apiFetch).toHaveBeenNthCalledWith(1, "/api/fss/suppliers?page=1&per_page=10");
    expect(apiFetch).toHaveBeenNthCalledWith(2, "/api/fss/suppliers?page=2&per_page=10");
    expect(suppliers.map((supplier) => supplier.id)).toEqual(["supplier-1", "supplier-2"]);
  });
});
