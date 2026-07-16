import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";
import type {
  AuditCapabilities,
  AuditEventDto,
  AuditFilterMetadata,
  AuditModule,
  AuditOutcome,
  AuditRetentionState,
  AuditSeverity,
} from "@/types/audit";

export type AuditLog = AuditEventDto;

export interface ListAuditLogsParams {
  page?: number;
  per_page?: number;
  module?: AuditModule;
  subfilter?: string;
  action?: string;
  severity?: AuditSeverity;
  outcome?: AuditOutcome;
  actor_id?: string;
  subject_id?: string;
  context_id?: string;
  start?: string; // YYYY-MM-DD
  end?: string;   // YYYY-MM-DD
}

export interface AuditLogListMeta extends PaginationMeta {
  filters: AuditFilterMetadata;
  capabilities: AuditCapabilities;
  retention: AuditRetentionState;
}

export class AuditLogServiceError extends Error {
  constructor(message: string, readonly status: number) {
    super(message);
    this.name = "AuditLogServiceError";
  }
}

function auditQuery(params: ListAuditLogsParams) {
  const qs = new URLSearchParams();
  if (params.page) qs.set("page", String(params.page));
  if (params.per_page) qs.set("per_page", String(params.per_page));
  if (params.module) qs.set("module", params.module);
  if (params.subfilter) qs.set("subfilter", params.subfilter);
  if (params.action) qs.set("action", params.action);
  if (params.severity) qs.set("severity", params.severity);
  if (params.outcome) qs.set("outcome", params.outcome);
  if (params.actor_id) qs.set("actor_id", params.actor_id);
  if (params.subject_id) qs.set("subject_id", params.subject_id);
  if (params.context_id) qs.set("context_id", params.context_id);
  if (params.start) qs.set("start", params.start);
  if (params.end) qs.set("end", params.end);
  return qs;
}

export async function listAuditLogs(
  params: ListAuditLogsParams = {},
  options: { signal?: AbortSignal } = {},
): Promise<{
  data: AuditLog[];
  meta: AuditLogListMeta;
}> {
  const qs = auditQuery(params);

  const res = await apiFetch(`/api/admin/audit-logs?${qs}`, {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
    signal: options.signal,
  }, { redirectOnUnauthorized: false });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new AuditLogServiceError(errorData.message || "Failed to fetch audit logs.", res.status);
  }

  const responseData = await res.json();
  return {
    data: responseData.data || [],
    meta: responseData.meta || {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1,
      filters: {
        modules: [],
        actions: [],
        outcomes: [],
        severities: [],
        module_subfilters: {} as Record<AuditModule, []>,
        module_actions: {} as Record<AuditModule, string[]>,
        module_counts: { all: 0, security_administration: 0, nutrition_care: 0, food_service_operations: 0, reports: 0 },
      },
      capabilities: {},
      retention: {
        enabled: false,
        source: "config",
        periods: { security: 365, clinical: 2190, operations: 1095, legacy: 90 },
      },
    },
  };
}

export async function updateAuditRetention(enabled: boolean): Promise<AuditRetentionState> {
  const res = await apiFetch("/api/admin/audit-retention", {
    method: "PUT",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ enabled }),
  }, { redirectOnUnauthorized: false });

  if (!res.ok) {
    throw new AuditLogServiceError("Unable to update scheduled deletion.", res.status);
  }

  const response = await res.json();
  return response.data;
}

export async function exportAuditLogs(params: ListAuditLogsParams = {}): Promise<Blob> {
  const qs = auditQuery(params);
  const res = await apiFetch(`/api/admin/audit-logs/export?${qs}`, {
    method: "GET",
    headers: { Accept: "text/csv" },
  }, { redirectOnUnauthorized: false });

  if (!res.ok) {
    throw new AuditLogServiceError("Audit export unavailable.", res.status);
  }

  const contentType = res.headers.get("Content-Type");
  if (contentType?.split(";", 1)[0].trim().toLowerCase() !== "text/csv") {
    throw new AuditLogServiceError("Audit export unavailable.", 502);
  }

  return res.blob();
}
