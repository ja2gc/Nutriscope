import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

export interface Supplier {
  id: number;
  name: string;
  /** Free-text description, e.g. "vegetables", "meats". (stored in `category`) */
  category: string | null;
  contact: string | null;
  address: string | null;
  payment_terms: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface SupplierPayload {
  name: string;
  category?: string | null;
  contact?: string | null;
  address?: string | null;
  payment_terms?: string | null;
  notes?: string | null;
}

export async function listSuppliers(page = 1, search = ""): Promise<{ data: Supplier[]; meta: PaginationMeta }> {
  const qs = new URLSearchParams({ page: String(page), per_page: "10" });
  if (search.trim()) qs.set("search", search.trim());
  const res = await apiFetch(`/api/fss/suppliers?${qs}`);
  if (!res.ok) throw new Error("Failed to load suppliers.");
  const json = await res.json();
  return { data: json.data ?? [], meta: json.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}

export async function saveSupplier(id: number | null, payload: SupplierPayload): Promise<Supplier> {
  const url = id ? `/api/fss/suppliers/${id}` : "/api/fss/suppliers";
  const res = await apiFetch(url, {
    method: id ? "PATCH" : "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.message ?? "Failed to save supplier.");
  return json.data;
}

export async function deleteSupplier(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/suppliers/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) {
    const json = await res.json().catch(() => ({}));
    throw new Error(json.message ?? "Failed to delete supplier.");
  }
}
