import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string): string {
  return readFileSync(join(process.cwd(), path), "utf8");
}

describe("legacy person-name compatibility", () => {
  test("public user and patient DTOs retain deprecated name output", () => {
    expect(source("services/authService.ts")).toMatch(/interface User[\s\S]*?name:\s*string;/);
    expect(source("services/patientService.ts")).toMatch(/interface Patient[\s\S]*?name:\s*string;/);
  });

  test("compatibility writers pass their typed payload through unchanged", () => {
    const admin = source("services/adminUserService.ts");
    const patient = source("services/patientService.ts");

    expect(admin).toContain("body: JSON.stringify(data)");
    expect(patient).toContain("body: JSON.stringify(data)");
  });
});
