import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import CarePlanHeader from "./_components/CarePlanHeader";
import EncounterLog from "./_components/EncounterLog";
import LogVisitForm from "./_components/LogVisitForm";

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

  it("uses a neutral readable care-plan history entry", () => {
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

    expect(html).toContain("Care plan version 1");
    expect(html).toContain("Initial care plan");
    expect(html).toContain("Not recorded");
    expect(html).not.toContain("Intervention revised");
    expect(html).not.toContain("border-emerald-200 bg-emerald-50");
    expect(html).not.toContain("—");
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
