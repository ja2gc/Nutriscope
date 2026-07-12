import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string) {
  return readFileSync(join(process.cwd(), path), "utf8");
}

describe("contextual structured audit trail migration", () => {
  test("migrates every contextual caller to the shared trail", () => {
    const callers = [
      "app/(rnd)/ncp/patients/[patientId]/page.tsx",
      "app/(rnd)/food-service/procurement/page.tsx",
      "components/budget/BudgetPageShell.tsx",
      "components/reports/ReportsBrowser.tsx",
    ].map(source);

    for (const caller of callers) expect(caller).toContain("AuditTrail");
    expect(callers.join("\n")).not.toContain("HistoryPanel");
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
