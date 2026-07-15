import { apiFetch } from "@/lib/apiFetch";
import type { AuditHistoryDto } from "@/types/auditHistory";

export class AuditHistoryServiceError extends Error {
  constructor(message: string, readonly status: number) {
    super(message);
    this.name = "AuditHistoryServiceError";
  }
}

export async function getAuditHistory(id: string, signal?: AbortSignal): Promise<AuditHistoryDto> {
  const response = await apiFetch(`/api/admin/audit-logs/${encodeURIComponent(id)}/history`, {
    method: "GET",
    headers: { Accept: "application/json" },
    signal,
    cache: "no-store",
  }, { redirectOnUnauthorized: false });

  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new AuditHistoryServiceError(body.message || "Audit history unavailable.", response.status);
  }

  const body = await response.json();
  return body.data as AuditHistoryDto;
}
