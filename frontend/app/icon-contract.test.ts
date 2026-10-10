import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("NutriScope browser icon", () => {
  it("uses the NutriScope leaf mark instead of the starter favicon", () => {
    const icon = readFileSync(resolve(process.cwd(), "app/icon.svg"), "utf8");
    expect(icon).toContain("NutriScope");
    expect(icon).toContain("<path");
  });
});
