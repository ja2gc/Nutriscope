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
const click = (name: string | RegExp) => act(() => { fireEvent.click(screen.queryByRole("button", { name }) ?? screen.getByRole("tab", { name })); });
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
    budget: { limit: 10, used: 0, remaining: 10, resets_at: "2099-10-21T00:00:00+08:00" }, can_generate: true, disabled_reason: null,
  } });
});
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); vi.resetAllMocks(); });
async function open(patientId = "fictional") {
  const params = Promise.resolve({ patientId, ncpId: patientId === "select-patient" ? "select-ncp" : "fictional-cycle" });
  await act(async () => { root.render(<Suspense fallback={<div>Loading</div>}><Page params={params} /></Suspense>); });
}

it("separates unverified proposals and offers editing rather than direct approval", async () => {
  const state = await diagnoses.fetchPesDraftStatus("fixture");
  const proposal = { draft_id: "raw-review", domain: "NB", label: "Physical inactivity", etiology: "Proposed cause", signs: "Proposed finding",
    builder_data: {}, extra_notes: "", evidence_used: [], review_required: true, review_reasons: ["Check the supporting findings."] };
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...state, review_drafts: [proposal] } as never);
  await open(); click("AI Review");
  const section = screen.getByRole("region", { name: "May need more thorough review" });
  expect(section.textContent).toContain("Check the supporting findings.");
  expect(section.textContent).toContain("Physical inactivity related to Proposed cause as evidenced by Proposed finding");
  expect(screen.queryByText("P:", { exact: true })).toBeNull();
  expect(section.querySelectorAll("button").length).toBeGreaterThan(0);
  expect(screen.queryByRole("button", { name: "Accept" })).toBeNull();
  expect(screen.getByRole("button", { name: "Review and edit" })).toBeTruthy();
});

it("uses the shared patient-selection action without loading patient data", async () => {
  await open("select-patient");
  expect(screen.getByRole("heading", { name: "No Patient Selected" })).toBeTruthy();
  expect(screen.getByRole("link", { name: "Go to Patients" }).getAttribute("href")).toBe("/ncp/patients");
  expect(fetchAssessment).not.toHaveBeenCalled();
});

it("shows saved diagnoses as a clamped PES statement without repeated component columns", async () => {
  const result = await diagnoses.fetchDiagnosesPage("fixture");
  const pes = "Physical inactivity related to pain limiting walking as evidenced by stopped nightly walks";
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ ...result, data: [{
    id: "saved", domain: "NB", problem: "Physical inactivity", etiology: "Pain limiting walking",
    signs_symptoms: "Stopped nightly walks", pes_statement: pes,
  }] } as never);
  await open();
  expect(screen.queryByRole("columnheader", { name: "Problem" })).toBeNull();
  expect(screen.queryByRole("columnheader", { name: "Etiology" })).toBeNull();
  expect(screen.queryByRole("columnheader", { name: "S&S" })).toBeNull();
  expect(screen.getByText(pes).classList.contains("line-clamp-3")).toBe(true);
});

it("shows only the current generation failure and clears it after five seconds", async () => {
  vi.useFakeTimers();
  try {
    const state = await diagnoses.fetchPesDraftStatus("fixture");
    const rejected = { ...state, meta: { ...state.meta, can_generate: false, can_retry: true, review_outcome: "rejected" as const } };
    vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue(rejected);
    vi.mocked(diagnoses.aiSuggestDiagnoses).mockResolvedValue(rejected);
    await open(); click("AI Review");
    expect(screen.queryByText(/Could not generate AI suggestions/)).toBeNull();
    await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Generate AI Suggestions" })); });
    expect(screen.getByText(/Could not generate AI suggestions/)).toBeTruthy();
    await act(async () => { vi.advanceTimersByTime(5000); });
    expect(screen.queryByText(/Could not generate AI suggestions/)).toBeNull();
  } finally { vi.useRealTimers(); }
});

