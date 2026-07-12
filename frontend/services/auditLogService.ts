import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";
import type { AuditCategory, AuditEventDto, AuditOutcome, AuditSeverity } from "@/types/audit";

export type AuditLog = AuditEventDto;

export interface ListAuditLogsParams {
  page?: number;
  per_page?: number;
  category?: AuditCategory;
  domain?: AuditEventDto["domain"];
  action?: string;
  severity?: AuditSeverity;
  outcome?: AuditOutcome;
  actor_id?: string;
  subject_id?: string;
  context_id?: string;
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
  if (params.category) qs.set("category", params.category);
  if (params.domain) qs.set("domain", params.domain);
  if (params.action) qs.set("action", params.action);
  if (params.severity) qs.set("severity", params.severity);
  if (params.outcome) qs.set("outcome", params.outcome);
  if (params.actor_id) qs.set("actor_id", params.actor_id);
  if (params.subject_id) qs.set("subject_id", params.subject_id);
  if (params.context_id) qs.set("context_id", params.context_id);
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
