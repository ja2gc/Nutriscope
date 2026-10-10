import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string) {
  return readFileSync(join(process.cwd(), path), "utf8");
}

describe("contextual structured audit trail migration", () => {
  test("keeps only the intended contextual trails in the UI", () => {
    const patientProfile = source("app/(rnd)/ncp/patients/[patientId]/page.tsx");
    const procurement = source("app/(rnd)/food-service/procurement/page.tsx");
    const budget = source("components/budget/BudgetPageShell.tsx");
    const reports = source("components/reports/ReportsBrowser.tsx");

    expect(reports).not.toContain("AuditTrail");
    expect(budget).not.toContain("AuditTrail");
    expect(budget).not.toContain("budget activity");
    expect(procurement).not.toContain("AuditTrail");
    expect(patientProfile).not.toContain("AuditTrail");
    expect(reports).not.toContain("HistoryPanel");
    expect(existsSync(join(process.cwd(), "components/HistoryPanel.tsx"))).toBe(false);
  });

  test("keeps every required contextual proxy and forwards cursors", () => {
    const routes = [
      "app/api/rnd/patients/[id]/activity/route.ts",
      "app/api/rnd/ncp-records/[ncpRecordId]/activity/route.ts",
      "app/api/fss/purchase-orders/[id]/activity/route.ts",
      "app/api/fss/budgets/[id]/activity/route.ts",
      "app/api/admin/budgets/[id]/activity/route.ts",
      "app/api/rnd/reports/[id]/activity/route.ts",
      "app/api/admin/reports/[id]/activity/route.ts",
    ];

    for (const route of routes) {
      const content = source(route);
      expect(content).toContain("activity");
      expect(content).toMatch(/searchParams|search:/);
    }
    expect(existsSync(join(process.cwd(), "app/api/fss/inventory/[id]/activity/route.ts"))).toBe(false);
  });

  test("never reintroduces raw object serialization", () => {
    const content = [source("services/activityService.ts"), source("components/audit/AuditTrail.tsx")].join("\n");
    expect(content).not.toContain(["JSON", "stringify"].join("."));
    expect(content).not.toContain(`<${"pre"}`);
  });
});
