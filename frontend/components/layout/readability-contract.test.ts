import { describe, expect, test } from "vitest";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { join } from "node:path";

const root = process.cwd();

function read(path: string) {
  return readFileSync(join(root, path), "utf8");
}

describe("readability contract", () => {
  test("compact density does not shrink the root font size", () => {
    const css = read("app/globals.css");

    expect(css).not.toMatch(/:root\[data-density="compact"\]\s*{[^}]*font-size:\s*93\.75%/s);
  });

  test("frontend avoids text sizes below 12px", () => {
    const files = [
      "app",
      "components",
    ];
    let output = "";
    try {
      output = execFileSync("rg", ["-n", String.raw`text-\[(?:8|9|10|11)px\]`, ...files], {
        cwd: root,
        encoding: "utf8",
      });
    } catch (error) {
      if ((error as { status?: number }).status !== 1) throw error;
    }

    expect(output).toBe("");
  });

  test("sidebar labels settings consistently with a gear icon", () => {
    const sidebar = read("components/layout/Sidebar.tsx");

    expect(sidebar).toContain("Settings");
    expect(sidebar).toContain("Cog");
    expect(sidebar).not.toContain("System Settings");
    expect(sidebar).not.toContain("Sliders");
  });
});
