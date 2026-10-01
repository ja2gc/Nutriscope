import { afterEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";

import {
  CLINICAL_LAB_META,
  GOAL_LAB_FLAGS,
  LAB_REFERENCE_RANGES,
  type ClinicalLabKey,
  buildEffectiveMonitoringPayload,
  fetchMonitoringContext,
  getWeightStatus,
} from "./monitoringService";

describe("monitoring lab metadata", () => {
  it("does not describe unchanged weight as progress", () => {
    expect(getWeightStatus(60.8, 60.8, "Overweight")).toBe("no_data");
    expect(getWeightStatus(60.8, 60.8, "Malnutrition")).toBe("no_data");
  });

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

describe("monitoring effective context", () => {
  afterEach(() => vi.unstubAllGlobals());

  const context = {
    source_type: "monitoring" as const,
    source_monitoring_id: "monitoring-uuid",
    source_monitoring_date: "2026-09-28",
    weight: 60,
    height: 165,
    edema_present: true,
    dry_weight_kg: 58,
    physical_activity_level: "light",
    pregnancy_lactation_status: "none" as const,
    allergies: ["shellfish"],
    dietary_restrictions: "Low sodium",
    food_dislikes: ["okra"],
    age_years: 51,
    sex: "Female",
  };

  it("loads the complete prefill snapshot from the context endpoint", async () => {
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ data: context }), {
      status: 200,
      headers: { "Content-Type": "application/json" },
    }));
    vi.stubGlobal("fetch", fetchMock);

    await expect(fetchMonitoringContext("ncp-uuid")).resolves.toEqual(context);
    expect(fetchMock).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/ncp-uuid/monitorings/context",
      expect.objectContaining({ headers: { Accept: "application/json" } }),
    );
  });

  it("merges edits into a complete effective calculation payload", () => {
    expect(buildEffectiveMonitoringPayload(context, {
      observed_at: "2026-09-29",
      visit_type: "scheduled_follow_up",
      weight: 59,
    })).toMatchObject({
      observed_at: "2026-09-29",
      visit_type: "scheduled_follow_up",
      weight: 59,
      height: 165,
      edema_present: true,
      dry_weight_kg: 58,
      physical_activity_level: "light",
      pregnancy_lactation_status: "none",
      allergies: ["shellfish"],
      dietary_restrictions: "Low sodium",
      food_dislikes: ["okra"],
    });
  });

  it("removes intervention revision controls from monitoring", () => {
    const root = join(process.cwd(), "app", "(rnd)", "ncp", "[patientId]", "monitoring", "[ncpId]", "_components");
    const form = readFileSync(join(root, "LogVisitForm.tsx"), "utf8");
    const log = readFileSync(join(root, "EncounterLog.tsx"), "utf8");

    expect(form).toContain('grid grid-cols-1 sm:grid-cols-2');
    expect(form).not.toMatch(/Update care plan|Open Intervention after saving|intervention_revision/);
    expect(log).not.toMatch(/Care plan version|revision|Intervention revised/i);
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
