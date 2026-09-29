import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";

import {
  CLINICAL_LAB_META,
  GOAL_LAB_FLAGS,
  LAB_REFERENCE_RANGES,
  type ClinicalLabKey,
  buildInterventionRevisionPayload,
} from "./monitoringService";
import type { Intervention } from "./interventionService";

describe("monitoring lab metadata", () => {
  it("supports severe nutrition electrolyte labs from monitoring plans", () => {
    const knownKeys = Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[];

    expect(knownKeys).toEqual(expect.arrayContaining(["phosphate", "magnesium"]));
    expect(CLINICAL_LAB_META.phosphate).toMatchObject({
      label: "Phosphate",
      unit: "mg/dL",
      type: "number",
    });
    expect(CLINICAL_LAB_META.magnesium).toMatchObject({
      label: "Magnesium",
      unit: "mg/dL",
      type: "number",
    });
    expect(LAB_REFERENCE_RANGES.phosphate).toMatchObject({
      label: "Phosphate",
      unit: "mg/dL",
      min: 2.5,
      max: 4.5,
    });
    expect(LAB_REFERENCE_RANGES.magnesium).toMatchObject({
      label: "Magnesium",
      unit: "mg/dL",
      min: 1.7,
      max: 2.2,
    });
  });

  it("keeps fallback goal lab flags aligned with renal electrolyte monitoring", () => {
    expect(GOAL_LAB_FLAGS.renal_diet).toEqual(
      expect.arrayContaining(["potassium", "phosphate"])
    );
    expect(GOAL_LAB_FLAGS.custom).toEqual(
      expect.arrayContaining(["phosphate", "magnesium"])
    );
  });
});

describe("monitoring intervention revisions", () => {
  it("builds an explicit complete snapshot only when the RND opts in", () => {
    const intervention: Intervention = {
      id: "intervention-uuid",
      goal_type: "custom",
      disease_stage: null,
      displayed_nutrients: ["energy", "protein", "carbs", "fat"],
      energy_kcal: "1800.00",
      protein_g: "70.00",
      carbs_g: "240.00",
      fat_g: "60.00",
      fluid_ml: "2000.00",
      micronutrient_limits: {},
      education_notes: "Education",
      counseling_goals: "Counseling",
      barriers: "Barrier",
      strategies: "Strategy",
      session_type: "follow-up",
      next_followup_date: "2026-10-15",
      source_monitoring_id: null,
      source_monitoring_date: null,
      has_meal_plan: false,
      meal_plan_id: null,
      created_at: "2026-09-25T08:00:00Z",
      updated_at: "2026-09-25T08:00:00Z",
    };

    expect(buildInterventionRevisionPayload(intervention, {
      effectiveDate: "2026-09-25",
      reason: "Needs changed",
      changes: { energy_kcal: 2000 },
    })).toEqual({
      effective_date: "2026-09-25",
      reason: "Needs changed",
      snapshot: expect.objectContaining({
        goal_type: "custom",
        energy_kcal: 2000,
        protein_g: "70.00",
        education_notes: "Education",
      }),
    });
  });

  it("keeps revision controls collapsed and mobile-safe while timeline metadata stays visible", () => {
    const root = join(process.cwd(), "app", "(rnd)", "ncp", "[patientId]", "monitoring", "[ncpId]", "_components");
    const form = readFileSync(join(root, "LogVisitForm.tsx"), "utf8");
    const log = readFileSync(join(root, "EncounterLog.tsx"), "utf8");

    expect(form).toContain('useState(false)');
    expect(form).toContain('title="Update care plan"');
    expect(form).toContain('grid grid-cols-1 sm:grid-cols-2');
    expect(log).toContain('Care plan version');
  });

  it("shows a readable patient code and cycle start instead of route UUIDs", () => {
    const page = readFileSync(join(
      process.cwd(), "app", "(rnd)", "ncp", "[patientId]", "monitoring", "[ncpId]", "page.tsx",
    ), "utf8");

    expect(page).toContain("patient?.patient_code");
    expect(page).toContain("Cycle started");
    expect(page).not.toContain(">Patient ID<");
    expect(page).not.toContain("{patientId}</span>");
    expect(page).not.toContain("{ncpId}</span>");
  });
});
