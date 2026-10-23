// @vitest-environment jsdom
import React, { act, Suspense } from "react";
import { createRoot, type Root } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import Page from "./page";
import * as diagnoses from "@/services/diagnosisService";
import { fetchPatientById } from "@/services/patientService";
import { fetchAssessment } from "@/services/assessmentService";
import { fetchIntervention } from "@/services/interventionService";

vi.mock("@/services/diagnosisService");
vi.mock("@/services/patientService");
vi.mock("@/services/assessmentService");
vi.mock("@/services/interventionService");
vi.mock("../../../_components/NcpPatientHeader", () => ({ default: () => <div>Fictional patient</div> }));
vi.mock("@/components/ncp/NcpBreadcrumb", () => ({ NcpBreadcrumb: () => null }));
let root: Root;
const click = (name: string | RegExp) => act(() => { fireEvent.click(screen.getByRole("button", { name })); });
const check = (name: string) => act(() => { fireEvent.click(screen.getByRole("checkbox", { name })); });
const enter = (name: string, value: string) => act(() => { fireEvent.change(screen.getByRole("textbox", { name }), { target: { value } }); });
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  const container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  vi.mocked(fetchPatientById).mockResolvedValue({ id: 1 } as never);
  vi.mocked(fetchAssessment).mockResolvedValue({} as never);
  vi.mocked(fetchIntervention).mockResolvedValue(null as never);
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 } });
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ data: [], meta: {
    cached: true, fingerprint: "snapshot", catalog_version: "v2", message: null,
    budget: { limit: 3, used: 0, remaining: 3, resets_at: "2099-10-21T00:00:00+08:00" }, can_generate: true, disabled_reason: null,
  } });
});
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); vi.resetAllMocks(); });
async function open() {
  const params = Promise.resolve({ patientId: "fictional", ncpId: "fictional-cycle" });
  await act(async () => { root.render(<Suspense fallback={<div>Loading</div>}><Page params={params} /></Suspense>); });
}

it("saves clinical Other components in PES while keeping all reasoning notes separate", async () => {
  await open(); click("Add New Diagnosis"); click("Clinical (NC)");
  check("Other clinical problem"); enter("Specify other clinical problem", "Nutrition-related meal timing difficulty");
  enter("Extra Notes (optional)", "General rationale only");
  click(/Next: Etiology/); check("Other etiology"); enter("Specify other etiology", "Shift schedule limits meal preparation");
  enter("Additional Etiology Notes", "Cause rationale only");
  click(/Next: Signs/); check("Other signs and symptoms"); enter("Specify other signs and symptoms", "Reports missing lunch on working days");
  enter("Additional Signs and Symptoms Notes", "Signs rationale only");
  click(/Next: PES/);
  const statement = (screen.getByRole("textbox", { name: "PES statement" }) as HTMLTextAreaElement).value;
  expect(statement).toBe("Nutrition-related meal timing difficulty related to Shift schedule limits meal preparation as evidenced by Reports missing lunch on working days");
  expect(statement).not.toContain("rationale");
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Save Diagnosis" })); });
  expect(diagnoses.storeDiagnosis).toHaveBeenCalledWith("fictional-cycle", expect.objectContaining({
    pes_statement: statement, extra_notes: "General rationale only",
    builder_data: expect.objectContaining({ etiology_notes: "Cause rationale only", sign_notes: "Signs rationale only" }),
  }));
});

it("keeps the generation button visible and disabled with an explanation after three uses", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...response, meta: { ...response.meta,
    budget: { ...response.meta.budget, used: 3, remaining: 0 }, can_generate: false,
    disabled_reason: "Daily limit reached (3/3). Available again after midnight Philippine time.",
  } });
  await open(); click("AI Review");
  expect((screen.getByRole("button", { name: "Generate AI Suggestions" }) as HTMLButtonElement).disabled).toBe(true);
  expect(screen.getByRole("button", { name: "Why AI generation is unavailable" })).toBeTruthy();
  expect(screen.getByRole("status").textContent).toContain("3/3 generations used today");
  expect(diagnoses.aiSuggestDiagnoses).not.toHaveBeenCalled();
});

it("opens a custom intake nutrient without classifying it as a vitamin", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...response, data: [{
    draft_id: "fictional-draft", candidate_id: "intake_other_v2", domain: "NI", label: "Excessive Sodium Intake",
    etiology: "Frequent use of salt", signs: "Uses salt with each meal", extra_notes: "Patient report documents the intake pattern.",
    builder_data: {
      problem_checks: [], problem_other: "", etiology_checks: [], etiology_other: "Frequent use of salt",
      sign_checks: [], sign_other: "Uses salt with each meal", etiology_notes: "", sign_notes: "",
      ni_direction: "Excessive", ni_nutrient: "Sodium",
    }, evidence_used: ["Dietary intake: Uses salt with each meal"],
    source: { id: "academy", issuer: "Academy", title: "Nutrition Diagnosis", version: "2026", location: "Diagnosis", url: "https://www.eatrightpro.org/" },
  }] });
  await open(); click("AI Review");
  expect((screen.getByRole("button", { name: "Generate AI Suggestions" }) as HTMLButtonElement).disabled).toBe(false);
  expect(screen.getByText('Extra Notes:')).toBeTruthy();
  expect(screen.queryByText('Reasoning:')).toBeNull();
  click("Edit");
  expect((screen.getByRole("textbox", { name: "Nutrient / substance (specify)" }) as HTMLInputElement).value).toBe("Sodium");
  expect(screen.queryByRole("textbox", { name: "Vitamins (specify)" })).toBeNull();
});
