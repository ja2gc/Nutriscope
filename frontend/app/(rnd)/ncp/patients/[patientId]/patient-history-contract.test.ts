import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const patientPage = () => readFileSync(join(process.cwd(), "app/(rnd)/ncp/patients/[patientId]/page.tsx"), "utf8");
const patientList = () => readFileSync(join(process.cwd(), "app/(rnd)/ncp/patients/page.tsx"), "utf8");
const reportsBrowser = () => readFileSync(join(process.cwd(), "components/reports/ReportsBrowser.tsx"), "utf8");

describe("patient history cleanup", () => {
  test("removes audit panels and internal identifiers", () => {
    const source = patientPage();
    expect(source).not.toContain("Patient and NCP activity");
    expect(source).not.toContain("<AuditTrail");
    expect(source).not.toContain("Cycle ID");
    expect(source).not.toContain("System {Number(patient.risk_score)");
    expect(source).not.toContain("formatSystemId");
    expect(patientList()).not.toContain("Cycle ID");
  });

  test("reuses InfoHint for the approved protection rules", () => {
    const source = patientPage();
    expect(source).toContain("<InfoHint");
    expect(source).toContain("Starting a new cycle does not change prior ADIME records.");
    expect(source).toContain("Once all three are recorded, the cycle is protected.");
  });

  test("uses normal clickable meal plan labels without AI badges", () => {
    const source = patientPage();
    expect(source).toContain("Meal Plan {index + 1}");
    expect(source).toContain("prepareReport");
    expect(source).toContain("<ReportPreview");
    expect(source).not.toContain('mp.generation_type === "auto"');
    expect(source).not.toContain("Week of {mp.week_start_date}");
  });

  test("shows current cycle, always-visible paginated past records, and appointments", () => {
    const source = patientPage();
    expect(source).toContain('type TabKey = "overview" | "adime-records" | "appointments" | "attachments"');
    expect(source).toContain("Current Cycle");
    expect(source).toContain("Past Records");
    expect(source).toContain("No past ADIME records yet.");
    expect(source).toContain("record.discontinuation_reason_code");
    expect(source).toContain('scope: "past"');
    expect(source).toContain("<PatientAppointments");
  });

  test("removes the report identity instruction", () => {
    expect(reportsBrowser()).not.toContain("Click a report to view it.");
    expect(reportsBrowser()).not.toContain("refreshes important source data when needed");
  });
});
