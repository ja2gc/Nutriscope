import { apiFetch } from "@/lib/apiFetch";

/** One audit-trail entry for a subject record (Spec 5). */
export interface ActivityEvent {
  id: number;
  event: "created" | "updated" | "deleted" | string;
  description: string;
  subject_id: number;
  causer: string;
  changes: { old: Record<string, unknown>; new: Record<string, unknown> };
  created_at: string;
}

/**
 * Fetch a record's change history. `path` is the app API route, e.g.
 * `/api/fss/purchase-orders/12/activity` or `/api/rnd/patients/3/activity`.
 */
export async function getActivity(path: string): Promise<ActivityEvent[]> {
  const res = await apiFetch(path);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? "Failed to load history.");
  return (data as { data: ActivityEvent[] }).data;
}
