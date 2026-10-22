// @vitest-environment jsdom

import React, { Suspense, act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";

const routerPush = vi.fn();
let searchParams = new URLSearchParams();
const plannerUnmount = vi.fn();
const pageParams = Promise.resolve({ patientId: "patient-uuid", ncpId: "ncp-uuid" });
(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: routerPush, replace: routerPush }),
  usePathname: () => "/ncp/patient-uuid/intervention/ncp-uuid",
  useSearchParams: () => searchParams,
}));

vi.mock("@/services/interventionService", () => ({
  AutofillError: class AutofillError extends Error { missingFields: string[] = []; },
  updateIntervention: vi.fn(),
  deleteIntervention: vi.fn(),
  fetchIntervention: vi.fn(async () => ({
    id: "plan-newest",
    goal_type: "custom",
    disease_stage: null,
    displayed_nutrients: [],
    energy_kcal: "1900.00",
    protein_g: "75.00",
    carbs_g: "250.00",
    fat_g: "60.00",
    fluid_ml: "2000.00",
    micronutrient_limits: {},
    education_notes: "Meal timing",
    counseling_goals: "Follow meal schedule",
    barriers: "Shift work",
    strategies: "Pack meals",
    session_type: null,
    next_followup_date: null,
    source_monitoring_id: "monitoring-uuid",
    source_monitoring_date: "2026-09-28",
    has_meal_plan: false,
    meal_plan_id: null,
    created_at: "2026-09-29T08:00:00Z",
    updated_at: "2026-09-29T08:00:00Z",
  })),
  fetchInterventionPlans: vi.fn(async () => ({
    data: [{
      id: "plan-newest",
      plan_date: "2026-09-29T08:00:00Z",
      goal_type: "custom",
      disease_stage: null,
      source_monitoring_id: "monitoring-uuid",
      source_monitoring_date: "2026-09-28",
      has_meal_plan: false,
      meal_plan_id: null,
    }],
    meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
  })),
  fetchInterventionPlan: vi.fn(async () => ({
    id: "plan-newest",
    goal_type: "custom",
    disease_stage: null,
    displayed_nutrients: [],
    energy_kcal: "1900.00",
    protein_g: "75.00",
    carbs_g: "250.00",
    fat_g: "60.00",
    fluid_ml: "2000.00",
    micronutrient_limits: {},
    education_notes: "Meal timing",
    counseling_goals: "Follow meal schedule",
    barriers: "Shift work",
    strategies: "Pack meals",
    session_type: null,
    next_followup_date: null,
    source_monitoring_id: "monitoring-uuid",
    source_monitoring_date: "2026-09-28",
    has_meal_plan: false,
    meal_plan_id: null,
    created_at: "2026-09-29T08:00:00Z",
    updated_at: "2026-09-29T08:00:00Z",
  })),
  createIntervention: vi.fn(async () => ({
    id: "saved-plan",
    goal_type: "custom",
    disease_stage: null,
    displayed_nutrients: [],
    energy_kcal: "1900.00",
    protein_g: "75.00",
    carbs_g: "250.00",
    fat_g: "60.00",
    fluid_ml: "2000.00",
    micronutrient_limits: {},
    education_notes: "Meal timing",
    counseling_goals: "Follow meal schedule",
    barriers: "Shift work",
    strategies: "Pack meals",
    session_type: null,
    next_followup_date: null,
    source_monitoring_id: "monitoring-uuid",
    source_monitoring_date: "2026-09-28",
    has_meal_plan: false,
    meal_plan_id: null,
    created_at: "2026-09-29T09:00:00Z",
    updated_at: "2026-09-29T09:00:00Z",
  })),
  autofillIntervention: vi.fn(async () => ({
    energy_kcal: 1900,
    protein_g: 75,
    carbs_g: 250,
    fat_g: 60,
    fluid_ml: 2000,
    calculation_snapshot: { version: 2, method: "Clinician-entered prescription", measurement_date: "2026-09-28", inputs: { weightKg: 60.8, heightCm: 170, ageYears: 36 }, calculated: {} },
    source_type: "monitoring",
    source_monitoring_id: "monitoring-uuid",
    source_monitoring_date: "2026-09-28",
  })),
}));

vi.mock("@/services/assessmentService", () => ({
  fetchAssessment: vi.fn(async () => ({
    weight: "70.00",
    height: "170.00",
    physical_activity_level: "light",
    pregnancy_lactation_status: "none",
    edema_present: false,
    dry_weight_kg: null,
    food_dislikes: [],
    allergies: ["shellfish"],
    dietary_restrictions: "old assessment restriction",
  })),
}));

vi.mock("@/services/patientService", () => ({
  fetchPatientById: vi.fn(async () => ({
    id: "patient-uuid",
    name: "Fictional Patient",
    dob: "1990-01-01",
    sex: "Female",
  })),
  fetchPatientNcpRecords: vi.fn(async () => ({ data: [{ id: "ncp-uuid" }], meta: {} })),
  ncpRecordMatchesRoute: vi.fn(() => true),
}));

