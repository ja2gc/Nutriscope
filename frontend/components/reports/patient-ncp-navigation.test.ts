import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("Patients NCP report navigation", () => {
  test("places Patients NCP inside Browse under Clinical instead of a top tab", () => {
    const browser = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const fullCatalog = browser.slice(browser.indexOf("export const FULL_CATALOG"), browser.indexOf("export const ADMIN_CATALOG"));

    expect(browser).toContain('type: "patients_ncp", name: "Patients NCP"');
    expect(browser).toContain("<PatientsNcpTab />");
    expect(browser).toContain('type TabKey = "browse" | "archived" | "templates"');
    expect(browser).not.toContain('{ key: "patients" as TabKey');
    expect(fullCatalog).not.toContain('type: "patient_menu_plan"');
    expect(fullCatalog).not.toContain('type: "ncp_summary"');
  });

  test("patient page requires an ADIME cycle selection before showing its reports", () => {
    const browser = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const page = readFileSync(join(root, "app", "(rnd)", "reports", "patients", "[patientId]", "page.tsx"), "utf8");
    const service = readFileSync(join(root, "services", "reportService.ts"), "utf8");

    expect(page).toContain("listPatientNcpReports");
    expect(page).toContain("selectedCycle");
    expect(page).toContain("Choose an ADIME cycle");
    expect(page).toContain("Back to ADIME cycles");
    expect(page).toContain("prepareReport(instance.type, instance.params, \"rnd\")");
    expect(page).toContain("disabled={!instance.available");
    expect(page).toContain("instance.unavailable_reason");
    expect(service).toContain("intervention_plan_id: string | null");
    expect(service).toContain("meal_plan_id: string | null");
    expect(service).toContain("available: boolean");
    expect(browser).toContain("if (i.available === false) return");
    expect(browser).toContain("disabled={i.available === false}");
    expect(browser).toContain("i.unavailable_reason");
    expect(page).toContain("<Pagination");
    expect(page).not.toContain("Only reports belonging to this ADIME cycle are shown.");
    expect(page).not.toContain("Current and completed cycles stay separate.");
  });

  test("uses the patient-facing Nutrition Intervention Plan name without changing its internal type", () => {
    const browser = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const patients = readFileSync(join(root, "components", "reports", "PatientsNcpTab.tsx"), "utf8");
    const help = readFileSync(join(root, "lib", "helpContent.ts"), "utf8");

    expect(browser).toContain("Nutrition Intervention Plan");
    expect(browser).toContain("patient_menu_plan");
    expect(patients).not.toContain("Choose a patient and ADIME cycle for its NCP Summary and Nutrition Intervention Plan.");
    expect(help).toContain("Nutrition Intervention Plan and NCP Summary are blocked");
    expect(browser).not.toContain('name: "Patient Menu Plan"');
    expect(help).not.toContain("Patient Menu Plan");
  });

  test("opens the year or month census screen without PDF report actions", () => {
    const browser = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const census = readFileSync(join(root, "components", "reports", "CensusPanel.tsx"), "utf8");
    const globalStyles = readFileSync(join(root, "app", "globals.css"), "utf8");

    expect(browser).toContain("Year or month summary of ADIME cycle starts.");
    expect(browser).toContain("<CensusPanel apiPrefix={apiPrefix} />");
    expect(census).toContain("By risk level");
    expect(census).toContain("Download PDF");
    expect(census).not.toContain("window.print");
    expect(globalStyles).not.toContain("data-census-print");
    expect(browser).not.toContain("primary diagnosis category, nutritional status");
    expect(browser).not.toContain("ward");
  });
});