it("opens every problem group when creating a diagnosis", async () => {
  await open(); click("Add New Diagnosis"); click("Clinical (NC)");
  for (const group of ["Functional", "Biochemical", "Weight and growth"]) {
    expect((screen.getByText(group).closest("details") as HTMLDetailsElement).open).toBe(true);
  }
  click("Behavioral-Env (NB)");
  for (const group of ["Knowledge and beliefs", "Physical activity and function", "Food safety and access"]) {
    expect((screen.getByText(group).closest("details") as HTMLDetailsElement).open).toBe(true);
  }
});

it("shows plain evidence for each PES component without field navigation links", async () => {
  const state = await diagnoses.fetchPesDraftStatus("fixture");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...state, data: [{ draft_id: "component-draft", domain: "NB",
    label: "Food- and nutrition-related knowledge deficit", etiology: "Limited nutrition education", signs: "Cannot identify carbohydrate foods",
    builder_data: {}, evidence_used: ["Legacy evidence"], evidence_by_component: {
      problem: [{ key: "knowledge_notes", text: "Knowledge notes: Cannot identify carbohydrate foods" }],
      etiology: [{ key: "social_history", text: "Social history: Limited nutrition education" }],
      signs: [{ key: "knowledge_notes", text: "Knowledge notes: Cannot identify carbohydrate foods" }],
    } }] } as never);
  await open(); click(/AI Review/);
  expect(screen.getByText("P evidence")).toBeTruthy();
  expect(screen.getByText("E evidence")).toBeTruthy();
  expect(screen.getByText("S evidence")).toBeTruthy();
  expect(screen.getByText("Social history")).toBeTruthy();
  expect(screen.queryByText("Social history: Limited nutrition education")).toBeNull();
  expect(screen.queryByRole("link", { name: "Social history" })).toBeNull();
  expect(screen.queryByText("Legacy evidence")).toBeNull();
});

it.each([
  ["NC", "Overweight/obesity", "Weight and growth", "Functional"],
  ["NB", "Physical inactivity", "Physical activity and function", "Knowledge and beliefs"],
])("opens only selected problem groups while editing %s", async (domain, problem, selectedGroup, otherGroup) => {
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ data: [{ id: 12, domain, problem,
    etiology: "Recorded cause", signs_symptoms: "Recorded findings" }],
    meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } } as never);
  await open(); act(() => { fireEvent.click(screen.getByTitle("Edit")); });
  expect((screen.getByText(selectedGroup).closest("details") as HTMLDetailsElement).open).toBe(true);
  expect((screen.getByText(otherGroup).closest("details") as HTMLDetailsElement).open).toBe(false);
});

it("shows symptom concerns separately without a diagnosis approval action", async () => {
  const result = await diagnoses.fetchPesDraftStatus("fixture");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...result, concerns: [{ concern_id: "concern", label: "Vitamin A inadequacy concern", missing: "Clarify intake history", evidence_used: ["Physical findings: Night blindness"] }] });
  await open(); click("AI Review");
  expect(screen.getByText("Possible concerns (1)")).toBeTruthy();
  expect(screen.getByRole("link", { name: "Review assessment", hidden: true })).toBeTruthy();
  expect(screen.queryByRole("button", { name: "Accept" })).toBeNull();
});

it("blocks editing when required clinical context fails and offers retry", async () => {
  vi.mocked(fetchAssessment).mockRejectedValueOnce(new Error("Connection failed"));
  await open();
  expect(screen.getByRole("alert").textContent).toContain("Could not load diagnosis");
  expect(screen.queryByRole("button", { name: "Add New Diagnosis" })).toBeNull();
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Try again" })));
  expect(screen.getByRole("button", { name: "Add New Diagnosis" })).toBeTruthy();
});

it("allows manual diagnosis while AI status is still downloading", async () => {
  vi.mocked(diagnoses.fetchPesDraftStatus).mockReturnValue(new Promise(() => {}));
  await open();
  expect(screen.getByRole("button", { name: "Add New Diagnosis" })).toBeTruthy();
  click("AI Review");
  expect(screen.getByText("Loading AI status…").getAttribute("role")).toBe("status");
  click("Diagnosis Table");
  click("Add New Diagnosis");
  expect(screen.getByRole("button", { name: "Protein-energy" })).toBeTruthy();
});

