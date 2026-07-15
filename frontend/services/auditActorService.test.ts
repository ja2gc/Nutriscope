import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const source = readFileSync(join(process.cwd(), "services/auditActorService.ts"), "utf8");

describe("audit actor service contract", () => {
  test("uses the dedicated paginated actor-name endpoint without private fields", () => {
    expect(source).toContain("/api/admin/audit-actors?");
    expect(source).toContain('qs.set("search"');
    expect(source).toContain('qs.set("page"');
    expect(source).toContain('qs.set("per_page"');
    expect(source).toContain('qs.set("selected_id"');
    expect(source).not.toContain("email");
    expect(source).not.toContain("patient");
  });
});
