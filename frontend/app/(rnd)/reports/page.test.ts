import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = join(process.cwd());

describe("shared reports page", () => {
  test("selects the FSS accomplishment catalog and API prefix from the authenticated role", () => {
    const source = readFileSync(join(root, "app", "(rnd)", "reports", "page.tsx"), "utf8");

    expect(source).toContain("FSS_CATALOG");
    expect(source).toContain('apiPrefix={isFss ? "fss" : "rnd"}');
  });
});
