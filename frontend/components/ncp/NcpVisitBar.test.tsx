// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import {
  createAppointment,
  fetchActiveAppointment,
  fetchPatientAppointments,
  transitionAppointment,
} from "@/services/ncpAppointmentService";
import { NcpVisitBar } from "./NcpVisitBar";

vi.mock("@/services/ncpAppointmentService", () => ({
  createAppointment: vi.fn(),
  fetchActiveAppointment: vi.fn(),
  fetchPatientAppointments: vi.fn(),
  transitionAppointment: vi.fn(),
}));
vi.mock("next/link", () => ({
  default: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
}));

const activeMock = vi.mocked(fetchActiveAppointment);
const createMock = vi.mocked(createAppointment);
const appointmentsMock = vi.mocked(fetchPatientAppointments);
const transitionMock = vi.mocked(transitionAppointment);
const meta = { current_page: 1, per_page: 5, total: 1, last_page: 1 };

describe("NcpVisitBar", () => {
  let root: Root;
  let container: HTMLDivElement;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    activeMock.mockReset();
    createMock.mockReset();
    appointmentsMock.mockReset();
    transitionMock.mockReset();
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it("starts the patient's scheduled visit against the current cycle", async () => {
    activeMock.mockResolvedValue(null);
    appointmentsMock.mockResolvedValue({
      data: [{
        id: "visit-1", patient_id: "patient-1", ncp_record_id: null, source: "scheduled", status: "scheduled",
        purpose: "Complete assessment", scheduled_at: "2026-09-08T10:00:00Z", started_at: null, finished_at: null,
        reason_code: null, worked_on: [], newly_completed: [], administered_by: null,
      }],
      meta,
    });
    transitionMock.mockResolvedValue({
      id: "visit-1", patient_id: "patient-1", ncp_record_id: "cycle-1", source: "scheduled", status: "in_progress",
      purpose: "Complete assessment", scheduled_at: "2026-09-08T10:00:00Z", started_at: "2026-09-08T10:01:00Z",
      finished_at: null, reason_code: null, worked_on: [], newly_completed: [], administered_by: { id: "rnd-1", display_name: "Ana Reyes" },
    });

    await act(async () => root.render(<NcpVisitBar patientId="patient-1" ncpId="cycle-1" />));
    const start = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Start Scheduled Visit");
    expect(start).toBeDefined();
    await act(async () => start?.click());

    expect(transitionMock).toHaveBeenCalledWith("visit-1", { action: "start", ncp_record_id: "cycle-1" });
  });

  it("requires confirmation before finishing the active visit", async () => {
    const active = {
      id: "visit-2", patient_id: "patient-1", ncp_record_id: "cycle-1", source: "walk_in" as const, status: "in_progress" as const,
      purpose: "Review progress", scheduled_at: null, started_at: "2026-09-08T10:00:00Z", finished_at: null,
      reason_code: null, worked_on: ["monitoring"], newly_completed: [], administered_by: { id: "rnd-1", display_name: "Ana Reyes" },
    };
    activeMock.mockResolvedValue(active);
    appointmentsMock.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });
    transitionMock.mockResolvedValue({ ...active, status: "completed", finished_at: "2026-09-08T11:00:00Z" });

    await act(async () => root.render(<NcpVisitBar patientId="patient-1" ncpId="cycle-1" />));
    const finish = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Finish Visit");
    await act(async () => finish?.click());
    expect(transitionMock).not.toHaveBeenCalled();
    const confirm = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Confirm Finish");
    await act(async () => confirm?.click());

    expect(transitionMock).toHaveBeenCalledWith("visit-2", { action: "finish" });
  });

  it("keeps a different active patient visible and links back to that visit", async () => {
    activeMock.mockResolvedValue({
      id: "visit-other", patient_id: "patient-2", ncp_record_id: "cycle-2", source: "scheduled", status: "in_progress",
      purpose: "Complete diagnosis", scheduled_at: "2026-09-08T10:00:00Z", started_at: "2026-09-08T10:00:00Z",
      finished_at: null, reason_code: null, worked_on: [], newly_completed: [], administered_by: null,
      patient: { id: "patient-2", display_name: "Maria Santos" },
    });
    appointmentsMock.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });

    await act(async () => root.render(<NcpVisitBar patientId="patient-1" ncpId="cycle-1" />));

    expect(container.textContent).toContain("Another visit is active for Maria Santos");
    expect(container.querySelector('a[href="/ncp/patient-2/assessment/cycle-2"]')).not.toBeNull();
  });

  it("starts a walk-in against the current patient and cycle", async () => {
    const user = userEvent.setup();
    activeMock.mockResolvedValue(null);
    appointmentsMock.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });
    createMock.mockResolvedValue({
      id: "walk-in-1", patient_id: "patient-1", ncp_record_id: "cycle-1", source: "walk_in", status: "in_progress",
      purpose: "Review progress", scheduled_at: null, started_at: "2026-09-08T10:00:00Z", finished_at: null,
      reason_code: null, worked_on: [], newly_completed: [], administered_by: null,
    });

    await act(async () => root.render(<NcpVisitBar patientId="patient-1" ncpId="cycle-1" />));
    await user.click(Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Start Walk-in")!);
    await user.type(container.querySelector('input[maxlength="255"]')!, "Review progress");
    await user.click(Array.from(container.querySelectorAll("button")).find((button) => button.textContent === "Confirm and Start")!);

    expect(createMock).toHaveBeenCalledWith("patient-1", {
      source: "walk_in",
      purpose: "Review progress",
      ncp_record_id: "cycle-1",
    });
  });
});
