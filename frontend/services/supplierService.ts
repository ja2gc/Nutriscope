import { apiFetch } from "@/lib/apiFetch";

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

export async function listSuppliers(): Promise<Supplier[]> {
  const res = await apiFetch("/api/fss/suppliers");
  if (!res.ok) throw new Error("Failed to load suppliers.");
  return (await res.json()).data;
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
