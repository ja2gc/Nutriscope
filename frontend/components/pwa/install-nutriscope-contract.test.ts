import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(path.join(__dirname, "InstallNutriScope.tsx"), "utf8");

describe("mobile install control", () => {
  it("always renders the named install button on phone and tablet", () => {
    expect(source).toContain("Install NutriScope");
    expect(source).not.toContain("{promptEvent && !installed && (");
    expect(source).toContain("setShowInstructions(true)");
  });

  it("uses login as a handoff and reserves installation for the scoped landing", () => {
    expect(source).toContain('mode === "login"');
    expect(source).toContain('href="/mobile-app"');
  });
});
