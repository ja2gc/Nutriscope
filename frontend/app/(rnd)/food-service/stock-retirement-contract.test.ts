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

  test("service-day copy describes truthful records and status only", () => {
    const panel = read("app/(rnd)/food-service/menu-cycle/_components/ServiceLogPanel.tsx");
    const sources = [
      panel,
      read("services/consumptionService.ts"),
      read("../backend/app/Services/FSS/ConsumptionService.php"),
    ].join("\n").toLowerCase();
    const retiredConcepts = [
      ["deduct", "stock"].join(" "),
      ["restore", "stock"].join(" "),
      ["stock", "used"].join(" "),
    ];

    expect(sources).toContain("service-day record");
    expect(sources).not.toContain("estimated service cost");
    expect(panel).not.toContain("total_value");
    expect(sources).not.toContain("stock");
    retiredConcepts.forEach((copy) => expect(sources).not.toContain(copy));
  });
});
