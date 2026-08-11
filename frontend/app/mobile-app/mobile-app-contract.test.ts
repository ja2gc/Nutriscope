import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(path.join(__dirname, "page.tsx"), "utf8");

describe("public FSS app landing", () => {
  it("uses shared install handoff and identifies the intended role", () => {
    expect(source).toContain('InstallNutriScope mode="landing"');
    expect(source).toContain("Food Service Staff");
    expect(source).not.toMatch(/desktop app/i);
  });
});