it("keeps manual diagnosis available when AI status fails", async () => {
  vi.mocked(diagnoses.fetchPesDraftStatus).mockRejectedValue(new Error("Network unavailable"));
  await open();
  expect(screen.getByRole("button", { name: "Add New Diagnosis" })).toBeTruthy();
  click("AI Review");
  expect(screen.getByRole("button", { name: "Try again" })).toBeTruthy();
});

it("offers protein-energy intake only in the standardized inadequate direction", async () => {
  await open(); click("Add New Diagnosis"); click("Protein-energy");
  expect((screen.getByRole("button", { name: "Excessive" }) as HTMLButtonElement).disabled).toBe(true);
});

it("uses the supplied vitamin names for new selections", async () => {
  await open(); click("Add New Diagnosis"); click("Vitamins");
  expect(screen.getByRole("option", { name: "Thiamin" })).toBeTruthy();
  expect(screen.queryByRole("option", { name: "Vitamin B1 (Thiamin)" })).toBeNull();
});

it("reopens a composed intake problem with its saved selections", async () => {
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ data: [{ id: "saved", domain: "NI", problem: "Increased nutrient needs: Protein",
    etiology: "Recorded cause", signs_symptoms: "Recorded findings",
    builder_data: { ni_pattern: "needs", ni_direction: "Inadequate", ni_nutrient: "Protein", ni_group: "", ni_detail: "",
      problem_checks: [], problem_other: "", etiology_checks: [], etiology_other: "Recorded cause", sign_checks: [], sign_other: "Recorded findings", etiology_notes: "", sign_notes: "" } }],
    meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } } as never);
  await open();
  act(() => { fireEvent.click(screen.getByTitle("Edit")); });
  expect((screen.getByRole("combobox", { name: "Intake problem family" }) as HTMLSelectElement).value).toBe("needs");
  expect(screen.getByRole("button", { name: "Protein", pressed: true })).toBeTruthy();
});

it("maps a renamed legacy problem to the current checkbox without rewriting its saved wording", async () => {
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ data: [{ id: "legacy", domain: "NB", problem: "Food and Nutrition Knowledge Deficit",
    etiology: "Limited access to nutrition education", signs_symptoms: "Cannot identify carbohydrate foods",
    builder_data: { problem_checks: ["Food and Nutrition Knowledge Deficit"], problem_other: "", etiology_checks: [], etiology_other: "Limited access to nutrition education", sign_checks: [], sign_other: "Cannot identify carbohydrate foods", etiology_notes: "", sign_notes: "" } }],
    meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } } as never);
  await open();
  act(() => { fireEvent.click(screen.getByTitle("Edit")); });
  expect((screen.getByRole("checkbox", { name: "Food- and nutrition-related knowledge deficit", hidden: true }) as HTMLInputElement).checked).toBe(true);
  click(/Next: Etiology/); enter("Specify other etiology", "New documented education barrier");
  click(/Next: Signs/); click(/Next: PES/);
  expect((screen.getByRole("textbox", { name: "PES statement" }) as HTMLTextAreaElement).value).toContain("Food and Nutrition Knowledge Deficit related to New documented education barrier");
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Update Diagnosis" })); });
  expect(diagnoses.updateDiagnosis).toHaveBeenCalledWith("fictional-cycle", "legacy", expect.objectContaining({ problem: "Food and Nutrition Knowledge Deficit", builder_data: expect.objectContaining({ problem_override: "Food and Nutrition Knowledge Deficit" }) }));
});

