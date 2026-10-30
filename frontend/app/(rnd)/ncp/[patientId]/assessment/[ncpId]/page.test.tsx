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
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); window.history.replaceState(null, "", "/"); vi.resetAllMocks(); });

it("keeps the default assessment tab for an unrelated section query", async () => {
  window.history.replaceState(null, "", "/?section=biochemical");
  const params = Promise.resolve({ patientId: "demo", ncpId: "demo-cycle" });
  await act(async () => root.render(<Suspense><Page params={params} /></Suspense>));
  expect(screen.getByRole("tab", { name: /Dietary/i }).getAttribute("aria-selected")).toBe("true");
});

it("requires retry after a failed assessment lookup instead of offering a new assessment", async () => {
  vi.mocked(fetchAssessment).mockRejectedValueOnce(new Error("Connection failed"));
  const params = Promise.resolve({ patientId: "demo", ncpId: "demo-cycle" });
  await act(async () => root.render(<Suspense><Page params={params} /></Suspense>));
  expect(screen.getByRole("alert").textContent).toContain("Could not load assessment");
  expect(screen.queryByRole("tab", { name: /Dietary/ })).toBeNull();
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Try again" })));
  expect(screen.getByRole("tab", { name: /Dietary/ })).toBeTruthy();
});

it("allows a genuinely missing assessment to be created", async () => {
  vi.mocked(fetchAssessment).mockRejectedValueOnce(Object.assign(new Error("Not found"), { status: 404 }));
  const params = Promise.resolve({ patientId: "demo", ncpId: "new-cycle" });
  await act(async () => root.render(<Suspense><Page params={params} /></Suspense>));
  expect(screen.getByRole("tab", { name: /Dietary/ })).toBeTruthy();
  expect(screen.queryByRole("alert")).toBeNull();
});

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

it("generates from the edited referral while preserving manual text and Undo", async () => {
  vi.mocked(fetchPatientById).mockResolvedValue({ id: "demo", dob: "1985-01-01", sex: "Male", medical_diagnosis: "Recorded diabetes" } as never);
  vi.mocked(fetchAssessment).mockResolvedValue({ weight: 70, height: 170, pregnancy_lactation_status: "none", physical_activity_level: "light", rnd_summary: "Clinician's existing summary", allergies: [], food_dislikes: [], medications: [] } as never);
  const params = Promise.resolve({ patientId: "demo", ncpId: "demo-cycle" });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
  await act(async () => fireEvent.click(screen.getByRole("tab", { name: /Referral/ })));
  act(() => fireEvent.change(screen.getByDisplayValue("Recorded diabetes"), { target: { value: "Suspected diabetes under review" } }));
  await act(async () => fireEvent.click(screen.getByRole("tab", { name: /Summary/ })));
  const summary = screen.getByRole("textbox", { name: "RND Summary (Clinical Observations)" }) as HTMLTextAreaElement;
  expect(summary.value).toBe("Clinician's existing summary");
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Regenerate Summary" })));
  expect(summary.value).toContain("Referral diagnosis: Suspected diabetes under review");
  expect(summary.value).toContain("BMI");
  expect(summary.value).toContain("Activity estimate: Lightly active");
  expect(summary.value).not.toContain("Recorded diabetes");
  await act(async () => fireEvent.click(screen.getByRole("tab", { name: /Referral/ })));
  act(() => fireEvent.change(screen.getByDisplayValue("Suspected diabetes under review"), { target: { value: "Referral diagnosis updated" } }));
  await act(async () => fireEvent.click(screen.getByRole("tab", { name: /Summary/ })));
  expect(screen.getByText("Assessment changed - regenerate to refresh")).toBeTruthy();
  expect((screen.getByRole("textbox", { name: "RND Summary (Clinical Observations)" }) as HTMLTextAreaElement).value).toContain("Suspected diabetes under review");
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Undo" })));
  expect((screen.getByRole("textbox", { name: "RND Summary (Clinical Observations)" }) as HTMLTextAreaElement).value).toBe("Clinician's existing summary");
});

it("requires an explicit RND selection to score recorded weight loss", async () => {
  vi.mocked(fetchPatientById).mockResolvedValue({ id: "demo", dob: "1985-01-01", sex: "Male", screening_type: "adult" } as never);
  vi.mocked(fetchAssessment).mockResolvedValue({ weight: 68.6, height: 170, usual_weight: 70, weight_loss_percentage: 2, weight_change_period_value: 4, weight_change_period_unit: "months", pregnancy_lactation_status: "none", allergies: [], food_dislikes: [], medications: [] } as never);
  const params = Promise.resolve({ patientId: "demo", ncpId: "demo-cycle" });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
  await act(async () => fireEvent.click(screen.getByRole("tab", { name: /Summary/ })));
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Generate Summary" })));
  expect((screen.getByRole("textbox", { name: "RND Summary (Clinical Observations)" }) as HTMLTextAreaElement).value).toMatch(/nutrition screening: Low Risk/i);
  const factor = screen.getByText("Unintentional weight loss over weeks/months").parentElement?.querySelector("input") as HTMLInputElement;
  expect(factor.checked).toBe(false);
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Manual edit" })));
  await act(async () => fireEvent.click(factor));
  expect(factor.checked).toBe(true);
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Automatic" })));
  expect(factor.checked).toBe(false);
});

it("uses a full-width tab panel without a mobile card shell", async () => {
  const params = Promise.resolve({ patientId: "demo", ncpId: "demo-cycle" });
  await act(async () => root.render(<Suspense fallback="Loading"><Page params={params} /></Suspense>));
  const tablist = screen.getByRole("tablist", { name: "Assessment categories" });
  const panel = screen.getByRole("tabpanel");

  expect(tablist.parentElement?.className).toContain("border-0");
  expect(tablist.parentElement?.className).toContain("sm:border");
  expect(tablist.parentElement?.className).toContain("bg-white");
  expect(tablist.className).toContain("flex-nowrap");
  expect(tablist.className).toContain("overflow-y-hidden");
  expect(panel.className).toContain("p-0");
  expect(panel.className).toContain("sm:p-4");
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
