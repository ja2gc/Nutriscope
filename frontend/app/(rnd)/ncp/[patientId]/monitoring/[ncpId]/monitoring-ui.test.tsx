import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";

import CarePlanHeader from "./_components/CarePlanHeader";
import EncounterLog from "./_components/EncounterLog";
import GoalProgressTracker from "./_components/GoalProgressTracker";
import LogVisitForm from "./_components/LogVisitForm";
import MonitoringVisitDetails from "./_components/MonitoringVisitDetails";
import type { MonitoringContext, MonitoringEntry } from "@/services/monitoringService";

const context: MonitoringContext = {
  source_type: "monitoring",
  source_monitoring_id: "monitoring-uuid",
  source_monitoring_date: "2026-09-28",
  weight: 60,
  height: 165,
  edema_present: true,
  dry_weight_kg: 58,
  physical_activity_level: "light",
  pregnancy_lactation_status: "none",
  allergies: ["shellfish"],
  dietary_restrictions: "Low sodium",
  food_dislikes: ["okra"],
  age_years: 51,
  sex: "Female",
};

const entry: MonitoringEntry = {
  id: "visit-uuid",
  observed_at: "2026-09-28",
  visit_type: "scheduled_follow_up",
  weight: 59,
  height: 165,
  edema_present: true,
  dry_weight_kg: 57,
  physical_activity_level: "light",
  pregnancy_lactation_status: "none",
  allergies: ["shellfish"],
  dietary_restrictions: "Low sodium",
  food_dislikes: ["okra"],
  bmi: 21.7,
  lab_values: { hba1c: 6.8, energy_kcal: 1600 },
  intake_notes: "Meal schedule followed",
  symptoms: "No GI symptoms",
  goal_achievement: { compliance: "compliant", continuation_decision: "continue" },
  clinical_summary: "Weight and glucose improved.",
  ai_decision: null,
  next_monitoring_date: "2026-10-28",
  created_at: "2026-09-28T08:00:00Z",
  updated_at: "2026-09-28T08:00:00Z",
};

