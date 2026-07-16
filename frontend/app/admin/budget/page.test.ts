import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("admin budget page", () => {
  test("uses shared read-only budget shell", () => {
    const source = readFileSync(join(root, "app/admin/budget/page.tsx"), "utf8");

    expect(source).toContain("BudgetPageShell");
    expect(source).toContain('apiPrefix="admin"');
    expect(source).toContain("canMutate={false}");
    expect(source).toContain('homeHref="/admin/dashboard"');
  });

  test("shared budget shell gates mutation controls behind canMutate", () => {
    const source = readFileSync(join(root, "components/budget/BudgetPageShell.tsx"), "utf8");

    expect(source).toContain("canMutate");
    expect(source).toContain("canMutate &&");
    expect(source).toContain("FiscalYearSetupSection");
    expect(source).toContain("ManualAdjustSection");
  });

  test("shared budget ledger identifies the actual user or system actor", () => {
    const source = readFileSync(join(root, "components/budget/BudgetPageShell.tsx"), "utf8");
    const service = readFileSync(join(root, "services/budgetService.ts"), "utf8");

    expect(source).toContain(">Actor<");
    expect(source).toContain("e.actor.name");
    expect(source).not.toContain(">Created By<");
    expect(service).toContain('kind: "user" | "system"');
  });
});
