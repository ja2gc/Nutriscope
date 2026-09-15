import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("Patients NCP report navigation", () => {
  test("replaces separate clinical report cards with one patient-first tab", () => {
    const browser = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const fullCatalog = browser.slice(browser.indexOf("export const FULL_CATALOG"), browser.indexOf("export const ADMIN_CATALOG"));

    expect(browser).toContain('label: "Patients NCP"');
    expect(browser).toContain("<PatientsNcpTab />");
    expect(fullCatalog).not.toContain('type: "patient_menu_plan"');
    expect(fullCatalog).not.toContain('type: "ncp_summary"');
  });

  test("patient page loads the combined feed and prepares both report types", () => {
    const page = readFileSync(join(root, "app", "(rnd)", "reports", "patients", "[patientId]", "page.tsx"), "utf8");

    expect(page).toContain("listPatientNcpReports");
    expect(page).toContain("prepareReport(instance.type, instance.params, \"rnd\")");
    expect(page).toContain("<Pagination");
  });
});
