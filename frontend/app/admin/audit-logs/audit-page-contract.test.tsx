import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string) {
  const file = join(process.cwd(), path);
  return existsSync(file) ? readFileSync(file, "utf8") : "";
}

const page = source("app/admin/audit-logs/page.tsx");
const table = source("components/audit/AuditEventTable.tsx");
const drawer = source("components/audit/AuditEventDrawer.tsx");
const changes = source("components/audit/AuditChangeList.tsx");
const filters = source("components/audit/AuditFilters.tsx");
const exportButton = source("components/audit/AuditExportButton.tsx");
const urlState = source("components/audit/useAuditUrlState.ts");
const tabsComponent = source("components/ui/Tabs.tsx");

describe("purposeful admin audit views", () => {
  test("provides exactly five module tabs and every required secondary filter", () => {
    for (const label of ["All Activity", "Security & Administration", "Nutrition Care", "Food Service Operations", "Reports"]) {
      expect(page).toContain(label);
    }
    expect(page).toContain("meta.filters.module_counts");
    expect(page).toContain("items={tabs}");
    expect(page).not.toContain("meta.filters.categories");
    expect(filters).toContain("Date range");
    for (const label of ["Context", "Action", "Actor", "Outcome", "Severity"]) {
      expect(filters).toContain(label);
    }
    expect(filters).not.toContain('label="Domain"');
    expect(filters).not.toContain('label="Category"');
    expect(filters).toContain("AuditActorFilter");
    expect(filters).toContain("h-11");
    expect(tabsComponent).toContain('role="tablist"');
    expect(tabsComponent).toContain('role="tab"');
    expect(tabsComponent).toContain('e.key === "ArrowRight"');
    expect(tabsComponent).toContain("tabTheme.focus");
  });

  test("uses the exact seven table columns and a responsive mobile alternative", () => {
    for (const label of ["Time", "Action", "Actor", "Subject / context", "Outcome", "Severity", "Summary"]) {
      expect(table).toContain(label);
    }
    expect(table).toContain("hidden md:block");
    expect(table).toContain("md:hidden");
    expect(table).toContain("break-words");
    expect(table).toContain('<button');
    expect(table).not.toContain("tabIndex={0}");
    expect(table).not.toContain("onKeyDown");
  });

  test("downloads exports through a guarded UI without navigating to a raw response", () => {
    expect(page).toContain("AuditExportButton");
    expect(page).not.toContain("window.location.href");
    expect(exportButton).toContain("URL.createObjectURL");
    expect(exportButton).toContain("URL.revokeObjectURL");
    expect(exportButton).toContain("exporting");
    expect(exportButton).toContain("exportErrorMessage");
  });

  test("uses request sequencing and URL-authoritative state hooks", () => {
    expect(page).toContain("useAuditEventList");
    expect(page).toContain("useAuditUrlState");
    expect(page).not.toContain("window.location.href");
  });

  test("renders every safe drawer section and clinical redaction message", () => {
    for (const section of ["Event summary", "Actor", "Subject / context", "Result", "Safe request metadata", "Field changes"]) {
      expect(drawer).toContain(section);
    }
    expect(changes).toContain("Value hidden; field changed");
    expect(changes).not.toMatch(/[•●]{2,}/u);
  });

  test("contains no raw audit serialization or model-class filters", () => {
    const combined = [page, table, drawer, changes, filters].join("\n");
    expect(combined).not.toContain(["JSON", "stringify"].join("."));
    expect(combined).not.toContain(`<${"pre"}`);
    expect(combined).not.toContain(["App", "Models"].join("\\"));
    expect(combined).not.toContain(["subject", "type"].join("_"));
    expect(combined).not.toContain(`log.${["pro", "perties"].join("")}`);
  });

  test("keeps only non-sensitive filters in URL search parameters", () => {
    expect(urlState).toContain("useSearchParams");
    expect(urlState).toContain("router.replace");
    expect(urlState).toContain('searchParams.get("module")');
    expect(urlState).toContain('searchParams.get("subfilter")');
    expect(urlState).not.toContain('searchParams.get("category")');
    expect(urlState).not.toContain('searchParams.get("domain")');
    expect(urlState).not.toContain('searchParams.set("search"');
    expect(urlState).not.toContain('searchParams.set("reason"');
  });

  test("covers loading, empty, no-results, error, unauthorized and forbidden states", () => {
    for (const state of ["Loading audit events", "No audit events yet", "No matching audit events", "Unable to load audit events", "Sign in required", "Access denied"]) {
      expect(page).toContain(state);
    }
  });
});
