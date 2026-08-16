import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

export interface ShoppingListItem {
  id: string;
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
  vendor_locked?: boolean;
  source: "generated" | "manual";
  included_in_po: boolean;
  exclusion_note: string | null;
  baseline_servings?: number | null;
  baseline_quantity?: string | null;
  scaled_quantity?: string | null;
  scaled_unit?: string | null;
}
export interface ReleaseBlocker { code: string; message: string }
export interface ReleaseReadiness { ready: boolean; blockers: ReleaseBlocker[] }
export interface ShoppingList {
  id: string;
  name: string;
  list_date: string | null;
  list_type: "manual" | "suggested";
  procurement_track?: "food" | "supplies" | null;
  status: "draft" | "converted";
  coverage_status: "full" | "partial";
  uncovered_dates: string[];
  days_span: number | null;
  period_start: string | null;
  period_end: string | null;
  total_served_population: number | null;
  estimate_population: number | null;
  estimate_population_updated_at: string | null;
  estimated_total?: number | null;
  estimated_budget_per_head_per_day?: number | null;
  release_readiness?: ReleaseReadiness | null;
  items: ShoppingListItem[];
}


export interface POItem { id: number; vendor_group_id?: number | null; fs_item_id: number | null; description: string; qty: string; unit: string; unit_price: string; total_value: string; purchase_qty: string | null; purchase_unit: string | null; purchase_price: string | null; actual_qty: string; actual_unit: string; actual_unit_price: string; actual_total: number; actual_values_confirmed: boolean }
export interface POAttachment { id: string; vendor_group_id?: number | null; type: "receipt" | "proof"; path: string; url: string; caption: string | null }
export interface POVendorGroup {
  id: string;
  supplier_id: number | null;
  supplier?: { id: string; name: string; category: string | null } | null;
  or_number: string | null;
  or_number_display: string;
  status: "pending" | "received";
  total_amount: string | null;
  received_at: string | null;
  stocked_at: string | null;
  items?: POItem[] | null;
  attachments?: POAttachment[] | null;
  evidence_requirements?: { supplier_assigned: boolean; actual_values_reviewed: boolean; receipt_uploaded: boolean; proof_uploaded: boolean; can_mark_received: boolean };
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
  id: string;
  shopping_list_id: string | null;
  shopping_list?: { id: string; name: string } | null;
  supplier_id: number | null;
  supplier?: { id: string; name: string; category: string | null } | null;
  po_number: string;
  or_number: string | null;
  order_date: string | null;
  received_date: string | null;
  total_amount: string | null;
  actual_budget_per_head_per_day: string | null;
  served_population_progress?: { expected: number; done: number; served: number } | null;
  status: "draft" | "ordered" | "received";
  lifecycle_status: "open_execution" | "completed" | "archived";
  procurement_track?: "food" | "supplies" | null;
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
export async function listShoppingLists(page = 1): Promise<{ data: ShoppingList[]; meta: PaginationMeta }> {
  const res = await apiFetch(`/api/fss/shopping-lists?page=${page}&per_page=10`);
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? "Failed to load shopping lists.");
  return { data: body.data ?? [], meta: body.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}
export async function getShoppingList(id: string): Promise<ShoppingList> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${id}`), "Failed to load list.");
}
export async function createShoppingList(payload: {
  name: string;
  list_date?: string | null;
  list_type?: "manual" | "suggested";
  procurement_track?: "food" | "supplies";
  status?: "draft" | "converted";
  estimate_population?: number | null;
}): Promise<ShoppingList> {
  return unwrap(await apiFetch("/api/fss/shopping-lists", {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to create list.");
}
export async function updateShoppingList(id: string, patch: Partial<Pick<ShoppingList, "name" | "list_date" | "status" | "estimate_population">>): Promise<ShoppingList> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${id}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update list.");
}
/**
 * Build a suggested list for a date range. The owning menu cycle is resolved per date
 * server-side, so a span crossing a week boundary (e.g. Fri→Mon) pulls each day from its
 * correct cycle. Dates with no plan come back in `uncovered_dates` with coverage `partial`.
 */
/** Thrown when generation is blocked because span dates are missing a menu plan. */
export class MissingMenuDaysError extends Error {
  missingDates: string[];
  missingByDate: Record<string, string>;
  constructor(message: string, missingDates: string[], missingByDate: Record<string, string>) {
    super(message);
    this.name = "MissingMenuDaysError";
    this.missingDates = missingDates;
    this.missingByDate = missingByDate;
  }
}

export async function generateByDates(start_date: string, end_date: string, estimate_population: number, name?: string): Promise<ShoppingList> {
  const res = await apiFetch("/api/fss/shopping-lists/generate", {
    method: "POST", headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ start_date, end_date, estimate_population, name }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    // All-or-nothing: the backend reports the exact missing dates + per-date reason
    // so the planner knows which empty menu days to fill before regenerating.
    const missingDates = (data as { missing_dates?: string[] }).missing_dates;
    if (res.status === 422 && Array.isArray(missingDates)) {
      throw new MissingMenuDaysError(
        (data as { message?: string }).message ?? "Some span dates are missing a menu plan.",
        missingDates,
        (data as { missing_items_by_date?: Record<string, string> }).missing_items_by_date ?? {},
      );
    }
    throw new Error((data as { message?: string }).message ?? "Failed to generate list.");
  }
  return (data as { data: ShoppingList }).data;
}
export async function deleteShoppingList(id: string): Promise<void> {
  const res = await apiFetch(`/api/fss/shopping-lists/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete list.");
}

