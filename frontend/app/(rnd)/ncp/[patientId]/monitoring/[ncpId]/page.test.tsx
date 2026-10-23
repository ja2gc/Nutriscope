// @vitest-environment jsdom
import React, { act, Suspense } from "react";
import { createRoot, type Root } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import Page from "./page";
import * as monitoring from "@/services/monitoringService";
import { fetchAssessment } from "@/services/assessmentService";
import { fetchIntervention } from "@/services/interventionService";
import { fetchDiagnoses } from "@/services/diagnosisService";
import { fetchPatientById } from "@/services/patientService";

vi.mock("@/services/monitoringService", async (original) => ({
  ...await original<typeof import("@/services/monitoringService")>(),
  fetchMonitorings: vi.fn(), fetchMonitoringPlan: vi.fn(), fetchMonitoringContext: vi.fn(),
  fetchMonitoringAiBudget: vi.fn(), createMonitoring: vi.fn(), updateMonitoring: vi.fn(), deleteMonitoring: vi.fn(),
}));
vi.mock("@/services/assessmentService");
vi.mock("@/services/interventionService");
vi.mock("@/services/diagnosisService");
vi.mock("@/services/patientService");
vi.mock("../../../_components/NcpPatientHeader", () => ({ default: () => null }));
vi.mock("@/components/ncp/NcpBreadcrumb", () => ({ NcpBreadcrumb: () => null }));

const entry: monitoring.MonitoringEntry = {
  id: "visit-1", observed_at: "2026-09-28", visit_type: "inpatient_review",
  weight: 59, height: 165, bmi: 21.7, edema_present: false, dry_weight_kg: null,
  physical_activity_level: "moderate", pregnancy_lactation_status: "none",
  allergies: ["shellfish"], dietary_restrictions: "Low sodium", food_dislikes: ["okra"],
  lab_values: { hba1c: 6.8, micro_fiber: 22 }, intake_notes: "Meals tolerated", symptoms: "No nausea",
  goal_achievement: { compliance: "partial", gi_tolerance: "tolerating", continuation_decision: "continue" },
  clinical_summary: "Recorded visit summary", ai_decision: null, next_monitoring_date: "2026-10-28",
  created_at: "2026-09-28T08:00:00Z", updated_at: "2026-09-28T08:00:00Z",
};
let root: Root;
const click = async (name: string | RegExp) => act(async () => { fireEvent.click(screen.getByRole("button", { name })); });
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  const container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  vi.mocked(fetchAssessment).mockResolvedValue({ weight: 90, height: 180 } as never);
  vi.mocked(fetchIntervention).mockResolvedValue({ id: "plan-1" } as never);
  vi.mocked(fetchDiagnoses).mockResolvedValue([{}] as never);
  vi.mocked(fetchPatientById).mockResolvedValue({ patient_code: "DEMO-1" } as never);
  vi.mocked(monitoring.fetchMonitoringContext).mockResolvedValue({
    source_type: "monitoring", source_monitoring_id: "newer-visit", source_monitoring_date: "2026-10-20",
    weight: 65, height: 170, edema_present: false, dry_weight_kg: null,
    physical_activity_level: "light", pregnancy_lactation_status: "none", allergies: ["peanut"],
    dietary_restrictions: null, food_dislikes: [], age_years: 40, sex: "Female",
  });
  vi.mocked(monitoring.fetchMonitoringPlan).mockResolvedValue({ visits: [], pes_statements: [], goal_type: "custom", nutritional_status: null, indicators: [] });
  vi.mocked(monitoring.fetchMonitoringAiBudget).mockResolvedValue({ entry_key: "entry", remaining: 3 });
  vi.mocked(monitoring.fetchMonitorings).mockResolvedValue({ data: [entry], meta: { current_page: 1, per_page: 5, total: 6, last_page: 2 } });
});
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); vi.resetAllMocks(); });
async function open() {
  const params = Promise.resolve({ patientId: "demo", ncpId: "cycle-1" });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
}

