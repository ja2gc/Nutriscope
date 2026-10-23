// @vitest-environment jsdom
import React, { act, Suspense } from "react";
import { createRoot, type Root } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import Page from "./page";
import { fetchPatientById } from "@/services/patientService";
import { fetchAssessment } from "@/services/assessmentService";
import { fetchIntervention } from "@/services/interventionService";

vi.mock("@/services/patientService");
vi.mock("@/services/assessmentService");
vi.mock("@/services/interventionService");
vi.mock("../../../_components/NcpPatientHeader", () => ({ default: () => null }));
vi.mock("@/components/ncp/NcpBreadcrumb", () => ({ NcpBreadcrumb: () => null }));
let root: Root;
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  const container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  vi.mocked(fetchPatientById).mockResolvedValue({ id: "demo", dob: "1985-01-01", sex: "Male", name: "Fictional Patient", admission_date: "2026-10-20" } as never);
  vi.mocked(fetchAssessment).mockResolvedValue({ weight: 70, height: 170, usual_weight: 70, waist_cm: 90, hip_cm: 100, physical_activity_level: "light", pregnancy_lactation_status: "none", allergies: [], food_dislikes: [], medications: [] } as never);
  vi.mocked(fetchIntervention).mockResolvedValue(null as never);
});
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); vi.resetAllMocks(); });

it('lets the RND describe food recall using the existing saved dietary intake field', async () => {
  vi.mocked(fetchAssessment).mockResolvedValue({ dietary_intake: 'Skips breakfast on working days.', allergies: [], food_dislikes: [], medications: [] } as never);
  const params = Promise.resolve({ patientId: 'demo', ncpId: 'demo-cycle' });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
  await act(async () => fireEvent.click(screen.getByRole('tab', { name: /Dietary/ })));
  const notes = screen.getByRole('textbox', { name: 'Food recall notes' }) as HTMLTextAreaElement;
  expect(notes.value).toBe('Skips breakfast on working days.');
  act(() => fireEvent.change(notes, { target: { value: 'x'.repeat(251) } }));
  expect(notes.value).toHaveLength(250);
  act(() => fireEvent.change(notes, { target: { value: 'Recall describes a working day; breakfast was skipped.' } }));
  expect(notes.value).toContain('working day');
});

it("updates the visible WHR when hip changes and never labels a 1cm waist as low risk", async () => {
  const params = Promise.resolve({ patientId: "demo", ncpId: "demo-cycle" });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
  await act(async () => fireEvent.click(screen.getByRole("tab", { name: /Anthropometrics/ })));
  expect(screen.getByText("0.900")).toBeTruthy();
  act(() => fireEvent.change(screen.getByRole("spinbutton", { name: /Hip Circumference/ }), { target: { value: "120" } }));
  expect(screen.getByText("0.750")).toBeTruthy();
  act(() => fireEvent.change(screen.getByRole("spinbutton", { name: /Waist Circumference/ }), { target: { value: "1" } }));
  expect(screen.getByRole("alert").textContent).toContain("Waist circumference must be between 20 and 250 cm.");
  expect(screen.queryByText(/Low Risk · cut-off/)).toBeNull();
  expect(screen.queryByText("0.750")).toBeNull();
});

it('protects unsaved assessment changes when leaving from any tab', async () => {
  const params = Promise.resolve({ patientId: 'demo', ncpId: 'demo-cycle' });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
  expect(screen.queryByLabelText('Food Recall')).toBeNull();
  expect(screen.getByRole('button', { name: /Display Macros/ })).toBeTruthy();
  act(() => fireEvent.change(screen.getByRole('spinbutton', { name: /Energy intake/ }), { target: { value: '1800' } }));
  await act(async () => fireEvent.click(screen.getByRole('tab', { name: /Anthropometrics/ })));
  expect(screen.getByRole('button', { name: 'Save Assessment' })).toBeTruthy();
  const link = document.createElement('a'); link.href = '/ncp/patients'; document.body.append(link);
  act(() => fireEvent.click(link));
  expect(screen.getByRole('dialog', { name: 'Unsaved changes' })).toBeTruthy();
  act(() => fireEvent.click(screen.getByRole('button', { name: 'Stay on this page' })));
  expect(screen.queryByRole('dialog')).toBeNull();
  await act(async () => fireEvent.click(screen.getByRole('tab', { name: /Dietary/ })));
  expect((screen.getByRole('spinbutton', { name: /Energy intake/ }) as HTMLInputElement).value).toBe('1800');
});
