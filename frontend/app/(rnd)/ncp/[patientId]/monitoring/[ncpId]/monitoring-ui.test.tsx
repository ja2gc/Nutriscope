import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import CarePlanHeader from "./_components/CarePlanHeader";
import EncounterLog from "./_components/EncounterLog";
import GoalProgressTracker from "./_components/GoalProgressTracker";
import LogVisitForm from "./_components/LogVisitForm";
import { readFileSync } from "node:fs";
import { join } from "node:path";

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
    expect(html).not.toContain("lucide-clipboard-list");
    expect(html).not.toContain("lucide-target");
    expect(html).not.toContain("lucide-stethoscope");
    expect(html).not.toContain("lucide-flask-conical");
  });

  it("does not repeat a migrated baseline under every monitoring visit", () => {
    const html = renderToStaticMarkup(<EncounterLog
      entries={[{
        id: "visit-1",
        ncp_record_id: 1,
        weight: null,
        bmi: null,
        lab_values: null,
        intake_notes: null,
        symptoms: null,
        goal_achievement: null,
        clinical_summary: null,
        ai_decision: null,
        next_monitoring_date: null,
        created_at: "2026-09-28T08:00:00Z",
        updated_at: "2026-09-28T08:00:00Z",
        intervention_revision: {
          id: "revision-1",
          version: 1,
          effective_at: "2026-09-28T08:00:00Z",
          reason: "Legacy intervention baseline",
          source: "legacy_baseline",
          snapshot: {} as never,
        },
      }]}
      onLogNew={() => undefined}
      onDelete={() => undefined}
    />);

    expect(html).not.toContain("Care plan version 1");
    expect(html).not.toContain("Initial care plan");
    expect(html).toContain("Not recorded");
    expect(html).not.toContain("Intervention revised");
    expect(html).not.toContain("border-emerald-200 bg-emerald-50");
    expect(html).not.toContain("—");
  });

  it("keeps chart guidance in help and formats targets for display", () => {
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
    expect(html).not.toContain("Visit 1 = assessment baseline");
    expect(html).toContain("How progress and chart guides work");

    const chartSource = readFileSync(join(
      process.cwd(),
      "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/VisitTrendsChart.tsx",
    ), "utf8");
    expect(chartSource.match(/Visit 1 = assessment baseline/g) ?? []).toHaveLength(0);
    expect(chartSource).toContain("How to read trend charts");
  });

  it("collects reassessment details and keeps care-plan updates optional", () => {
    const html = renderToStaticMarkup(<LogVisitForm
      plan={null}
      heightCm={165}
      intervention={null}
      onSubmit={async () => undefined}
      onCancel={() => undefined}
    />);

    expect(html).toContain("Follow-up Assessment");
    expect(html).toContain("Intake Notes");
    expect(html).toContain("Symptoms");
    expect(html).toContain("Progress Assessment");
    expect(html).toContain("Open Intervention after saving");
  });
});
