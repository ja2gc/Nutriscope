// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import { createAppointment, fetchPatientAppointments, transitionAppointment } from "@/services/ncpAppointmentService";
import { PatientAppointments } from "./PatientAppointments";

vi.mock("@/services/ncpAppointmentService", () => ({ fetchPatientAppointments: vi.fn(), createAppointment: vi.fn(), transitionAppointment: vi.fn() }));
vi.mock("next/link", () => ({ default: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a> }));
const fetchMock = vi.mocked(fetchPatientAppointments);
const createMock = vi.mocked(createAppointment);
const transitionMock = vi.mocked(transitionAppointment);
const meta = { current_page: 1, per_page: 5, total: 1, last_page: 1 };

describe("PatientAppointments", () => {
  let root: Root;
  let container: HTMLDivElement;
  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchMock.mockReset();
    createMock.mockReset();
    transitionMock.mockReset();
    container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  });
  afterEach(() => { act(() => root.unmount()); container.remove(); });

  it("shows separate upcoming and past records with purpose, source, status, actor, and linked cycle", async () => {
    fetchMock
      .mockResolvedValueOnce({ data: [{ id: "visit-1", patient_id: "patient-1", ncp_record_id: null, rescheduled_from_id: "visit-old", source: "scheduled", status: "scheduled", purpose: "Complete diagnosis", scheduled_at: "2026-09-08T10:00:00Z", started_at: null, finished_at: null, reason_code: null, worked_on: [], newly_completed: [], administered_by: null }], meta })
      .mockResolvedValueOnce({ data: [{ id: "visit-2", patient_id: "patient-1", ncp_record_id: "cycle-1", source: "walk_in", status: "ended_early", purpose: "Review progress", scheduled_at: null, started_at: "2026-09-07T10:00:00Z", finished_at: "2026-09-07T11:00:00Z", reason_code: "patient_left", worked_on: ["monitoring"], newly_completed: ["monitoring"], administered_by: { id: "rnd-1", display_name: "Dr. Ana Reyes" } }], meta });

    await act(async () => root.render(<PatientAppointments patientId="patient-1" currentNcpId="cycle-current" />));

    expect(container.textContent).toContain("Upcoming Appointments");
    expect(container.textContent).toContain("Past Appointments");
    expect(container.textContent).toContain("Purpose: Complete diagnosis");
    expect(container.textContent).toContain("Walk-in · Ended early");
    expect(container.textContent).toContain("Outcome reason: Patient Left");
    expect(container.textContent).toContain("Rescheduled replacement");
    expect(container.textContent).toContain("Administered by: Dr. Ana Reyes");
    expect(container.querySelector('a[href="/ncp/patient-1/assessment/cycle-1"]')).not.toBeNull();
    expect(container.textContent).toContain("Page 1 of 1");
  });

  it("starts a scheduled appointment against the selected current cycle", async () => {
    const visit = { id: "visit-1", patient_id: "patient-1", ncp_record_id: null, source: "scheduled" as const, status: "scheduled" as const, purpose: "Complete diagnosis", scheduled_at: "2026-09-08T10:00:00Z", started_at: null, finished_at: null, reason_code: null, worked_on: [], newly_completed: [], administered_by: null };
    fetchMock
      .mockResolvedValueOnce({ data: [visit], meta })
      .mockResolvedValueOnce({ data: [], meta: { ...meta, total: 0 } })
      .mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });
    transitionMock.mockResolvedValue({ ...visit, ncp_record_id: "cycle-current", status: "in_progress" });

    await act(async () => root.render(<PatientAppointments patientId="patient-1" currentNcpId="cycle-current" />));
    await act(async () => Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Start Visit")?.click());

    expect(transitionMock).toHaveBeenCalledWith("visit-1", { action: "start", ncp_record_id: "cycle-current" });
  });

  it("creates a walk-in from the patient appointment tab", async () => {
    const user = userEvent.setup();
    fetchMock.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });
    createMock.mockResolvedValue({ id: "walk-in-1", patient_id: "patient-1", ncp_record_id: "cycle-current", source: "walk_in", status: "in_progress", purpose: "Monitoring follow-up", scheduled_at: null, started_at: "2026-09-08T10:00:00Z", finished_at: null, reason_code: null, worked_on: [], newly_completed: [], administered_by: null });

    await act(async () => root.render(<PatientAppointments patientId="patient-1" currentNcpId="cycle-current" />));
    await user.click(Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Schedule or Walk-in"))!);
    await user.click(Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Walk-in")!);
    await user.type(container.querySelector('input[maxlength="255"]')!, "Monitoring follow-up");
    await user.click(Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Start Visit")!);

    expect(createMock).toHaveBeenCalledWith("patient-1", {
      source: "walk_in",
      purpose: "Monitoring follow-up",
      ncp_record_id: "cycle-current",
    });
  });
});
