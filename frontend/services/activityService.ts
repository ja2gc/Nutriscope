import { apiFetch } from "@/lib/apiFetch";
import type { AuditEventDto } from "@/types/audit";

export interface ActivityPage {
  data: AuditEventDto[];
  meta: {
    next_before_id: string | null;
    has_more: boolean;
  };
}

function isStructuredEvent(value: unknown): value is AuditEventDto {
  if (typeof value !== "object" || value === null) return false;
  const event = value as Partial<AuditEventDto>;
  return typeof event.id === "string"
    && typeof event.category === "string"
    && typeof event.domain === "string"
    && typeof event.action === "string"
    && typeof event.action_label === "string"
    && typeof event.summary === "string"
    && typeof event.occurred_at === "string"
    && Array.isArray(event.details)
    && Array.isArray(event.changes);
}

export async function getActivity(
  path: string,
  beforeId?: string | null,
  options: { signal?: AbortSignal } = {},
): Promise<ActivityPage> {
  const url = new URL(path, "http://audit.local");
  if (beforeId) url.searchParams.set("before_id", beforeId);
  const requestPath = `${url.pathname}${url.search}`;
  const response = await apiFetch(requestPath, {
    headers: { Accept: "application/json" },
    signal: options.signal,
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error((payload as { message?: string }).message ?? "Failed to load history.");
  }

  const candidateData = (payload as { data?: unknown }).data;
  if (!Array.isArray(candidateData) || !candidateData.every(isStructuredEvent)) {
    throw new Error("Invalid activity trail response.");
  }
  const data = candidateData;
  const meta = (payload as { meta?: Partial<ActivityPage["meta"]> }).meta;

  return {
    data,
    meta: {
      next_before_id: meta?.next_before_id === null || meta?.next_before_id === undefined
        ? null
        : String(meta.next_before_id),
      has_more: meta?.has_more === true,
    },
  };
}