describe("monitoring workflow UI", () => {
  it("presents monitoring sources as plain targets without decorative card-title icons", () => {
    const html = renderToStaticMarkup(<CarePlanHeader plan={{
      visits: [],
      pes_statements: ["Inadequate intake related to poor appetite"],
      goal_type: "weight_gain",
      nutritional_status: "At risk",
      indicators: [],
    }} />);

    expect(html).toContain("Monitoring Targets");
    expect(html).not.toContain("Care Plan — What We&#x27;re Monitoring");
    expect(html).not.toMatch(/lucide-(clipboard-list|target|stethoscope|flask-conical)/);
  });

  it("orders complete effective reassessment fields without intervention actions", () => {
    const html = renderToStaticMarkup(<LogVisitForm
      plan={null}
      context={context}
      intervention={null}
      onSubmit={async () => undefined}
      onCancel={() => undefined}
    />);
    const headings = [
      "Visit Context",
      "Recalculation Measurements and Factors",
      "Goal-relevant Labs",
      "Meal Safety, Intake, and Tolerance",
      "Clinical Progress and Decision",
      "Follow-up",
    ];

    headings.reduce((previous, heading) => {
      const position = html.indexOf(heading);
      expect(position).toBeGreaterThan(previous);
      return position;
    }, -1);
    expect(html).toMatch(/Observed Date/);
    expect(html).toMatch(/Visit Type/);
    expect(html).toMatch(/Weight|Height|Edema|Dry Weight|Physical Activity|Pregnancy \/ Lactation|BMI/);
    expect(html).toMatch(/Allergies|Dietary Restrictions|Food Dislikes/);
    expect(html).not.toMatch(/Update care plan|Open Intervention after saving|revision|version/i);
  });

  it("summarizes saved visits and expands only non-empty ordered sections", () => {
    const summary = renderToStaticMarkup(<MonitoringVisitDetails entry={entry} mode="summary" />);
    const full = renderToStaticMarkup(<MonitoringVisitDetails entry={entry} mode="full" />);
    const sparse = renderToStaticMarkup(<MonitoringVisitDetails entry={{ ...entry, lab_values: null, intake_notes: null, symptoms: null }} mode="full" />);

    expect(summary).toContain("Sep 28, 2026");
    expect(summary).toContain("Scheduled follow-up");
    expect(summary).toContain("59 kg");
    expect(summary).toContain("Continue");
    expect(summary).not.toContain("Clinical Progress and Decision");

    const ordered = [
      "Visit Context",
      "Recalculation Measurements and Factors",
      "Goal-relevant Labs",
      "Meal Safety, Intake, and Tolerance",
      "Clinical Progress and Decision",
      "Follow-up",
    ];
    ordered.reduce((previous, heading) => {
      const position = full.indexOf(heading);
      expect(position).toBeGreaterThan(previous);
      return position;
    }, -1);
    expect(sparse).not.toContain("Goal-relevant Labs");
  });

  it("keeps legacy visits readable when newer context fields are absent", () => {
    const legacy = {
      ...entry,
      observed_at: null,
      visit_type: null,
      height: null,
      edema_present: null,
      dry_weight_kg: null,
      physical_activity_level: null,
      pregnancy_lactation_status: null,
      allergies: null,
      dietary_restrictions: null,
      food_dislikes: null,
    } as unknown as MonitoringEntry;

    const summary = renderToStaticMarkup(<MonitoringVisitDetails entry={legacy} mode="summary" />);
    const full = renderToStaticMarkup(<MonitoringVisitDetails entry={legacy} mode="full" />);

    expect(summary).toContain("Sep 28, 2026");
    expect(summary).toContain("Follow-up");
    expect(full).not.toMatch(/null (cm|kg)/);
  });

  it("uses neutral responsive visit rows without revision or raw-cycle language", () => {
    const html = renderToStaticMarkup(<EncounterLog
      entries={[entry]}
      onLogNew={() => undefined}
      onDelete={() => undefined}
    />);
    const root = join(process.cwd(), "app", "(rnd)", "ncp", "[patientId]", "monitoring", "[ncpId]");
    const page = readFileSync(join(root, "page.tsx"), "utf8");

    expect(html).toContain("Visit History");
    expect(html).not.toContain("border-emerald-200 bg-emerald-50");
    expect(html).not.toMatch(/revision|version|Intervention revised/i);
    expect(page).toContain("patient?.patient_code");
    expect(page).not.toContain("NCP Cycle");
    expect(page).not.toContain("{patientId}</span>");
    expect(page).not.toContain("{ncpId}</span>");
    expect(page).toContain("Nutrition Monitoring & Evaluation");
    expect(page).not.toContain("Step 4: Nutrition Monitoring & Evaluation");
  });

  it("keeps progress trends separate and chart guidance inside help", () => {
    const html = renderToStaticMarkup(<GoalProgressTracker
      plan={{
        visits: [],
        pes_statements: [],
        goal_type: "weight_loss",
        nutritional_status: "Overweight",
        indicators: [{
          key: "weight",
          label: "Weight",
          unit: "kg",
          category: "anthro",
          sources: ["calculated"],
          reference: null,
          target: 47.751968503937015,
          series: [
            { visit: "Visit 1", value: 62, status: "no_data" },
            { visit: "Follow-up 1", value: 60.8, status: "in_progress" },
          ],
          latest_status: "in_progress",
        }],
      }}
      entries={[]}
      baselineWeight={62}
      baselineBmi={25.8}
      baselineLabs={null}
      nutritionalStatus="Overweight"
    />);

    expect(html).toContain("target 47.8 kg");
    expect(html).not.toContain("47.751968503937015");
    expect(html).not.toContain("lucide-trending-up");
    expect(html).toContain("How progress and chart guides work");

    const chartSource = readFileSync(join(
      process.cwd(),
      "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/VisitTrendsChart.tsx",
    ), "utf8");
    expect(chartSource).toContain("How to read trend charts");
  });

  it("keeps the saved AI review heading free of decorative icons", () => {
    const source = readFileSync(join(
      process.cwd(),
      "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/MonitoringSummaryCard.tsx",
    ), "utf8");

    expect(source).not.toMatch(/<Sparkles[^>]*\/> AI Clinical Review/);
  });
});