it("edits the latest saved visit instead of creating another entry", async () => {
  vi.mocked(monitoring.fetchMonitorings).mockResolvedValue({ data: [{ ...entry, can_edit: true, can_delete: true }], meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 } });
  await open();
  await click(/Edit visit/);
  expect(screen.queryByText('View only')).toBeNull();
  const weight = screen.getByRole('spinbutton', { name: 'Weight kg' }) as HTMLInputElement;
  expect(weight.disabled).toBe(false);
  act(() => fireEvent.change(weight, { target: { value: '60' } }));
  await act(async () => { fireEvent.submit(screen.getByRole('button', { name: 'Save Visit' }).closest('form')!); });
  expect(monitoring.updateMonitoring).toHaveBeenCalledWith('cycle-1', 'visit-1', expect.objectContaining({ weight: 60, clinical_summary: 'Recorded visit summary', next_monitoring_date: '2026-10-28' }));
  expect(monitoring.createMonitoring).not.toHaveBeenCalled();
});

it("confirms deletion and reloads visit history", async () => {
  vi.mocked(monitoring.fetchMonitorings).mockResolvedValue({ data: [{ ...entry, can_edit: true, can_delete: true }], meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 } });
  await open();
  await click(/Delete visit/);
  expect(monitoring.deleteMonitoring).not.toHaveBeenCalled();
  await click('Delete');
  expect(monitoring.deleteMonitoring).toHaveBeenCalledWith('cycle-1', 'visit-1');
  expect(screen.queryByRole('dialog')).toBeNull();
  expect(monitoring.fetchMonitorings).toHaveBeenCalledTimes(2);
});

it("replaces history with the new form and protects edits when returning", async () => {
  await open();
  expect(screen.getAllByRole("columnheader").map((cell) => cell.textContent)).toEqual(["Date", "Visit type", "Decision", "Actions"]);
  expect(screen.queryByText("59 kg")).toBeNull();
  await click("Log New Visit");
  expect(screen.queryByRole("heading", { name: "Visit History" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Next page" })).toBeNull();
  expect((screen.getByRole("spinbutton", { name: "Weight kg" }) as HTMLInputElement).value).toBe("65");
  act(() => fireEvent.change(screen.getByRole("spinbutton", { name: "Weight kg" }), { target: { value: "66" } }));
  await click("Back to visit history");
  expect(screen.getByRole("dialog", { name: "Unsaved changes" })).toBeTruthy();
  await click("Discard");
  expect(screen.getByRole("heading", { name: "Visit History" })).toBeTruthy();
});

it("opens a saved visit in the same form with only its historical values and returns to its history page", async () => {
  vi.mocked(monitoring.fetchMonitorings).mockImplementation(async (_id, page = 1) => ({ data: [entry], meta: { current_page: page, per_page: 5, total: 6, last_page: 2 } }));
  await open();
  await click("Next page");
  await click(/View visit/);
  expect(screen.queryByRole("heading", { name: "Visit History" })).toBeNull();
  expect(screen.getByText("View only")).toBeTruthy();
  const weight = screen.getByRole("spinbutton", { name: "Weight kg" }) as HTMLInputElement;
  expect(weight.value).toBe("59");
  expect(weight.matches(":disabled")).toBe(true);
  expect((screen.getByRole("textbox", { name: "Progress Assessment" }) as HTMLTextAreaElement).value).toBe("Recorded visit summary");
  expect((screen.getByRole("spinbutton", { name: /Fiber intake/ }) as HTMLInputElement).value).toBe("22");
  expect(screen.queryByRole("button", { name: "Save Visit" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Generate AI Summary" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Delete Entry" })).toBeNull();
  await click("Back to visit history");
  expect(screen.getByRole("heading", { name: "Visit History" })).toBeTruthy();
  expect(screen.getByText("Page 2 of 2 · 6 items")).toBeTruthy();
});

it("keeps missing historical measurements blank instead of borrowing current values", async () => {
  vi.mocked(monitoring.fetchMonitorings).mockResolvedValue({ data: [{ ...entry, height: null, bmi: null, physical_activity_level: null, pregnancy_lactation_status: null }], meta: { current_page: 1, per_page: 5, total: 1, last_page: 1 } });
  await open(); await click(/View visit/);
  expect((screen.getByRole("spinbutton", { name: "Height cm" }) as HTMLInputElement).value).toBe("");
  expect((screen.getByRole("combobox", { name: "Physical Activity" }) as HTMLSelectElement).value).toBe("");
  expect((screen.getByRole("combobox", { name: "Pregnancy / Lactation" }) as HTMLSelectElement).value).toBe("");
});
