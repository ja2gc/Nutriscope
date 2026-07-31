import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

describe("procurement attachment URL contract", () => {
  test("uses the storage-provider URL returned by Laravel", () => {
    const page = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/procurement/page.tsx"), "utf8");
    const service = readFileSync(resolve(process.cwd(), "services/procurementService.ts"), "utf8");

    expect(service).toContain("url: string");
    expect(page).toContain("src: a.url");
    expect(page).not.toContain("/storage/${a.path}");
  });
});
