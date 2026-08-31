import { readFileSync, readdirSync } from "node:fs";
import { join, relative } from "node:path";
import { describe, expect, test } from "vitest";

const sourceRoots = [
  join(process.cwd(), "app"),
  join(process.cwd(), "components"),
  join(process.cwd(), "..", "mobile", "app"),
  join(process.cwd(), "..", "mobile", "components"),
];

function sourceFiles(directory: string): string[] {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);

    if (entry.isDirectory()) {
      return sourceFiles(path);
    }

    if (!/\.(tsx|jsx)$/.test(entry.name) || /\.test\./.test(entry.name)) {
      return [];
    }

    return [path];
  });
}

describe("input placeholder contract", () => {
  test("application controls do not render in-field placeholder guidance", () => {
    const attributePattern = /\bplaceholder\s*=|\bplaceholderText(?:Color)?\s*=/i;
    const violations = sourceRoots.flatMap((root) =>
      sourceFiles(root)
        .filter((file) => attributePattern.test(readFileSync(file, "utf8")))
        .map((file) => relative(process.cwd(), file)),
    );

    expect(violations).toEqual([]);
  });
});
