import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();
const read = (path: string) => readFileSync(join(root, path), "utf8");

describe("food-service stock retirement", () => {
  test("recipe and procurement pickers use the reference catalog", () => {
    const sources = [
      read("app/(rnd)/food-service/recipes/new/page.tsx"),
      read("app/(rnd)/food-service/recipes/[id]/page.tsx"),
      read("app/(rnd)/food-service/procurement/page.tsx"),
    ].join("\n");

    expect(sources).toContain("searchCatalog");
    const retiredPickerNames = [
      ["list", "Inventory", "Rows"].join(""),
      ["search", "Inventory"].join(""),
      ["Inventory", "Item"].join(""),
      ["Inventory", "Row"].join(""),
    ];
    retiredPickerNames.forEach((name) => expect(sources).not.toContain(name));

    const catalogService = read("services/fsCatalogService.ts");
    expect(catalogService).toContain('limit: String(limit)');
    expect(catalogService).toContain('limit = 5');
  });

  test("runtime source has no retired stock contract names", () => {
    const sources = [
      read("services/fsCatalogService.ts"),
      read("app/(rnd)/food-service/recipes/new/page.tsx"),
      read("app/(rnd)/food-service/recipes/[id]/page.tsx"),
      read("app/(rnd)/food-service/procurement/page.tsx"),
    ].join("\n");

    const retiredNames = [
      ["quantity", "in", "stock"].join("_"),
      ["Stock", "Status"].join(""),
      ["in", "stock"].join("_"),
      ["no", "stock"].join("_"),
    ];
    retiredNames.forEach((name) => expect(sources).not.toContain(name));
  });

});