export async function updateListItem(itemId: string, patch: { supplier_id?: string | null; qty?: number; unit_price?: number; purchase_qty?: number | null; purchase_unit?: string | null; purchase_price?: number | null; included_in_po?: boolean; exclusion_note?: string | null }): Promise<ShoppingListItem> {
  return unwrap(await apiFetch(`/api/fss/shopping-list-items/${itemId}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update item.");
}
export async function addListItem(listId: string, payload: {
  fs_item_id?: string | null;
  ingredient_name?: string | null;
  qty: number;
  unit: string;
  supplier_id?: string | null;
  unit_price?: number | null;
  purchase_qty?: number | null;
  purchase_unit?: string | null;
  purchase_price?: number | null;
}): Promise<ShoppingListItem> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${listId}/items`, {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to add item.");
}
export async function deleteListItem(itemId: string): Promise<void> {
  const res = await apiFetch(`/api/fss/shopping-list-items/${itemId}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete item.");
}
/**
 * Convert a shopping list → it becomes the purchase: ONE purchase order with a vendor
 * group per supplier, each group carrying its own OR#, receipts, and proof uploads.
 * One-shot (re-converting an already-converted list is rejected).
 */
export async function approveShoppingList(listId: string): Promise<{ purchase_order_id: string; purchase_order_ids: string[] }> {
  return unwrap(await apiFetch(`/api/fss/shopping-lists/${listId}/approve`, { method: "POST" }), "Failed to approve shopping list.");
}

// ─── Purchase orders ───────────────────────────────────────────────────────────
export async function listPurchaseOrders(page = 1, shoppingListId?: number): Promise<{ data: PurchaseOrder[]; meta: PaginationMeta }> {
  const qs = new URLSearchParams({ page: String(page), per_page: "10" });
  if (shoppingListId) qs.set("shopping_list_id", String(shoppingListId));
  const res = await apiFetch(`/api/fss/purchase-orders?${qs}`);
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? "Failed to load purchase orders.");
  return { data: body.data ?? [], meta: body.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}
export async function getPurchaseOrder(id: string): Promise<PurchaseOrder> {
  return unwrap(await apiFetch(`/api/fss/purchase-orders/${id}`), "Failed to load PO.");
}
export async function updatePurchaseOrder(id: string, patch: Partial<Pick<PurchaseOrder, "or_number" | "status" | "notes">>): Promise<PurchaseOrder> {
  return unwrap(await apiFetch(`/api/fss/purchase-orders/${id}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update PO.");
}
export async function updateVendorGroup(groupId: string, patch: {
  or_number?: string | null;
  status?: "pending" | "received";
  items?: Array<{ id: number; actual_qty?: number | null; actual_unit_price?: number | null; receipt_total?: number | null }>;
}): Promise<PurchaseOrder> {
  return unwrap(await apiFetch(`/api/fss/purchase-order-vendor-groups/${groupId}`, {
    method: "PATCH", headers: { "Content-Type": "application/json" }, body: JSON.stringify(patch),
  }), "Failed to update vendor group.");
}
export async function deletePurchaseOrder(id: string): Promise<void> {
  const res = await apiFetch(`/api/fss/purchase-orders/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete PO.");
}
export async function uploadAttachment(poId: string, file: File, type: "receipt" | "proof", caption?: string): Promise<POAttachment> {
  const fd = new FormData();
  fd.append("file", file); fd.append("type", type);
  if (caption) fd.append("caption", caption);
  const res = await apiFetch(`/api/fss/purchase-orders/${poId}/attachments`, { method: "POST", body: fd });
  return unwrap(res, "Failed to upload.");
}
/** Upload several receipt/proof photos at once. */
export async function uploadAttachments(poId: string, files: File[], type: "receipt" | "proof", caption?: string): Promise<POAttachment[]> {
  const fd = new FormData();
  files.forEach((f) => fd.append("files[]", f));
  fd.append("type", type);
  if (caption) fd.append("caption", caption);
  const res = await apiFetch(`/api/fss/purchase-orders/${poId}/attachments`, { method: "POST", body: fd });
  return unwrap(res, "Failed to upload.");
}
export async function uploadVendorGroupAttachments(groupId: string, files: File[], type: "receipt" | "proof", caption?: string): Promise<POAttachment[]> {
  const fd = new FormData();
  files.forEach((f) => fd.append("files[]", f));
  fd.append("type", type);
  if (caption) fd.append("caption", caption);
  const res = await apiFetch(`/api/fss/purchase-order-vendor-groups/${groupId}/attachments`, { method: "POST", body: fd });
  return unwrap(res, "Failed to upload.");
}
export async function getPurchaseOrderPpa(id: string): Promise<ProgramProjectActivity> {
  return unwrap(await apiFetch(`/api/fss/purchase-orders/${id}/ppa`), "Failed to load PPA.");
}
export async function deleteAttachment(attachmentId: string): Promise<void> {
  const res = await apiFetch(`/api/fss/purchase-order-attachments/${attachmentId}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete attachment.");
}
