import { beforeEach, describe, expect, it, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import { fetchPatientAppointments, fetchUpcomingAppointments } from "./ncpAppointmentService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));
const fetchMock = vi.mocked(apiFetch);

describe("ncpAppointmentService", () => {
  beforeEach(() => fetchMock.mockReset());

  it("requests independently paginated appointment history with scope and cycle filters", async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ data: [], meta: { current_page: 2, per_page: 5, total: 0, last_page: 1 } }), { status: 200 }));

    await fetchPatientAppointments("patient-1", 2, { scope: "past", cycleScope: "past", appointmentId: "visit-1" });

    expect(fetchMock).toHaveBeenCalledWith("/api/rnd/patients/patient-1/appointments?page=2&per_page=5&scope=past&cycle_scope=past&appointment_id=visit-1");
  });

  it("uses the explicit dashboard queue endpoint", async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ data: [], meta: { current_page: 1, per_page: 3, total: 0, last_page: 1 } }), { status: 200 }));

    await fetchUpcomingAppointments();

    expect(fetchMock).toHaveBeenCalledWith("/api/rnd/ncp-appointments/dashboard?page=1&per_page=3");
  });
});
