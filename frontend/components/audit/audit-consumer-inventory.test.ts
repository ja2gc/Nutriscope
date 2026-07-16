import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { describe, expect, test } from "vitest";

const productionFiles = [
  "app/admin/audit-logs/page.tsx",
  "app/admin/audit-logs/[id]/history/page.tsx",
  "components/audit/AuditChangeList.tsx",
  "components/audit/AuditActorFilter.tsx",
  "components/audit/AuditEventDrawer.tsx",
  "components/audit/AuditEventTable.tsx",
  "components/audit/AuditFilters.tsx",
  "components/audit/AuditRetentionControl.tsx",
  "components/audit/AuditTimestamp.tsx",
  "components/audit/AuditTrail.tsx",
  "components/audit/history/AuditHistoryView.tsx",
  "components/audit/history/StructuredHistorySnapshot.tsx",
  "components/audit/history/types/RndRecipeHistory.tsx",
  "components/audit/useAuditEventList.ts",
  "components/audit/useAuditUrlState.ts",
  "services/activityService.ts",
  "services/auditActorService.ts",
  "services/auditLogService.ts",
  "services/auditHistoryService.ts",
  "types/audit.ts",
  "types/auditHistory.ts",
];

const proxyFiles = [
  "app/api/admin/audit-actors/route.ts",
  "app/api/admin/audit-logs/[id]/history/route.ts",
  "app/api/admin/audit-logs/export/route.ts",
  "app/api/admin/audit-logs/route.ts",
  "app/api/admin/audit-retention/route.ts",
  "app/api/admin/budgets/[id]/activity/route.ts",
  "app/api/admin/reports/[id]/activity/route.ts",
  "app/api/fss/budgets/[id]/activity/route.ts",
  "app/api/fss/purchase-orders/[id]/activity/route.ts",
  "app/api/rnd/ncp-records/[ncpRecordId]/activity/route.ts",
  "app/api/rnd/patients/[id]/activity/route.ts",
  "app/api/rnd/reports/[id]/activity/route.ts",
];

function source(path: string): string {
  return readFileSync(join(process.cwd(), path), "utf8");
}

function filesUnder(path: string): string[] {
  const files: string[] = [];
  const visit = (directory: string): void => {
    for (const entry of readdirSync(directory)) {
      const child = join(directory, entry);
      if (statSync(child).isDirectory()) visit(child);
      else if (/\.(ts|tsx)$/.test(entry) && !/\.test\.(ts|tsx)$/.test(entry)) {
        files.push(relative(process.cwd(), child).replaceAll("\\", "/"));
      }
    }
  };
  visit(join(process.cwd(), path));
  return files.sort();
}

describe("audit consumer inventory", () => {
  test("Admin audit page, typed consumers, trails, filters, drawer, and URL state are explicit", () => {
    for (const file of productionFiles) expect(() => source(file), file).not.toThrow();

    const page = source("app/admin/audit-logs/page.tsx");
    expect(page).toContain("AuditEventDrawer");
    expect(page).toContain("AuditEventTable");
    expect(page).toContain("AuditFilters");
    expect(page).toContain("useAuditUrlState");
    expect(page).toContain("AuditRetentionControl");
  });

  test("all audit and contextual activity proxies are allowlisted", () => {
    const actual = filesUnder("app/api").filter((file) =>
      file.includes("audit-actors") || file.includes("audit-logs") || file.includes("audit-retention") || file.endsWith("/activity/route.ts"),
    );
    expect(actual).toEqual(proxyFiles);
  });

  test("normal UI and list service use only the active module taxonomy", () => {
    const page = source("app/admin/audit-logs/page.tsx");
    const filters = source("components/audit/AuditFilters.tsx");
    const urlState = source("components/audit/useAuditUrlState.ts");
    const service = source("services/auditLogService.ts");

    expect(page).toContain('AuditEventDto, AuditModule');
    expect(page).toContain('value={filters.module || "all"}');
    expect(filters).not.toContain('label="Domain"');
    expect(urlState).toContain('searchParams.get("module")');
    expect(urlState).toContain('searchParams.get("subfilter")');
    expect(urlState).not.toContain('searchParams.get("category")');
    expect(urlState).not.toContain('searchParams.get("domain")');
    expect(service).not.toContain('if (params.category) qs.set("category"');
    expect(service).not.toContain('if (params.domain) qs.set("domain"');
  });

  test("five-tab module contract and Task 8 history framework are explicit", () => {
    const page = source("app/admin/audit-logs/page.tsx");
    const filters = source("components/audit/AuditFilters.tsx");
    const types = source("types/audit.ts");

    expect(page).toContain("All Activity");
    for (const label of [
      "Security & Administration",
      "Nutrition Care",
      "Food Service Operations",
      "Reports",
    ]) expect(page).toContain(label);
    expect(filters).not.toContain('label="Domain"');
    expect(types).toContain("AuditModule");
    expect(filesUnder("app/api")).toContain("app/api/admin/audit-logs/[id]/history/route.ts");
    expect(() => source("app/admin/audit-logs/[id]/history/page.tsx")).not.toThrow();
  });
});
