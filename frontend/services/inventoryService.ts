export interface FoodItemRef {
  id: number;
  name: string;
}

export interface InventoryItem {
  id: number;
  food_item_id: number;
  food_item: FoodItemRef;
  quantity_in_stock: string;
  unit: string;
  expiry_date: string | null;
  usage_rate: string | null;
  minimum_stock_threshold: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface CreateInventoryPayload {
  food_item_id: number;
  quantity_in_stock: number;
  unit: string;
  expiry_date?: string | null;
  usage_rate?: number | null;
  minimum_stock_threshold?: number | null;
  notes?: string | null;
}

export interface UpdateInventoryPayload {
  quantity_in_stock?: number;
  unit?: string;
  expiry_date?: string | null;
  usage_rate?: number | null;
  minimum_stock_threshold?: number | null;
  notes?: string | null;
}

export type StockStatus = "low" | "expiring" | "ok";

export function getStockStatus(item: InventoryItem): StockStatus {
  const qty = parseFloat(item.quantity_in_stock);
  const threshold = item.minimum_stock_threshold
    ? parseFloat(item.minimum_stock_threshold)
    : null;

  if (threshold !== null && qty < threshold) return "low";

  if (item.expiry_date) {
    const expiry = new Date(item.expiry_date);
    const now = new Date();
    const diffDays = (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);
    if (diffDays <= 7) return "expiring";
  }

  return "ok";
}

export async function listInventory(): Promise<InventoryItem[]> {
  const res = await fetch("/api/fss/inventory");
  if (!res.ok) throw new Error("Failed to fetch inventory.");
  const json = await res.json();
  return json.data;
}

export async function createInventory(
  payload: CreateInventoryPayload
): Promise<InventoryItem> {
  const res = await fetch("/api/fss/inventory", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Failed to create.");
  return json.data;
}

export async function updateInventory(
  id: number,
  payload: UpdateInventoryPayload
): Promise<InventoryItem> {
  const res = await fetch(`/api/fss/inventory/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Failed to update.");
  return json.data;
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
): Promise<InventoryItem> {
  const res = await fetch(`/api/fss/inventory/${id}/restock`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ quantity }),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Restock failed.");
  return json.data;
}