it("keeps the NO warning visible for both add and generate until the saved decision is deleted", async () => {
  const warning = "Delete the saved 'No nutrition diagnosis at this time' entry before adding or generating a diagnosis.";
  const status = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ data: [{ id: "no-decision", domain: "NO", problem: "No nutrition diagnosis at this time", etiology: "", signs_symptoms: "", pes_statement: "No nutrition diagnosis at this time" }], meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 } } as never);
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...status, meta: { ...status.meta, can_generate: false, blocked_by_no_diagnosis: true, disabled_reason: warning } });
  await open();
  expect(screen.getByRole("alert").textContent).toContain(warning);
  expect((screen.getByRole("button", { name: "Add New Diagnosis" }) as HTMLButtonElement).disabled).toBe(true);
  click("AI Review");
  expect((screen.getByRole("button", { name: "Generate AI Suggestions" }) as HTMLButtonElement).disabled).toBe(true);
  expect(screen.getByRole("alert").textContent).toContain(warning);
  expect(diagnoses.aiSuggestDiagnoses).not.toHaveBeenCalled();
  click("Diagnosis Table");
  vi.spyOn(window, "confirm").mockReturnValue(true);
  vi.mocked(diagnoses.fetchDiagnosesPage).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 } });
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue(status);
  await act(async () => { fireEvent.click(screen.getByTitle("Delete")); });
  expect(screen.queryByText(warning)).toBeNull();
  expect((screen.getByRole("button", { name: "Add New Diagnosis" }) as HTMLButtonElement).disabled).toBe(false);
  vi.restoreAllMocks();
});

it("saves an explicit NO decision directly without etiology or signs", async () => {
  await open(); click("Add New Diagnosis"); click("Other (NO)");
  expect(screen.queryByRole("tab", { name: /E — Etiology/ })).toBeNull();
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Save Diagnosis" })); });
  expect(diagnoses.storeDiagnosis).toHaveBeenCalledWith("fictional-cycle", expect.objectContaining({ domain: "NO", problem: "No nutrition diagnosis at this time", etiology: "", signs_symptoms: "", pes_statement: "No nutrition diagnosis at this time" }));
});

it("keeps diagnosis tabs selected in a flat, full-width mobile panel", async () => {
  await open();
  const tablist = screen.getByRole("tablist", { name: "Diagnosis sections" });
  const panel = screen.getByRole("tabpanel");
  const diagnosisTable = screen.getByRole("tab", { name: /Diagnosis Table/ });

  expect(tablist.parentElement?.className).toContain("border-0");
  expect(tablist.parentElement?.className).toContain("sm:border");
  expect(tablist.parentElement?.className).toContain("bg-white");
  expect(tablist.className).toContain("flex-nowrap");
  expect(tablist.className).toContain("overflow-y-hidden");
  expect(panel.className).toContain("px-0");
  expect(panel.className).toContain("sm:p-5");
  expect(diagnosisTable.getAttribute("aria-selected")).toBe("true");
  await act(async () => { fireEvent.click(screen.getByRole("tab", { name: /P — Problem/ })); });
  expect(screen.getByRole("tab", { name: /P — Problem/ }).getAttribute("aria-selected")).toBe("true");
});

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

it("keeps the generation button visible and disabled with an explanation after ten requests", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...response, meta: { ...response.meta,
    budget: { ...response.meta.budget, used: 10, remaining: 0 }, can_generate: false,
    disabled_reason: "Ten requests per ten minutes reached. Try again when the allowance resets.",
  } });
  await open(); click("AI Review");
  expect((screen.getByRole("button", { name: "Generate AI Suggestions" }) as HTMLButtonElement).disabled).toBe(true);
  expect(screen.getByRole("button", { name: "AI suggestion status" })).toBeTruthy();
  expect(screen.getByRole("status").textContent).toContain("10/10 diagnosis requests used in this 10-minute window");
  expect(diagnoses.aiSuggestDiagnoses).not.toHaveBeenCalled();
});

