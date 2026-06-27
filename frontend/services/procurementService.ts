import { apiFetch } from "@/lib/apiFetch";

export interface ShoppingListItem {
  id: number;
  fs_item_id: number | null;
  ingredient_name: string;
  qty: string;
  unit: string;
  supplier_id: number | null;
  item_type: "ingredient" | "supply";
  unit_price: string | null;
  total: string | null;
  purchase_qty: string | null;
  purchase_unit: string | null;
  purchase_price: string | null;
}
export interface ShoppingList {
  id: number;
  name: string;
  list_date: string | null;
  list_type: "manual" | "suggested";
  status: "draft" | "converted";
  coverage_status: "full" | "partial";
  uncovered_dates: string[];
  days_span: number | null;
  period_start: string | null;
  period_end: string | null;
  total_served_population: number | null;
  estimate_population: number | null;
  estimate_population_updated_at: string | null;
  items: ShoppingListItem[];
}

/** Calculated budget-per-head for a procurement span. {@see ProcurementCostEfficiencyService} */
export interface CostEfficiency {
  estimated: number | null;
  actual: number | null;
  pending: boolean;
  pending_reason: string | null;
  procurement_cost: number;
  served_population: number;
  service_days_done: number;
  service_days_expected: number;
}
interface CostEfficiencyApi {
  estimated_per_head?: number | null;
  actual_per_head?: number | null;
  pending?: boolean;
  estimated_total?: number;
  estimate_population?: number;
  span_days?: number;
  estimated?: number | null;
  actual?: number | null;
  pending_reason?: string | null;
  procurement_cost?: number;
  served_population?: number;
  service_days_done?: number;
  service_days_expected?: number;
}

