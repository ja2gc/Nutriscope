import { apiFetch } from "@/lib/apiFetch";

export type FsItemKind = "ingredient" | "supply";

export interface CatalogItem {
  id: string;
  name: string;
  kind: FsItemKind;
  category: string | null;
  base_unit: string;
  purchase_unit: string | null;
  purchase_price: string | null;
  units_per_purchase: string | null;
  unit_cost: number;
  default_supplier_id: string | null;
  vendor: string | null;
  vendor_locked: boolean;
}

export interface CreateFsItemPayload {
  name: string;
  kind: FsItemKind;
  category?: string | null;
  base_unit: string;
  purchase_price: number;
  default_supplier_id?: string | null;
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

/** The reference catalog (ingredients + supplies). Optional kind filter. */
export async function listCatalog(kind?: FsItemKind): Promise<CatalogItem[]> {
  const qs = kind ? `?kind=${kind}` : "";
  return unwrap(await apiFetch(`/api/fss/fs-items/catalog${qs}`), "Failed to load catalog.");
}

export async function createFsItem(payload: CreateFsItemPayload): Promise<CatalogItem> {
  return unwrap(await apiFetch("/api/fss/fs-items", {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to create item.");
}

export async function updateFsItem(id: string, patch: Partial<CreateFsItemPayload>): Promise<CatalogItem> {
  return unwrap(await apiFetch(`/api/fss/fs-items/${id}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update item.");
}

export async function deleteFsItem(id: string): Promise<void> {
  const res = await apiFetch(`/api/fss/fs-items/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) {
    const data = await res.json().catch(() => ({}));
    throw new Error((data as { message?: string }).message ?? "Failed to delete item.");
  }
}
