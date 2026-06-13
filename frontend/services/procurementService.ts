import { apiFetch } from "@/lib/apiFetch";

export interface ShoppingListItem {
  id: number;
  fs_item_id: number | null;
  ingredient_name: string;
  qty: string;
  unit: string;
  supplier_id: number | null;
  unit_price: string | null;
  total: string | null;
}
export interface ShoppingList {
  id: number;
  menu_cycle_id: number | null;
  name: string;
  list_date: string | null;
  list_type: "manual" | "suggested";
  status: "draft" | "finalized";
  days_span: number | null;
  period_start: string | null;
  period_end: string | null;
  items: ShoppingListItem[];
}

export interface POItem { id: number; fs_item_id: number | null; description: string; qty: string; unit: string; unit_price: string; total_value: string }
export interface POAttachment { id: number; type: "receipt" | "proof"; path: string; caption: string | null }
export interface PurchaseOrder {
  id: number;
  shopping_list_id: number | null;
  supplier_id: number | null;
  supplier?: { id: number; name: string; category: string | null } | null;
  po_number: string;
  or_number: string | null;
  order_date: string | null;
  total_amount: string | null;
  status: "draft" | "ordered" | "received";
  notes: string | null;
  items?: POItem[];
  attachments?: POAttachment[];
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

// ─── Shopping lists ─────────────────────────────────────────────────────────────
export async function listShoppingLists(): Promise<ShoppingList[]> {
  return unwrap(await apiFetch("/api/fss/shopping-lists"), "Failed to load shopping lists.");
}
export async function getShoppingList(id: number): Promise<ShoppingList> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${id}`), "Failed to load list.");
}
export async function generateFromCycle(menu_cycle_id: number, start_date: string, end_date: string, name?: string): Promise<ShoppingList> {
  return unwrap(await apiFetch("/api/fss/shopping-lists/generate", {
    method: "POST", headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ menu_cycle_id, start_date, end_date, name }),
  }), "Failed to generate list.");
}
export async function deleteShoppingList(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/shopping-lists/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete list.");
}
export async function updateListItem(itemId: number, patch: { supplier_id?: number | null; qty?: number; unit_price?: number }): Promise<{ id: number; supplier_id: number | null; qty: string; unit_price: string; total: string }> {
  return unwrap(await apiFetch(`/api/fss/shopping-list-items/${itemId}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update item.");
}
export async function generatePos(listId: number): Promise<{ purchase_order_ids: number[] }> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${listId}/generate-pos`, { method: "POST" }), "Failed to generate POs.");
}

// ─── Purchase orders ───────────────────────────────────────────────────────────
export async function listPurchaseOrders(shoppingListId?: number): Promise<PurchaseOrder[]> {
  const qs = shoppingListId ? `?shopping_list_id=${shoppingListId}` : "";
  return unwrap(await apiFetch(`/api/fss/purchase-orders${qs}`), "Failed to load purchase orders.");
}
export async function getPurchaseOrder(id: number): Promise<PurchaseOrder> {
  return unwrap(await apiFetch(`/api/fss/purchase-orders/${id}`), "Failed to load PO.");
}
export async function updatePurchaseOrder(id: number, patch: Partial<Pick<PurchaseOrder, "or_number" | "status" | "notes">>): Promise<PurchaseOrder> {
  return unwrap(await apiFetch(`/api/fss/purchase-orders/${id}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update PO.");
}
export async function deletePurchaseOrder(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/purchase-orders/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete PO.");
}
export async function uploadAttachment(poId: number, file: File, type: "receipt" | "proof", caption?: string): Promise<POAttachment> {
  const fd = new FormData();
  fd.append("file", file); fd.append("type", type);
  if (caption) fd.append("caption", caption);
  const res = await apiFetch(`/api/fss/purchase-orders/${poId}/attachments`, { method: "POST", body: fd });
  return unwrap(res, "Failed to upload.");
}
export async function deleteAttachment(attachmentId: number): Promise<void> {
  const res = await apiFetch(`/api/fss/purchase-order-attachments/${attachmentId}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete attachment.");
}
