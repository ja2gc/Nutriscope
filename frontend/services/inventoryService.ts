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
  notes: string | null;
  created_at: string;
  updated_at: string;
}

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
  notes: string | null;
  status: StockStatus;
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
    rows.push({
      inventoryId:              rec?.id ?? null,
      itemType:                 "food_item",
      itemId:                   fi.id,
      name:                     fi.name,
      category:                 fi.category ?? "",
      quantity_in_stock:        rec?.quantity_in_stock ?? "0",
      unit:                     rec?.unit ?? "",
      expiry_date:              rec?.expiry_date ?? null,
      usage_rate:               rec?.usage_rate ?? null,
      minimum_stock_threshold:  rec?.minimum_stock_threshold ?? null,
      notes:                    rec?.notes ?? null,
      status:                   rec
        ? getStockStatus(rec.quantity_in_stock, rec.minimum_stock_threshold, rec.expiry_date)
        : "untracked",
    });
  }

  for (const rcp of recipes) {
    const rec = byRecipe.get(rcp.id);
    rows.push({
      inventoryId:              rec?.id ?? null,
      itemType:                 "recipe",
      itemId:                   rcp.id,
      name:                     rcp.name,
      category:                 rcp.category ?? "",
      quantity_in_stock:        rec?.quantity_in_stock ?? "0",
      unit:                     rec?.unit ?? "servings",
      expiry_date:              rec?.expiry_date ?? null,
      usage_rate:               rec?.usage_rate ?? null,
      minimum_stock_threshold:  rec?.minimum_stock_threshold ?? null,
      notes:                    rec?.notes ?? null,
      status:                   rec
        ? getStockStatus(rec.quantity_in_stock, rec.minimum_stock_threshold, rec.expiry_date)
        : "untracked",
    });
  }

  return rows;
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