vi.mock("@/services/diagnosisService", () => ({
  fetchDiagnoses: vi.fn(async () => [{ id: "diagnosis-uuid" }]),
}));

vi.mock("@/services/monitoringService", () => ({
  fetchMonitoringContext: vi.fn(async () => ({
    source_type: "monitoring",
    source_monitoring_id: "monitoring-uuid",
    source_monitoring_date: "2026-09-28",
    weight: "60.80",
    height: "170.00",
    edema_present: false,
    dry_weight_kg: null,
    physical_activity_level: "light",
    pregnancy_lactation_status: "none",
    allergies: ["shellfish"],
    dietary_restrictions: "renal restriction",
    food_dislikes: ["okra"],
    age_years: 36,
    sex: "Female",
  })),
}));

vi.mock("./[patientId]/intervention/[ncpId]/_components/MealPlanSection", () => ({
  default: function Planner({ interventionPlanId, readOnly, onSaveIntervention }: { interventionPlanId: string; readOnly: boolean; onSaveIntervention?: () => Promise<string | null> }) {
    React.useEffect(() => () => { plannerUnmount(); }, []);
    const [draft, setDraft] = React.useState('Previous menu');
    return <div data-testid="meal-plan-section" data-plan={interventionPlanId} data-readonly={String(readOnly)}>
      <input aria-label="Menu draft" value={draft} onChange={(event) => setDraft(event.target.value)} />
      {!interventionPlanId && <button onClick={() => void onSaveIntervention?.()}>Save intervention for menu</button>}
    </div>;
  },
}));

vi.mock("./_components/NcpPatientHeader", () => ({
  default: ({ foodDetails }: { foodDetails?: Array<string | null | undefined> }) => (
    <div data-testid="patient-header">{foodDetails?.filter(Boolean).join(" | ")}</div>
  ),
}));

import InterventionPage from "./[patientId]/intervention/[ncpId]/page";
import * as interventionService from "@/services/interventionService";

