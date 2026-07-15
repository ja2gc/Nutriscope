import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const decisions = {
  categoryTabs: ["remove", 6],
  domainFilter: ["remove", 6],
  categoryQuery: ["remove-after-compatibility", 15],
  domainQuery: ["remove-after-compatibility", 15],
  disabledExportControl: ["remove-after-compatibility", 15],
  rawJsonRenderer: ["keep-absent", 15],
  historyRoute: ["add", 8],
} as const;

function source(path: string): string {
  return readFileSync(join(process.cwd(), path), "utf8");
}

describe("audit stale-feature inventory", () => {
  test("every web compatibility item has an explicit removal or retention decision", () => {
    expect(decisions).toEqual({
      categoryTabs: ["remove", 6],
      domainFilter: ["remove", 6],
      categoryQuery: ["remove-after-compatibility", 15],
      domainQuery: ["remove-after-compatibility", 15],
      disabledExportControl: ["remove-after-compatibility", 15],
      rawJsonRenderer: ["keep-absent", 15],
      historyRoute: ["add", 8],
    });
  });

  test("current category, Domain, URL, proxy, and disabled-export compatibility is explicit", () => {
    const page = source("app/admin/audit-logs/page.tsx");
    const filters = source("components/audit/AuditFilters.tsx");
    const urlState = source("components/audit/useAuditUrlState.ts");
    const service = source("services/auditLogService.ts");
    const proxy = source("app/api/admin/audit-logs/route.ts");

    expect(page).toContain("AuditCategory");
    expect(page).toContain("meta.capabilities.export");
    expect(filters).toContain('label="Domain"');
    expect(urlState).toContain('searchParams.get("category")');
    expect(urlState).toContain('searchParams.get("domain")');
    expect(service).toContain('qs.set("category"');
    expect(service).toContain('qs.set("domain"');
    expect(proxy).toContain("new URL(req.url).searchParams");
    expect(proxy).toContain('proxy("/admin/audit-logs", { search })');
    expect(existsSync(join(process.cwd(), "components/audit/AuditExportButton.tsx"))).toBe(true);
    expect(existsSync(join(process.cwd(), "app/api/admin/audit-logs/export/route.ts"))).toBe(true);
  });

  test("raw JSON and deprecated security scaffolding stay absent", () => {
    const auditUi = [
      "components/audit/AuditChangeList.tsx",
      "components/audit/AuditEventDrawer.tsx",
      "components/audit/AuditEventTable.tsx",
      "components/audit/AuditFilters.tsx",
      "app/admin/audit-logs/page.tsx",
    ].map(source).join("\n");

    expect(auditUi).not.toContain("JSON.stringify");
    expect(auditUi).not.toContain("<pre");
    const forbiddenIpScaffolding = new RegExp([
      ["Ip", "Blocked"].join(""),
      ["Ip", "Unblocked"].join(""),
      ["AUDIT", "SECURITY", "BLOCKS", "ENABLED"].join("_"),
      "ip[-_ ]block",
    ].join("|"), "i");
    expect(auditUi).not.toMatch(forbiddenIpScaffolding);
  });

  test("approved module-only cleanup is not yet present", () => {
    const page = source("app/admin/audit-logs/page.tsx");
    const filters = source("components/audit/AuditFilters.tsx");
    const urlState = source("components/audit/useAuditUrlState.ts");

    expect(page).not.toContain("Security & Administration");
    expect(filters).toContain('label="Domain"');
    expect(urlState).toContain('searchParams.get("category")');
    expect(urlState).toContain('searchParams.get("domain")');
  });
});
