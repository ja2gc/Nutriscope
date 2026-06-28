import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const source = readFileSync(join(process.cwd(), "app/admin/audit-logs/page.tsx"), "utf8");

describe("admin audit filter contract", () => {
  test("uses backend model class names for subject filters", () => {
    expect(source).toContain('value: "App\\\\Models\\\\User"');
    expect(source).toContain('value: "App\\\\Models\\\\Budget"');
    expect(source).toContain('value: "App\\\\Models\\\\BudgetLedger"');
    expect(source).toContain('value: "App\\\\Models\\\\PurchaseOrder"');
  });

  test("includes auth and password security events", () => {
    expect(source).toContain('value="login_failed"');
    expect(source).toContain('value="password_changed"');
    expect(source).toContain('value="password_reset"');
  });
});
