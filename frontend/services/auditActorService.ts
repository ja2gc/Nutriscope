import { apiFetch } from "@/lib/apiFetch";

export interface AuditActorOption {
  id: string;
  name: string;
  role: string;
}

export interface AuditActorPage {
  data: AuditActorOption[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export async function listAuditActors(params: {
  search?: string;
  selected_id?: string;
  page?: number;
  per_page?: number;
} = {}, signal?: AbortSignal): Promise<AuditActorPage> {
  const qs = new URLSearchParams();
  if (params.search) qs.set("search", params.search);
  if (params.selected_id) qs.set("selected_id", params.selected_id);
  if (params.page) qs.set("page", String(params.page));
  if (params.per_page) qs.set("per_page", String(params.per_page));

  const response = await apiFetch(`/api/admin/audit-actors?${qs}`, {
    method: "GET",
    headers: { Accept: "application/json" },
    signal,
  }, { redirectOnUnauthorized: false });
  if (!response.ok) throw new Error("Unable to load audit actors.");

  return response.json();
}
