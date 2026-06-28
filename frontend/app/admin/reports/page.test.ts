import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("admin report catalog", () => {
  test("shows non-patient RND reports and hides patient-specific reports", () => {
    const source = readFileSync(join(root, "components/reports/ReportsBrowser.tsx"), "utf8");
    const adminCatalog = source.slice(
      source.indexOf("export const ADMIN_CATALOG"),
      source.indexOf("export type ApiPrefix"),
    );

    expect(adminCatalog).toContain("program_project_activity");
    expect(adminCatalog).toContain("menu_calendar");
    expect(adminCatalog).toContain("procurement_pack");
    expect(adminCatalog).toContain("accomplishment_report");
    expect(adminCatalog).toContain("demographic_census");
    expect(adminCatalog).not.toContain("patient_menu_plan");
    expect(adminCatalog).not.toContain("ncp_summary");
  });
});