it.each([
  { nutrient: "Sodium", role: "combobox", label: "Mineral" },
  { nutrient: "Vitamin D3", role: "combobox", label: "Vitamin" },
  { nutrient: "Choline", role: "textbox", label: "Nutrient / substance (specify)" },
])("preserves $nutrient in its appropriate edit control", async ({ nutrient, role, label }) => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...response, data: [{
    draft_id: "fictional-draft", candidate_id: "intake_other_v2", domain: "NI", label: `Excessive ${nutrient} Intake`,
    etiology: "Frequent use of salt", signs: "Uses salt with each meal", extra_notes: "Patient report documents the intake pattern.",
    builder_data: {
      problem_checks: [], problem_other: "", etiology_checks: [], etiology_other: "Frequent use of salt",
      sign_checks: [], sign_other: "Uses salt with each meal", etiology_notes: "", sign_notes: "",
      ni_direction: "Excessive", ni_nutrient: nutrient,
    }, evidence_used: ["Dietary intake: Uses salt with each meal"],
    source: { id: "academy", issuer: "Academy", title: "Nutrition Diagnosis", version: "2026", location: "Diagnosis", url: "https://www.eatrightpro.org/" },
  }] });
  await open(); click("AI Review");
  expect((screen.getByRole("button", { name: "Generate AI Suggestions" }) as HTMLButtonElement).disabled).toBe(false);
  expect(screen.getByText('Extra Notes:')).toBeTruthy();
  expect(screen.queryByText('Reasoning:')).toBeNull();
  click("Edit");
  expect((screen.getByRole(role, { name: label }) as HTMLInputElement).value).toBe(nutrient);
  expect(screen.queryByRole("textbox", { name: "Vitamins (specify)" })).toBeNull();
});

it("selects vitamins and minerals from dropdowns and saves the exact nutrient", async () => {
  await open(); click("Add New Diagnosis"); click("Vitamins");
  const vitamin = screen.getByRole("combobox", { name: "Vitamin" }) as HTMLSelectElement;
  expect(screen.queryByRole("textbox", { name: /Vitamin/i })).toBeNull();
  act(() => { fireEvent.change(vitamin, { target: { value: "Vitamin C" } }); });
  expect(screen.getByText("Inadequate vitamin intake: C")).toBeTruthy();
  click("Minerals");
  const mineral = screen.getByRole("combobox", { name: "Mineral" }) as HTMLSelectElement;
  expect(mineral.value).toBe("");
  expect(screen.queryByRole("combobox", { name: "Vitamin" })).toBeNull();
  act(() => { fireEvent.change(mineral, { target: { value: "Iron" } }); });
  expect(screen.getByText("Inadequate mineral intake: Iron")).toBeTruthy();
  click(/Next: Etiology/); check("Other etiology"); enter("Specify other etiology", "Limited access to iron-rich foods");
  click(/Next: Signs/); check("Other signs and symptoms"); enter("Specify other signs and symptoms", "Reported iron intake below requirements");
  click(/Next: PES/);
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Save Diagnosis" })); });
  expect(diagnoses.storeDiagnosis).toHaveBeenCalledWith("fictional-cycle", expect.objectContaining({
    problem: "Inadequate mineral intake: Iron",
  }));
});

it("retries rejected output with the single Generate action without generating on page open", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue({ ...response, meta: {
    ...response.meta, can_generate: false, can_retry: true, review_outcome: "rejected",
    message: "The response did not pass evidence checks. Retry explicitly or update the Assessment.",
    disabled_reason: "The response did not pass evidence checks. Retry explicitly or update the Assessment.",
  } });
  vi.mocked(diagnoses.aiSuggestDiagnoses).mockResolvedValue(response);
  await open(); click("AI Review");
  expect(diagnoses.aiSuggestDiagnoses).not.toHaveBeenCalled();
  expect(screen.queryByText("Could not generate AI suggestions. Try again or add a diagnosis manually.")).toBeNull();
  expect(screen.queryByText(/Review the Assessment findings before trying again/)).toBeNull();
  expect(screen.queryByText(/did not pass evidence checks/)).toBeNull();
  expect(screen.queryByText(/No additional applicable nutrition diagnosis was found/)).toBeNull();
  expect(screen.queryByRole("button", { name: "AI suggestion status" })).toBeNull();
  expect(screen.queryByRole("button", { name: /Review again/ })).toBeNull();
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Generate AI Suggestions" })); });
  expect(diagnoses.aiSuggestDiagnoses).toHaveBeenCalledExactlyOnceWith("fictional-cycle", true);
});

