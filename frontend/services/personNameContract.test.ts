import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string): string {
  return readFileSync(join(process.cwd(), path), "utf8");
}

describe("legacy person-name compatibility", () => {
  test("public user and patient DTOs expose split, display, and deprecated names", () => {
    for (const file of ["services/authService.ts", "services/patientService.ts"]) {
      const contract = source(file);
      expect(contract).toMatch(/first_name:\s*string\s*\|\s*null;/);
      expect(contract).toMatch(/last_name:\s*string\s*\|\s*null;/);
      expect(contract).toMatch(/display_name:\s*string;/);
      expect(contract).toMatch(/name:\s*string;/);
    }
  });

  test("modern create payloads require split names while legacy payloads stay typed", () => {
    const admin = source("services/adminUserService.ts");
    const patient = source("services/patientService.ts");

    expect(admin).toContain("first_name: string");
    expect(admin).toContain("last_name: string");
    expect(admin).toContain("name: string");
    expect(patient).toContain("first_name: string");
    expect(patient).toContain("last_name: string");
    expect(patient).toContain("name: string");
  });

  test("compatibility writers pass their typed payload through unchanged", () => {
    const admin = source("services/adminUserService.ts");
    const patient = source("services/patientService.ts");

    expect(admin).toContain("body: JSON.stringify(data)");
    expect(patient).toContain("body: JSON.stringify(data)");
  });
});
