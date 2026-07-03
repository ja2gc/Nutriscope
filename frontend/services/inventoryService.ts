import { apiFetch } from "@/lib/apiFetch";

/** Food-service inventory item kinds. Catalog items split into ingredient/supply; recipes are prepared dishes. */
export type ItemType = "ingredient" | "supply" | "recipe";
// Binary stock state: in stock (green) or out / untracked (red). No low-stock threshold.
export type StockStatus = "no_stock" | "ok";

export interface FsItemRef {
  id: number;
  name: string;
  kind?: ItemType;
  category?: string;
  base_unit?: string;
  unit_cost?: number;
}

export interface RecipeRef {
  id: number;
  name: string;
  category?: string;
  cost?: string;
  servings?: number;
}

export type RowHighlight = "green" | "red";

/** A merged row in the unified inventory table (catalog item or recipe + optional stock). */
export interface InventoryRow {
  inventoryId: number | null;   // null = no stock record yet
  itemType: ItemType;
  itemId: string;               // fs_item_id or recipe_id (uuid)
  name: string;
  category: string;
  quantity_in_stock: string;
  unit: string;
  /** Catalog buy price (per purchase_unit). Null for recipes. */
  unit_price: string | null;
  /** Derived ₱ per base_unit (catalog items only). */
  unit_cost: string | null;
  base_unit: string | null;
  purchase_unit: string | null;
  units_per_purchase: number | null;
  /** Auto-calculated cost per recipe from ingredients (recipes only). */
  recipe_cost: string | null;
  recipe_servings: number | null;
  status: StockStatus;
  highlight: RowHighlight;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface InventoryStats {
  total: number;
  in_stock: number;
  no_stock: number;
}

export interface ListInventoryRowsParams {
  page?: number;
  per_page?: number;
  search?: string;
  type?: "all" | ItemType;
  status?: "all" | StockStatus;
}

export async function listInventoryRows(params: ListInventoryRowsParams = {}): Promise<{
  data: InventoryRow[];
  meta: PaginationMeta;
  stats: InventoryStats;
}> {
  const qs = new URLSearchParams();
  if (params.page)                              qs.set("page", String(params.page));
  if (params.per_page)                          qs.set("per_page", String(params.per_page));
  if (params.search)                            qs.set("search", params.search);
  if (params.type && params.type !== "all")     qs.set("type", params.type);
  if (params.status && params.status !== "all") qs.set("status", params.status);

  const res = await apiFetch(`/api/fss/inventory/rows?${qs}`);
  if (!res.ok) throw new Error("Failed to fetch inventory.");
  const json = await res.json();

  const data: InventoryRow[] = (json.data ?? []).map((r: Record<string, unknown>) => ({
    inventoryId:              r.inventory_id as number | null,
    itemType:                 r.item_type as ItemType,
    itemId:                   r.item_id as string,
    name:                     r.name as string,
    category:                 (r.category as string) ?? "",
    quantity_in_stock:        (r.quantity_in_stock as string) ?? "0",
    unit:                     (r.unit as string) ?? "",
    unit_price:               (r.unit_price as string | null) ?? null,
    unit_cost:                (r.unit_cost as string | null) ?? null,
    base_unit:                (r.base_unit as string | null) ?? null,
    purchase_unit:            (r.purchase_unit as string | null) ?? null,
    units_per_purchase:       (r.units_per_purchase as number | null) ?? null,
    recipe_cost:              (r.recipe_cost as string | null) ?? null,
    recipe_servings:          (r.recipe_servings as number | null) ?? null,
    status:                   r.status as StockStatus,
    highlight:                r.highlight as RowHighlight,
  }));

  return { data, meta: json.meta, stats: json.stats };
}

export async function patchFsItemCategory(fsItemId: number, category: string | null): Promise<void> {
  const res = await apiFetch(`/api/fss/fs-items/${fsItemId}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ category }),
  });
  if (!res.ok) {
    const json = await res.json().catch(() => ({}));
    throw new Error(json.message ?? "Failed to update category.");
  }
}

export interface PatchFsItemPayload {
  category?: string | null;
  purchase_price?: number | null;
  purchase_unit?: string | null;
  base_unit?: string | null;
  units_per_purchase?: number | null;
}

export async function patchFsItem(fsItemId: number, payload: PatchFsItemPayload): Promise<void> {
  const res = await apiFetch(`/api/fss/fs-items/${fsItemId}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const json = await res.json().catch(() => ({}));
    throw new Error(json.message ?? "Failed to update item.");
  }
}

export async function patchRecipeCategory(recipeId: number, category: string | null): Promise<void> {
  const res = await apiFetch(`/api/fss/food-service-recipes/${recipeId}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ category }),
  });
  if (!res.ok) {
    const json = await res.json().catch(() => ({}));
    throw new Error(json.message ?? "Failed to update category.");
  }
}