it("keeps Generate clickable after evidence is exhausted and warns on each requested check", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  const exhausted = { ...response, meta: { ...response.meta, can_generate: false, can_retry: false,
    message: 'No supported evidence left.', disabled_reason: 'No supported evidence left. Update the Assessment when new findings are available.' } };
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue(exhausted);
  vi.mocked(diagnoses.aiSuggestDiagnoses).mockResolvedValue(exhausted);
  await open(); click('AI Review');
  const button = screen.getByRole('button', { name: 'Generate AI Suggestions' }) as HTMLButtonElement;
  expect(button.disabled).toBe(false);
  expect(diagnoses.aiSuggestDiagnoses).not.toHaveBeenCalled();
  await act(async () => { fireEvent.click(button); });
  expect(screen.getByRole('alert').textContent).toContain('No additional nutrition diagnosis found.');
  expect(screen.queryByText(/No supported evidence left/)).toBeNull();
  expect(button.disabled).toBe(false);
  expect(diagnoses.aiSuggestDiagnoses).toHaveBeenCalledExactlyOnceWith('fictional-cycle', false);
});

it("reuses valid partial drafts through the single Generate action", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  const warning = "The response did not pass evidence checks. Retry explicitly or update the Assessment.";
  const partial: diagnoses.PesDraftResult = { ...response, data: [{
    draft_id: "fictional-draft", candidate_id: "knowledge_deficit_v2", domain: "NB", label: "Food and Nutrition Knowledge Deficit",
    etiology: "Limited access to nutrition education", signs: "Cannot identify carbohydrate foods", extra_notes: "",
    builder_data: { problem_checks: [], problem_other: "", etiology_checks: [], etiology_other: "Limited access to nutrition education",
      sign_checks: [], sign_other: "Cannot identify carbohydrate foods", etiology_notes: "", sign_notes: "" },
    evidence_used: ["Knowledge notes: Cannot identify carbohydrate foods"],
    source: { id: "academy", issuer: "Academy", title: "Nutrition Diagnosis", version: "2026", location: "Diagnosis", url: "https://www.eatrightpro.org/" },
  }], meta: { ...response.meta, can_generate: false, can_retry: true, review_outcome: "partial", disabled_reason: warning } };
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue(partial);
  vi.mocked(diagnoses.aiSuggestDiagnoses).mockResolvedValue(partial);
  await open(); click("AI Review");
  expect(screen.getByText(/^Food and Nutrition Knowledge Deficit related to/)).toBeTruthy();
  expect(screen.queryByText(warning, { exact: false })).toBeNull();
  expect(screen.queryByRole("button", { name: "AI suggestion status" })).toBeNull();
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Generate AI Suggestions" })); });
  expect(screen.queryByText(warning, { exact: false })).toBeNull();
  expect(diagnoses.aiSuggestDiagnoses).toHaveBeenCalledExactlyOnceWith("fictional-cycle", false);
  expect(screen.queryByRole("button", { name: /Review again/ })).toBeNull();
});

it("does not turn a completed review with omitted candidates into a paid retry", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  const complete = { ...response, data: [], meta: { ...response.meta,
    can_generate: false, can_retry: true, review_outcome: "complete" as const } };
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue(complete);
  vi.mocked(diagnoses.aiSuggestDiagnoses).mockResolvedValue(complete);
  await open(); click("AI Review");
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Generate AI Suggestions" })); });
  expect(diagnoses.aiSuggestDiagnoses).toHaveBeenCalledExactlyOnceWith("fictional-cycle", false);
});

it("keeps a previously failed problem retryable after a later complete response", async () => {
  const response = await vi.mocked(diagnoses.fetchPesDraftStatus)("fictional-cycle");
  const result = { ...response, data: [], meta: { ...response.meta,
    can_generate: false, can_retry: true, has_retryable_rejections: true, review_outcome: "complete" as const } };
  vi.mocked(diagnoses.fetchPesDraftStatus).mockResolvedValue(result);
  vi.mocked(diagnoses.aiSuggestDiagnoses).mockResolvedValue(result);
  await open(); click("AI Review");
  expect(screen.queryByText("Could not generate AI suggestions. Try again or add a diagnosis manually.")).toBeNull();
  await act(async () => { fireEvent.click(screen.getByRole("button", { name: "Generate AI Suggestions" })); });
  expect(diagnoses.aiSuggestDiagnoses).toHaveBeenCalledExactlyOnceWith("fictional-cycle", true);
});
