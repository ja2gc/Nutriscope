import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const source = readFileSync(join(process.cwd(), "services/auditLogService.ts"), "utf8");

describe("audit log service contract", () => {
  test("uses only structured audit DTO fields and public filters", () => {
    expect(source).toContain('from "@/types/audit"');
    expect(source).toContain('qs.set("actor_id"');
    expect(source).toContain('qs.set("domain"');
    expect(source).toContain('qs.set("action"');
    expect(source).not.toContain("properties:");
    expect(source).not.toContain("subject_type");
    expect(source).not.toContain("causer_id");
    expect(source).not.toContain("email:");
    expect(source).not.toContain("updated_at");
  });
});
