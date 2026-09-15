import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("shopping list navigation", () => {
  test("opens from the name and keeps rename controls inside detail", () => {
    const source = readFileSync(join(process.cwd(), "app", "(rnd)", "food-service", "procurement", "page.tsx"), "utf8");

    expect(source).toContain("onClick={() => setListDetail(l.id)}");
    expect(source).toContain("editingName");
    expect(source).toContain("Save name");
    expect(source).toContain("Cancel rename");
    expect(source).not.toContain("editingListId");
    expect(source).not.toContain('<Eye className="h-3.5 w-3.5" />\n                          </button>\n                          <button\n                            onClick={() => { setEditingListId');
  });

  test("purchase unit is selected from the shared catalog units", () => {
    const source = readFileSync(join(process.cwd(), "app", "(rnd)", "food-service", "procurement", "page.tsx"), "utf8");

    expect(source).toContain('import { CATALOG_UNIT_OPTIONS } from "@/lib/units"');
    expect(source).toContain("...CATALOG_UNIT_OPTIONS");
    expect(source).not.toContain('<input defaultValue={it.purchase_unit ?? it.unit}');
  });
});
