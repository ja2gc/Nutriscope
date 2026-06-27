import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("food-service budget placement", () => {
  test("budget page owns the budget and insights tabs", () => {
    const source = readFileSync(join(root, "app/(rnd)/food-service/budget/page.tsx"), "utf8");

    expect(source).toContain('key: "budget"');
    expect(source).toContain('key: "insights"');
    expect(source).toContain("<BudgetInsightsPanel");
    expect(source).toContain('from "@/components/ui/Tabs"');
  });

  test("sidebar does not expose insights as a separate food-service page", () => {
    const source = readFileSync(join(root, "components/layout/Sidebar.tsx"), "utf8");

    expect(source).toContain('href="/food-service/budget"');
    expect(source).not.toContain('href="/food-service/insights"');
  });
});
