import type { PaginationMeta } from "@/components/ui/Pagination";
import { apiFetch } from "@/lib/apiFetch";

export type AppointmentSource = "scheduled" | "walk_in";
export type AppointmentStatus = "scheduled" | "in_progress" | "completed" | "ended_early" | "no_show" | "cancelled" | "rescheduled";

export interface NcpAppointment {
  id: string;
  patient_id: string;
  ncp_record_id: string | null;
  source: AppointmentSource;
  status: AppointmentStatus;
  purpose: string;
  scheduled_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  reason_code: string | null;
  worked_on: string[];
  newly_completed: string[];
  patient?: { id: string; first_name?: string | null; last_name?: string | null; display_name?: string | null };
}

async function json<T>(response: Response, fallback: string): Promise<T> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? fallback);
  return (typeof body === "object" && body !== null && "data" in body ? body.data : body) as T;
}

export async function fetchPatientAppointments(patientId: string, page = 1) {
  const response = await apiFetch(`/api/rnd/patients/${patientId}/appointments?page=${page}&per_page=5`);
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? "Failed to load appointments.");
  return { data: (body.data ?? []) as NcpAppointment[], meta: body.meta as PaginationMeta };
}

export async function fetchActiveAppointment(): Promise<NcpAppointment | null> {
  return json<NcpAppointment | null>(await apiFetch("/api/rnd/ncp-appointments/active"), "Failed to load active visit.");
}

export async function fetchUpcomingAppointments(page = 1, perPage = 3) {
  const response = await apiFetch(`/api/rnd/ncp-appointments?page=${page}&per_page=${perPage}`);
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? "Failed to load upcoming appointments.");
  return { data: (body.data ?? []) as NcpAppointment[], meta: body.meta as PaginationMeta };
}

export async function createAppointment(patientId: string, payload: {
  source: AppointmentSource;
  purpose: string;
  scheduled_at?: string;
  ncp_record_id?: string;
}): Promise<NcpAppointment> {
  return json<NcpAppointment>(await apiFetch(`/api/rnd/patients/${patientId}/appointments`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to create appointment.");
}

export async function transitionAppointment(id: string, payload: Record<string, string>): Promise<NcpAppointment | null> {
  const response = await apiFetch(`/api/rnd/ncp-appointments/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });
  if (response.status === 204) return null;
  return json<NcpAppointment>(response, "Failed to update appointment.");
}
