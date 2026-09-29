// @vitest-environment jsdom

import React, { Suspense, act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const routerPush = vi.fn();
const searchParams = new URLSearchParams();
(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: routerPush, replace: routerPush }),
  usePathname: () => "/ncp/patient-uuid/intervention/ncp-uuid",
  useSearchParams: () => searchParams,
}));

vi.mock("@/services/interventionService", () => ({
  AutofillError: class AutofillError extends Error { missingFields: string[] = []; },
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
    dietary_restrictions: null,
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

vi.mock("./[patientId]/intervention/[ncpId]/_components/MealPlanSection", () => ({
  default: () => <div data-testid="meal-plan-section" />,
}));

vi.mock("./[patientId]/_components/NcpPatientHeader", () => ({
  default: () => <div data-testid="patient-header" />,
}));

import InterventionPage from "./[patientId]/intervention/[ncpId]/page";
import * as interventionService from "@/services/interventionService";

describe("intervention plans workspace", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
  });

  async function renderPage() {
    await act(async () => {
      root.render(
        <Suspense fallback={<div>Loading</div>}>
          <InterventionPage params={Promise.resolve({ patientId: "patient-uuid", ncpId: "ncp-uuid" })} />
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
    expect(container.querySelector('[data-testid="meal-plan-section"]')).not.toBeNull();

    await clickButton("Plans");
    expect(container.textContent).toContain("Create New Intervention Plan");
  });

  it("prefills a new plan without copying its menu and cancel persists nothing", async () => {
    await renderPage();
    await clickButton("Plans");
    await clickButton("Create New Intervention Plan");

    expect(routerPush).toHaveBeenCalledWith("?mode=new");
    expect(container.textContent).toContain("New Intervention Plan");
    expect(container.querySelector('[data-testid="meal-plan-section"]')).toBeNull();

    await clickButton("Education");
    expect((container.querySelector('textarea[aria-label="Education notes"]') as HTMLTextAreaElement).value)
      .toBe("Meal timing");

    await clickButton("Cancel");
    expect(interventionService.createIntervention).not.toHaveBeenCalled();
  });

  it("posts one complete plan then selects its returned UUID read-only", async () => {
    await renderPage();
    await clickButton("Plans");
    await clickButton("Create New Intervention Plan");
    await clickButton("Save Intervention Plan");

    expect(interventionService.createIntervention).toHaveBeenCalledTimes(1);
    expect(interventionService.createIntervention).toHaveBeenCalledWith("ncp-uuid", expect.objectContaining({
      goal_type: "custom",
      education_notes: "Meal timing",
      counseling_goals: "Follow meal schedule",
      barriers: "Shift work",
      strategies: "Pack meals",
    }));
    expect(routerPush).toHaveBeenCalledWith("?plan=saved-plan");
    expect(container.textContent).not.toContain("Save Intervention Plan");
    expect(container.querySelector('[data-testid="meal-plan-section"]')).not.toBeNull();
  });
});