export interface POItem { id: number; vendor_group_id?: number | null; fs_item_id: number | null; description: string; qty: string; unit: string; unit_price: string; total_value: string; purchase_qty: string | null; purchase_unit: string | null; purchase_price: string | null }
export interface POAttachment { id: number; vendor_group_id?: number | null; type: "receipt" | "proof"; path: string; caption: string | null }
export interface POVendorGroup {
  id: number;
  supplier_id: number | null;
  supplier?: { id: number; name: string; category: string | null } | null;
  or_number: string | null;
  status: "pending" | "received";
  total_amount: string | null;
  received_at: string | null;
  stocked_at: string | null;
  items?: POItem[] | null;
  attachments?: POAttachment[] | null;
}
export interface ProgramProjectActivity {
  id: number;
  activity: string;
  menu_snapshot: string | null;
  target_date_range: string | null;
  estimated_total_cost: string;
  estimated_output_patients: number;
  actual_total_cost: string | null;
  actual_output_patients: number | null;
  execution_frozen_at: string | null;
}
export interface PurchaseOrder {
  id: number;
  shopping_list_id: number | null;
  supplier_id: number | null;
  supplier?: { id: number; name: string; category: string | null } | null;
  po_number: string;
  or_number: string | null;
  order_date: string | null;
  received_date: string | null;
  total_amount: string | null;
  actual_budget_per_head_per_day: string | null;
  status: "draft" | "ordered" | "received";
  lifecycle_status: "open_execution" | "completed" | "archived";
  converted_at: string | null;
  completed_at: string | null;
  archived_at: string | null;
  notes: string | null;
  items?: POItem[];
  attachments?: POAttachment[];
  vendor_groups?: POVendorGroup[];
  ppa?: ProgramProjectActivity | null;
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
export async function createShoppingList(payload: {
  name: string;
  list_date?: string | null;
  list_type?: "manual" | "suggested";
  status?: "draft" | "converted";
  estimate_population?: number | null;
}): Promise<ShoppingList> {
  return unwrap(await apiFetch("/api/fss/shopping-lists", {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to create list.");
}
export async function updateShoppingList(id: number, patch: Partial<Pick<ShoppingList, "name" | "list_date" | "status" | "estimate_population">>): Promise<ShoppingList> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${id}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update list.");
}
/**
 * Build a suggested list for a date range. The owning menu cycle is resolved per date
 * server-side, so a span crossing a week boundary (e.g. Fri→Mon) pulls each day from its
 * correct cycle. Dates with no plan come back in `uncovered_dates` with coverage `partial`.
 */
export async function generateByDates(start_date: string, end_date: string, name?: string): Promise<ShoppingList> {
  return unwrap(await apiFetch("/api/fss/shopping-lists/generate", {
    method: "POST", headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ start_date, end_date, name }),
  }), "Failed to generate list.");
}
export async function deleteShoppingList(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/shopping-lists/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete list.");
}
/** Calculated estimated + actual budget-per-head for the span. */
export async function getCostEfficiency(id: number): Promise<CostEfficiency> {
  const data = await unwrap<CostEfficiencyApi>(
    await apiFetch(`/api/fss/shopping-lists/${id}/cost-efficiency`),
    "Failed to load cost efficiency.",
  );

  return {
    estimated: data.estimated ?? data.estimated_per_head ?? null,
    actual: data.actual ?? data.actual_per_head ?? null,
    pending: data.pending ?? true,
    pending_reason: data.pending_reason ?? null,
    procurement_cost: data.procurement_cost ?? data.estimated_total ?? 0,
    served_population: data.served_population ?? data.estimate_population ?? 0,
    service_days_done: data.service_days_done ?? 0,
    service_days_expected: data.service_days_expected ?? data.span_days ?? 0,
  };
}
export async function updateListItem(itemId: number, patch: { supplier_id?: number | null; qty?: number; unit_price?: number }): Promise<{ id: number; supplier_id: number | null; qty: string; unit_price: string; total: string }> {
  return unwrap(await apiFetch(`/api/fss/shopping-list-items/${itemId}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update item.");
}
export async function addListItem(listId: number, payload: {
  fs_item_id?: number | null;
  ingredient_name?: string | null;
  qty: number;
  unit: string;
  supplier_id?: number | null;
  unit_price?: number | null;
  purchase_qty?: number | null;
  purchase_unit?: string | null;
  purchase_price?: number | null;
}): Promise<ShoppingListItem> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${listId}/items`, {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to add item.");
}
export async function deleteListItem(itemId: number): Promise<void> {
  const res = await apiFetch(`/api/fss/shopping-list-items/${itemId}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete item.");
}
/**
 * Approve a shopping list → it becomes the purchase: one order per vendor, each with its
 * own OR# and proof uploads. One-shot (re-approving an already-approved list is rejected).
 */
export async function approveShoppingList(listId: number): Promise<{ purchase_order_id: number; purchase_order_ids: number[] }> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${listId}/approve`, { method: "POST" }), "Failed to approve shopping list.");
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
export async function updateVendorGroup(groupId: number, patch: {
  or_number?: string | null;
  status?: "pending" | "received";
  items?: Array<{ id: number; purchase_qty?: number | null; purchase_unit?: string | null; purchase_price?: number | null; unit_price?: number | null }>;
}): Promise<PurchaseOrder> {
  return unwrap(await apiFetch(`/api/fss/purchase-order-vendor-groups/${groupId}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update vendor group.");
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
/** Upload several receipt/proof photos at once. */
export async function uploadAttachments(poId: number, files: File[], type: "receipt" | "proof", caption?: string): Promise<POAttachment[]> {
  const fd = new FormData();
  files.forEach((f) => fd.append("files[]", f));
  fd.append("type", type);
  if (caption) fd.append("caption", caption);
  const res = await apiFetch(`/api/fss/purchase-orders/${poId}/attachments`, { method: "POST", body: fd });
  return unwrap(res, "Failed to upload.");
}
export async function uploadVendorGroupAttachments(groupId: number, files: File[], type: "receipt" | "proof", caption?: string): Promise<POAttachment[]> {
  const fd = new FormData();
  files.forEach((f) => fd.append("files[]", f));
  fd.append("type", type);
  if (caption) fd.append("caption", caption);
  const res = await apiFetch(`/api/fss/purchase-order-vendor-groups/${groupId}/attachments`, { method: "POST", body: fd });
  return unwrap(res, "Failed to upload.");
}
export async function getPurchaseOrderPpa(id: number): Promise<ProgramProjectActivity> {
  return unwrap(await apiFetch(`/api/fss/purchase-orders/${id}/ppa`), "Failed to load PPA.");
}
export async function deleteAttachment(attachmentId: number): Promise<void> {
  const res = await apiFetch(`/api/fss/purchase-order-attachments/${attachmentId}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete attachment.");
}