describe("intervention plans workspace", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    searchParams = new URLSearchParams();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
  });

  it("uses the page name without a numeric step prefix", () => {
    const source = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx"), "utf8");
    expect(source).toContain("Nutrition Intervention");
    expect(source).not.toContain("Step 3: Nutrition Intervention");
  });

  async function renderPage() {
    await act(async () => {
      root.render(
        <Suspense fallback={<div>Loading</div>}>
          <InterventionPage params={pageParams} />
        </Suspense>,
      );
    });
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
  }

  async function clickButton(label: string) {
    const button = [...container.querySelectorAll("button")]
      .find((candidate) => candidate.textContent?.trim() === label);
    expect(button, `button ${label}`).toBeDefined();
    await act(async () => button?.dispatchEvent(new MouseEvent("click", { bubbles: true })));
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
  }

  afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  it("shows dated plans and creation inside Intervention without revision status language", async () => {
    await renderPage();

    expect(container.textContent).toContain("Plans");
    expect(container.textContent).toContain("Sep 29, 2026");
    expect(container.textContent).not.toMatch(/current|inactive|revision|version/i);
    expect(container.querySelector('[data-testid="meal-plan-section"]')?.closest('[hidden]')).not.toBeNull();
    expect(container.textContent).toContain("Create New Intervention Plan");
    await clickButton("Food / Nutrient Delivery");
    expect(container.querySelector('[data-testid="meal-plan-section"]')).not.toBeNull();
  });

  it("prefills a new plan without reusing the previous saved identity and cancel persists nothing", async () => {
    await renderPage();
    await clickButton("Plans");
    await clickButton("Create New Intervention Plan");

    expect(routerPush).toHaveBeenCalledWith("?mode=new");
    expect(container.textContent).toContain("New Intervention Plan");
    const planner = container.querySelector('[data-testid="meal-plan-section"]');
    expect(planner).not.toBeNull();
    expect(planner?.getAttribute('data-plan')).toBe('');
    expect(planner?.getAttribute('data-readonly')).toBe('false');

    await clickButton("Education");
    expect((container.querySelector('textarea[aria-label="Education notes"]') as HTMLTextAreaElement).value)
      .toBe("Meal timing");

    await clickButton("Cancel");
    expect(interventionService.createIntervention).not.toHaveBeenCalled();
    expect(container.querySelector('[aria-labelledby="intervention-plans-heading"]')).not.toBeNull();
    expect(container.querySelector('[aria-label="Saved intervention plan"]')?.closest('[hidden]')).not.toBeNull();
  });

  it("uses the latest monitoring context in new-plan calculation details", async () => {
    await renderPage();
    await clickButton("Plans");
    await clickButton("Create New Intervention Plan");
    await clickButton("Show calculations");

    expect(container.textContent).toContain("60.8 kg");
    expect(container.textContent).not.toContain("70 kg");
  });

  it("uses latest monitoring meal-safety details in the patient header", async () => {
    await renderPage();

    expect(container.querySelector('[data-testid="patient-header"]')?.textContent)
      .toContain("renal restriction");
    expect(container.textContent).not.toContain("old assessment restriction");
  });

  it("posts one complete plan then keeps its returned UUID editable", async () => {
    vi.mocked(interventionService.autofillIntervention).mockResolvedValueOnce({ energy_kcal: 2200, protein_g: 90, carbs_g: 300, fat_g: 70, fluid_ml: 2400, source_type: "monitoring", source_monitoring_id: "monitoring-uuid", source_monitoring_date: "2026-09-28" });
    await renderPage();
    await clickButton("Plans");
    await clickButton("Create New Intervention Plan");
    await clickButton("Save Intervention Plan");

    expect(interventionService.createIntervention).toHaveBeenCalledTimes(1);
    expect(interventionService.createIntervention).toHaveBeenCalledWith("ncp-uuid", expect.objectContaining({
      goal_type: "custom",
      energy_kcal: 1900,
      education_notes: "Meal timing",
      counseling_goals: "Follow meal schedule",
      barriers: "Shift work",
      strategies: "Pack meals",
    }));
    expect(routerPush).toHaveBeenCalledWith("?plan=saved-plan");
    expect(container.textContent).toContain("Save Intervention Plan");
    expect(container.querySelector('[data-testid="meal-plan-section"]')).not.toBeNull();
  });

  it('reopens the latest plan for editing and updates its existing identity', async () => {
    const latest = await interventionService.fetchIntervention('ncp-uuid');
    vi.mocked(interventionService.updateIntervention).mockResolvedValueOnce(latest!);
    await renderPage();
    await clickButton('Food / Nutrient Delivery');
    expect(container.textContent).toContain('Save Intervention Plan');
    await clickButton('Save Intervention Plan');
    expect(interventionService.updateIntervention).toHaveBeenCalledWith('ncp-uuid', 'plan-newest', expect.objectContaining({ energy_kcal: 1900 }));
    expect(interventionService.createIntervention).not.toHaveBeenCalled();
  });

  it("saves a new intervention for the pending menu action and exposes its returned identity", async () => {
    await renderPage();
    await clickButton("Create New Intervention Plan");
    await clickButton("Save intervention for menu");
    expect(interventionService.createIntervention).toHaveBeenCalledTimes(1);
    expect(container.querySelector('[data-testid="meal-plan-section"]')?.getAttribute('data-plan')).toBe('saved-plan');
    expect(container.querySelector('[data-testid="meal-plan-section"]')?.getAttribute('data-readonly')).toBe('false');
  });

  it("retains the new intervention and its visible planner when saving fails validation", async () => {
    vi.mocked(interventionService.createIntervention).mockRejectedValueOnce(new Error('Complete the prescription.'));
    await renderPage();
    await clickButton('Create New Intervention Plan');
    await clickButton('Save intervention for menu');
    expect(container.textContent).toContain('Complete the prescription.');
    expect(container.textContent).toContain('New Intervention Plan');
    expect(container.querySelector('[data-testid="meal-plan-section"]')?.getAttribute('data-plan')).toBe('');
    await clickButton('Education');
    expect((container.querySelector('textarea[aria-label="Education notes"]') as HTMLTextAreaElement).value).toBe('Meal timing');
  });

  it("keeps the planner mounted when the saved plan URL catches up with local state", async () => {
    await renderPage();
    await clickButton('Create New Intervention Plan');
    await clickButton('Save intervention for menu');
    plannerUnmount.mockClear();
    searchParams = new URLSearchParams('plan=saved-plan');
    await renderPage();
    expect(plannerUnmount).not.toHaveBeenCalled();
    expect(container.querySelector('[data-testid="meal-plan-section"]')?.getAttribute('data-plan')).toBe('saved-plan');
  });

  it("retains menu draft edits across intervention tabs and saving from Education", async () => {
    await renderPage();
    await clickButton('Create New Intervention Plan');
    const draft = container.querySelector<HTMLInputElement>('input[aria-label="Menu draft"]')!;
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(draft, 'Edited breakfast');
      draft.dispatchEvent(new Event('input', { bubbles: true }));
    });
    plannerUnmount.mockClear();
    await clickButton('Education');
    await clickButton('Counseling');
    await clickButton('Food / Nutrient Delivery');
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Menu draft"]')?.value).toBe('Edited breakfast');
    await clickButton('Education');
    await clickButton('Save Intervention Plan');
    await clickButton('Food / Nutrient Delivery');
    expect(container.querySelector<HTMLInputElement>('input[aria-label="Menu draft"]')?.value).toBe('Edited breakfast');
    expect(container.querySelector('[data-testid="meal-plan-section"]')?.getAttribute('data-plan')).toBe('saved-plan');
    expect(plannerUnmount).not.toHaveBeenCalled();
  });
});
