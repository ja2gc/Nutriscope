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
