import type { PaginationMeta } from "@/components/ui/Pagination";
import { apiFetch } from "@/lib/apiFetch";

export type AppointmentSource = "scheduled" | "walk_in";
export type AppointmentStatus = "scheduled" | "in_progress" | "completed" | "ended_early" | "no_show" | "cancelled" | "rescheduled";

export interface NcpAppointment {
  id: string;
  patient_id: string;
  ncp_record_id: string | null;
  rescheduled_from_id?: string | null;
  source: AppointmentSource;
  status: AppointmentStatus;
  purpose: string;
  scheduled_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  reason_code: string | null;
  worked_on: string[];
  newly_completed: string[];
  administered_by?: { id: string; display_name: string } | null;
  patient?: { id: string; first_name?: string | null; last_name?: string | null; display_name?: string | null };
}

async function json<T>(response: Response, fallback: string): Promise<T> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? fallback);
  return (typeof body === "object" && body !== null && "data" in body ? body.data : body) as T;
}

export async function fetchPatientAppointments(
  patientId: string,
  page = 1,
  options: { scope?: "upcoming" | "past"; cycleScope?: "current" | "past" | "unassigned"; appointmentId?: string } = {},
) {
  const params = new URLSearchParams({ page: String(page), per_page: "5" });
  if (options.scope) params.set("scope", options.scope);
  if (options.cycleScope) params.set("cycle_scope", options.cycleScope);
  if (options.appointmentId) params.set("appointment_id", options.appointmentId);
  const response = await apiFetch(`/api/rnd/patients/${patientId}/appointments?${params}`);
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? "Failed to load appointments.");
  return { data: (body.data ?? []) as NcpAppointment[], meta: body.meta as PaginationMeta };
}

export async function fetchActiveAppointment(): Promise<NcpAppointment | null> {
  return json<NcpAppointment | null>(await apiFetch("/api/rnd/ncp-appointments/active"), "Failed to load active visit.");
}

export async function fetchUpcomingAppointments(page = 1, perPage = 3) {
  const response = await apiFetch(`/api/rnd/ncp-appointments/dashboard?page=${page}&per_page=${perPage}`);
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
  const appointment = await json<NcpAppointment>(await apiFetch(`/api/rnd/patients/${patientId}/appointments`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to create appointment.");
  notifyVisitChanged();
  return appointment;
}

export type NcpAppointmentTransition = NcpAppointment | { original: NcpAppointment; replacement: NcpAppointment };

export async function transitionAppointment(id: string, payload: Record<string, string>): Promise<NcpAppointmentTransition | null> {
  const response = await apiFetch(`/api/rnd/ncp-appointments/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload),
  });
  if (response.status === 204) {
    notifyVisitChanged();
    return null;
  }
  const appointment = await json<NcpAppointmentTransition>(response, "Failed to update appointment.");
  notifyVisitChanged();
  return appointment;
}

function notifyVisitChanged() {
  if (typeof window !== "undefined") window.dispatchEvent(new Event("ncp-visit-changed"));
}
