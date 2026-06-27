import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("food-service budget placement", () => {
  test("budget page has no insights tab and puts fiscal year setup at the top", () => {
    const source = readFileSync(join(root, "app/(rnd)/food-service/budget/page.tsx"), "utf8");

    // Insights are fully removed.
    expect(source).not.toContain("BudgetInsightsPanel");
    expect(source).not.toContain('key: "insights"');

    // Fiscal Year Setup section exists and is rendered above the summary cards.
    expect(source).toContain("FiscalYearSetupSection");
    expect(source.indexOf("FiscalYearSetupSection")).toBeLessThan(source.indexOf("SummarySection"));
  });

  test("summary shows only the three slimmed cards", () => {
    const source = readFileSync(join(root, "app/(rnd)/food-service/budget/page.tsx"), "utf8");

    expect(source).toContain('label: "Allocated"');
    expect(source).toContain('label: "Total Deductions"');
    expect(source).toContain('label: "Remaining"');
    // Removed cards.
    expect(source).not.toContain('label: "PO Deductions"');
    expect(source).not.toContain('label: "Manual Additions"');
    expect(source).not.toContain('label: "Over Allocation"');
  });

  test("ledger has no procurement span column", () => {
    const source = readFileSync(join(root, "app/(rnd)/food-service/budget/page.tsx"), "utf8");
    expect(source).not.toContain("procurement_span");
    expect(source).not.toMatch(/>\s*Span\s*</);
  });

  test("sidebar does not expose insights or inventory as food-service pages", () => {
    const source = readFileSync(join(root, "components/layout/Sidebar.tsx"), "utf8");

    expect(source).toContain('href="/food-service/budget"');
    expect(source).not.toContain('href="/food-service/insights"');
    expect(source).not.toContain('href="/food-service/inventory"');
  });
});
