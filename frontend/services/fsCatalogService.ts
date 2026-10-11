import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

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
  include_in_generated_lists: boolean;
}

export interface CreateFsItemPayload {
  name: string;
  kind: FsItemKind;
  category?: string | null;
  base_unit: string;
  purchase_price: number;
  default_supplier_id?: string | null;
  include_in_generated_lists?: boolean;
  recipe_corrections?: RecipeCorrection[];
}

export interface AffectedRecipe {
  recipe_id?: string;
  slot_id?: string;
  cycle_id?: string;
  recipe_name: string;
  line_index: number;
  quantity: number | string | null;
  unit: string;
}

export interface RecipeCorrection {
  recipe_id?: string;
  slot_id?: string;
  line_index: number;
  quantity: number;
  unit: string;
}

export class RecipeCorrectionsRequiredError extends Error {
  constructor(message: string, public affectedRecipes: AffectedRecipe[]) {
    super(message);
  }
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

/** The reference catalog (ingredients + supplies). Optional kind filter. */
export async function listCatalog(kind?: FsItemKind, page = 1, search = "", grocery: "auto" | "non_auto" | "" = ""): Promise<{ data: CatalogItem[]; meta: PaginationMeta }> {
  const qs = new URLSearchParams({ page: String(page), limit: "10" });
  if (kind) qs.set("kind", kind);
  if (search.trim()) qs.set("search", search.trim());
  if (grocery) qs.set("grocery", grocery);
  const res = await apiFetch(`/api/fss/fs-items/catalog?${qs}`);
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? "Failed to load catalog.");
  return { data: body.data ?? [], meta: body.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}

/** Search active reference-catalog items for recipe and procurement pickers. */
export async function searchCatalog(search: string, kind: FsItemKind, limit = 5, grocery?: "auto" | "non_auto"): Promise<CatalogItem[]> {
  const qs = new URLSearchParams({ search, kind, limit: String(limit) });
  if (grocery) qs.set("grocery", grocery);
  return unwrap(
    await apiFetch(`/api/fss/fs-items/catalog?${qs}`),
    "Failed to search the catalog.",
  );
}

export async function createFsItem(payload: CreateFsItemPayload): Promise<CatalogItem> {
  return unwrap(await apiFetch("/api/fss/fs-items", {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to create item.");
}

export async function updateFsItem(id: string, patch: Partial<CreateFsItemPayload>): Promise<CatalogItem> {
  const response = await apiFetch(`/api/fss/fs-items/${id}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    if (response.status === 422 && Array.isArray(body.affected_recipes)) {
      throw new RecipeCorrectionsRequiredError(body.message ?? "Update recipe measurements first.", body.affected_recipes);
    }
    throw new Error(body.message ?? "Failed to update item.");
  }
  return body.data as CatalogItem;
}

export async function deleteFsItem(id: string): Promise<void> {
  const res = await apiFetch(`/api/fss/fs-items/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) {
    const data = await res.json().catch(() => ({}));
    throw new Error((data as { message?: string }).message ?? "Failed to delete item.");
  }
}
