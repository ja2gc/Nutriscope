import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

export interface Sop {
  id: number;
  title: string;
  body: string;
  author?: { id: number; name: string; role: string } | null;
  created_at: string;
}

export interface SopPayload {
  title: string;
  body: string;
}

/** Current (latest) SOP, or null when none has been set. */
export async function fetchCurrentSop(): Promise<Sop | null> {
  const res = await apiFetch("/api/sop", {
    method: "GET",
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error("Failed to load SOP.");
  const json = await res.json();
  return json.data ?? null;
}

/** Full revision history, newest first. */
export async function fetchSopHistory(page = 1): Promise<{ data: Sop[]; meta: PaginationMeta }> {
  const res = await apiFetch(`/api/sop/history?page=${page}&per_page=10`, {
    method: "GET",
    headers: { Accept: "application/json" },
  });
  if (!res.ok) throw new Error("Failed to load SOP history.");
  const json = await res.json();
  return { data: json.data ?? [], meta: json.meta ?? { current_page: page, per_page: 10, total: 0, last_page: 1 } };
}

/** Save a new SOP revision (append-only). RND/Admin only. */
export async function saveSop(data: SopPayload): Promise<Sop> {
  const res = await apiFetch("/api/sop", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || "Failed to save SOP.");
  }
  const json = await res.json();
  return json.data ?? json;
}
