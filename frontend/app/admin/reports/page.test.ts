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

  test("shows accessible creator and archive attribution without raw metadata", () => {
    const source = readFileSync(join(root, "components/reports/ReportsBrowser.tsx"), "utf8");

    expect(source).toContain("Created by");
    expect(source).toContain("r.created_by?.name");
    expect(source).toContain("Asia/Manila");
    expect(source).toContain("<time");
    expect(source).not.toContain("JSON.stringify");
    expect(source).not.toContain("<pre");
  });

  test("removed report create proxy exposes GET only", () => {
    const source = readFileSync(join(root, "app/api/rnd/reports/route.ts"), "utf8");
    expect(source).toContain("export async function GET");
    expect(source).not.toContain("export async function POST");
    expect(source).not.toContain("NextRequest");
  });
});
