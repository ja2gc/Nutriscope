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
    const page = readFileSync(join(root, "app", "(rnd)", "reports", "patients", "[patientId]", "page.tsx"), "utf8");

    expect(page).toContain("listPatientNcpReports");
    expect(page).toContain("selectedCycle");
    expect(page).toContain("Choose an ADIME cycle");
    expect(page).toContain("Back to ADIME cycles");
    expect(page).toContain("prepareReport(instance.type, instance.params, \"rnd\")");
    expect(page).toContain("<Pagination");
    expect(page).not.toContain("Only reports belonging to this ADIME cycle are shown.");
    expect(page).not.toContain("Current and completed cycles stay separate.");
  });
});
