import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { describe, expect, test } from "vitest";

const roots = ["app", "components", "lib", "services"];

function productionSources(): string[] {
  const files: string[] = [];
  const visit = (path: string): void => {
    for (const entry of readdirSync(path)) {
      const child = join(path, entry);
      if (statSync(child).isDirectory()) visit(child);
      else if (/\.(ts|tsx)$/.test(entry) && !/\.test\.(ts|tsx)$/.test(entry)) files.push(child);
    }
  };

  roots.forEach(visit);
  return files;
}

function matchingLines(pattern: RegExp): string[] {
  const matches: string[] = [];
  for (const file of productionSources()) {
    readFileSync(file, "utf8").split(/\r?\n/).forEach((line) => {
      pattern.lastIndex = 0;
      if (pattern.test(line)) matches.push(`${relative(process.cwd(), file).replaceAll("\\", "/")}:${line.trim()}`);
    });
  }
  return matches.sort();
}

describe("person-name stale-consumer guard", () => {
  test("current User and Patient objects are never rendered from their deprecated name member", () => {
    expect(matchingLines(/\b(?:[A-Za-z_$][\w$]*(?:user|patient)|user|patient)\??\.name\b/gi)).toEqual([]);
  });

  test("the one raw PersonNameLike name read stays inside the display adapter", () => {
    expect(matchingLines(/\bperson\.name\b/gi)).toEqual([
      "lib/personName.ts:if (person.name?.trim()) return person.name;",
    ]);
  });

  test("attribution name keys stay at typed API compatibility boundaries", () => {
    const counts = Object.fromEntries(
      matchingLines(/\b(?:actor|creator|created_by|author|owner|createdBy|updatedBy)\??\.name\b/gi)
        .reduce<Map<string, number>>((files, match) => {
          const file = match.slice(0, match.indexOf(":"));
          files.set(file, (files.get(file) ?? 0) + 1);
          return files;
        }, new Map()),
    );

    expect(counts).toEqual({
      "app/(rnd)/dashboard/page.tsx": 4,
      "app/admin/dashboard/page.tsx": 3,
      "components/announcements/AnnouncementsBoard.tsx": 4,
      "components/announcements/SopBanner.tsx": 2,
      "components/audit/AuditActorFilter.tsx": 3,
      "components/audit/AuditEventDrawer.tsx": 1,
      "components/audit/AuditEventTable.tsx": 2,
      "components/audit/AuditTrail.tsx": 1,
      "components/audit/history/AuditHistoryView.tsx": 1,
      "components/budget/BudgetPageShell.tsx": 2,
      "components/ncp/ClinicalAttribution.tsx": 2,
      "components/reports/ReportsBrowser.tsx": 1,
    });
  });

  test("all current account and patient presentation entry points use the shared adapter", () => {
    for (const file of [
      "app/admin/users/page.tsx",
      "app/(rnd)/dashboard/page.tsx",
      "app/(rnd)/ncp/patients/page.tsx",
      "app/(rnd)/ncp/patients/[patientId]/page.tsx",
      "app/(rnd)/ncp/_components/NcpPatientHeader.tsx",
      "components/layout/TopBar.tsx",
    ]) {
      expect(readFileSync(file, "utf8"), file).toContain("personDisplayName");
    }
  });

  test("food, recipe, supplier, menu, procurement, and report names stay entity names", () => {
    for (const file of [
      "services/foodDatabaseService.ts",
      "services/foodLibraryService.ts",
      "services/fsCatalogService.ts",
      "services/menuCycleService.ts",
      "services/procurementService.ts",
      "services/reportService.ts",
      "services/supplierService.ts",
    ]) {
      const source = readFileSync(file, "utf8");
      expect(source, file).toMatch(/\bname\??:\s*string/);
      expect(source, file).not.toContain("first_name");
      expect(source, file).not.toContain("last_name");
    }
  });
});
