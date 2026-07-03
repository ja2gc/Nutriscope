import { apiFetch } from "@/lib/apiFetch";
import { PaginationMeta } from "./inventoryService";

export interface AuditLogCauser {
  id: string;
  name: string;
  email: string;
  role: "RND" | "FSS" | "Admin";
}

export interface AuditLog {
  id: number;
  log_name: string;
  description: string;
  event: string | null;
  subject_type: string | null;
  subject_id: number | null;
  causer: AuditLogCauser | null;
  properties: Record<string, unknown> | null;
  created_at: string;
  updated_at: string;
}

export interface ListAuditLogsParams {
  page?: number;
  per_page?: number;
  causer_id?: string;
  subject_type?: string;
  event?: string;
  start?: string; // YYYY-MM-DD
  end?: string;   // YYYY-MM-DD
}

export async function listAuditLogs(params: ListAuditLogsParams = {}): Promise<{
  data: AuditLog[];
  meta: PaginationMeta;
}> {
  const qs = new URLSearchParams();
  if (params.page) qs.set("page", String(params.page));
  if (params.per_page) qs.set("per_page", String(params.per_page));
  if (params.causer_id) qs.set("causer_id", String(params.causer_id));
  if (params.subject_type) qs.set("subject_type", params.subject_type);
  if (params.event) qs.set("event", params.event);
  if (params.start) qs.set("start", params.start);
  if (params.end) qs.set("end", params.end);

  const res = await apiFetch(`/api/admin/audit-logs?${qs}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch audit logs.");
  }

  const responseData = await res.json();
  return {
    data: responseData.data || [],
    meta: responseData.meta || {
      current_page: 1,
      per_page: 25,
      total: 0,
      last_page: 1,
    },
  };
}
