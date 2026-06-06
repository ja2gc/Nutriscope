export type ItemType = "food_item" | "recipe";
export type StockStatus = "low" | "expiring" | "ok" | "untracked";

export interface FoodItemRef {
  id: number;
  name: string;
  category?: string;
}

export interface RecipeRef {
  id: number;
  name: string;
  category?: string;
  cost?: string;
  servings?: number;
}

export interface InventoryRecord {
  id: number;
  item_type: ItemType;
  food_item_id: number | null;
  recipe_id: number | null;
  food_item?: FoodItemRef;
  recipe?: RecipeRef;
  quantity_in_stock: string;
  unit: string;
  expiry_date: string | null;
  usage_rate: string | null;
  minimum_stock_threshold: string | null;
  unit_price: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export type RowHighlight = "none" | "yellow" | "red";

/** A merged row in the unified inventory table */
export interface InventoryRow {
  inventoryId: number | null;   // null = no stock record yet
  itemType: ItemType;
  itemId: number;               // food_item_id or recipe_id
  name: string;
  category: string;
  quantity_in_stock: string;
  unit: string;
  expiry_date: string | null;
  usage_rate: string | null;
  minimum_stock_threshold: string | null;
  unit_price: string | null;
  /** Auto-calculated cost per serving from recipe ingredients (recipes only) */
  recipe_cost: string | null;
  recipe_servings: number | null;
  notes: string | null;
  status: StockStatus;
  highlight: RowHighlight;
}

export interface UpsertInventoryPayload {
  item_type: ItemType;
  food_item_id?: number | null;
  recipe_id?: number | null;
  quantity_in_stock: number;
  unit: string;
  expiry_date?: string | null;
  usage_rate?: number | null;
  minimum_stock_threshold?: number | null;
  unit_price?: number | null;
  notes?: string | null;
}

export function getStockStatus(
  qty: string,
  threshold: string | null,
  expiry: string | null
): StockStatus {
  const qtyNum = parseFloat(qty);
  const threshNum = threshold ? parseFloat(threshold) : null;

  if (threshNum !== null && qtyNum < threshNum) return "low";

  if (expiry) {
    const diffDays =
      (new Date(expiry).getTime() - Date.now()) / (1000 * 60 * 60 * 24);
    if (diffDays <= 7) return "expiring";
  }

  return "ok";
}

export function getRowHighlight(
  qty: string,
  threshold: string | null,
  expiry: string | null,
  hasRecord: boolean
): RowHighlight {
  if (!hasRecord) return "none";
  const qtyNum = parseFloat(qty);
  if (qtyNum === 0) return "red";
  const threshNum = threshold ? parseFloat(threshold) : null;
  if (threshNum !== null && qtyNum < threshNum) return "yellow";
  if (expiry) {
    const diffDays = (new Date(expiry).getTime() - Date.now()) / (1000 * 60 * 60 * 24);
    if (diffDays <= 7) return "yellow";
  }
  return "none";
}

export function buildInventoryRows(
  foodItems: FoodItemRef[],
  recipes: RecipeRef[],
  records: InventoryRecord[]
): InventoryRow[] {
  const byFoodItem = new Map<number, InventoryRecord>();
  const byRecipe = new Map<number, InventoryRecord>();
  for (const r of records) {
    if (r.food_item_id) byFoodItem.set(r.food_item_id, r);
    if (r.recipe_id) byRecipe.set(r.recipe_id, r);
  }

  const rows: InventoryRow[] = [];

  for (const fi of foodItems) {
    const rec = byFoodItem.get(fi.id);
    const hasRecord = !!rec;
    const qty = rec?.quantity_in_stock ?? "0";
    const threshold = rec?.minimum_stock_threshold ?? null;
    const expiry = rec?.expiry_date ?? null;
    rows.push({
      inventoryId:              rec?.id ?? null,
      itemType:                 "food_item",
      itemId:                   fi.id,
      name:                     fi.name,
      category:                 fi.category ?? "",
      quantity_in_stock:        qty,
      unit:                     rec?.unit ?? "",
      expiry_date:              expiry,
      usage_rate:               rec?.usage_rate ?? null,
      minimum_stock_threshold:  threshold,
      unit_price:               rec?.unit_price ?? null,
      recipe_cost:              null,
      recipe_servings:          null,
      notes:                    rec?.notes ?? null,
      status:                   hasRecord ? getStockStatus(qty, threshold, expiry) : "untracked",
      highlight:                getRowHighlight(qty, threshold, expiry, hasRecord),
    });
  }

  for (const rcp of recipes) {
    const rec = byRecipe.get(rcp.id);
    const hasRecord = !!rec;
    const qty = rec?.quantity_in_stock ?? "0";
    const threshold = rec?.minimum_stock_threshold ?? null;
    const expiry = rec?.expiry_date ?? null;
    rows.push({
      inventoryId:              rec?.id ?? null,
      itemType:                 "recipe",
      itemId:                   rcp.id,
      name:                     rcp.name,
      category:                 rcp.category ?? "",
      quantity_in_stock:        qty,
      unit:                     rec?.unit ?? "servings",
      expiry_date:              expiry,
      usage_rate:               rec?.usage_rate ?? null,
      minimum_stock_threshold:  threshold,
      unit_price:               rec?.unit_price ?? null,
      recipe_cost:              null,
      recipe_servings:          null,
      notes:                    rec?.notes ?? null,
      status:                   hasRecord ? getStockStatus(qty, threshold, expiry) : "untracked",
      highlight:                getRowHighlight(qty, threshold, expiry, hasRecord),
    });
  }

  return rows;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface InventoryStats {
  total: number;
  tracked: number;
  low: number;
  expiring: number;
  untracked: number;
}

export interface ListInventoryRowsParams {
  page?: number;
  per_page?: number;
  search?: string;
  type?: "all" | "food_item" | "recipe";
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

  const res = await fetch(`/api/fss/inventory/rows?${qs}`);
  if (!res.ok) throw new Error("Failed to fetch inventory.");
  const json = await res.json();

  const data: InventoryRow[] = (json.data ?? []).map((r: Record<string, unknown>) => ({
    inventoryId:              r.inventory_id as number | null,
    itemType:                 r.item_type as ItemType,
    itemId:                   r.item_id as number,
    name:                     r.name as string,
    category:                 (r.category as string) ?? "",
    quantity_in_stock:        (r.quantity_in_stock as string) ?? "0",
    unit:                     (r.unit as string) ?? "",
    expiry_date:              (r.expiry_date as string | null) ?? null,
    usage_rate:               (r.usage_rate as string | null) ?? null,
    minimum_stock_threshold:  (r.minimum_stock_threshold as string | null) ?? null,
    unit_price:               (r.unit_price as string | null) ?? null,
    recipe_cost:              (r.recipe_cost as string | null) ?? null,
    recipe_servings:          (r.recipe_servings as number | null) ?? null,
    notes:                    (r.notes as string | null) ?? null,
    status:                   r.status as StockStatus,
    highlight:                r.highlight as RowHighlight,
  }));

  return { data, meta: json.meta, stats: json.stats };
}

export async function listInventory(): Promise<InventoryRecord[]> {
  const res = await fetch("/api/fss/inventory");
  if (!res.ok) throw new Error("Failed to fetch inventory.");
  const json = await res.json();
  return json.data;
}

export async function upsertInventory(
  inventoryId: number | null,
  payload: UpsertInventoryPayload
): Promise<InventoryRecord> {
  if (inventoryId) {
    const res = await fetch(`/api/fss/inventory/${inventoryId}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message ?? "Failed to update.");
    return json.data;
  } else {
    const res = await fetch("/api/fss/inventory", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message ?? "Failed to create.");
    return json.data;
  }
}

export async function deleteInventory(id: number): Promise<void> {
  const res = await fetch(`/api/fss/inventory/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) {
    const json = await res.json().catch(() => ({}));
    throw new Error(json.message ?? "Failed to delete.");
  }
}

export async function restockInventory(
  id: number,
  quantity: number
): Promise<InventoryRecord> {
  const res = await fetch(`/api/fss/inventory/${id}/restock`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ quantity }),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Restock failed.");
  return json.data;
}
