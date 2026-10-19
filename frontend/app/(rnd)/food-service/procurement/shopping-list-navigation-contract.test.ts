import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("shopping list navigation", () => {
  test("uses the row pencil to open a plain shopping list name", () => {
    const source = readFileSync(join(process.cwd(), "app", "(rnd)", "food-service", "procurement", "page.tsx"), "utf8");

    expect(source).toContain('aria-label={`Open list: ${l.name}`} title="Open list"');
    expect(source).toContain('<Pencil className="h-3.5 w-3.5" />');
    expect(source).not.toContain('aria-label={`Rename ${l.name}`} title="Edit name"');
    expect(source).toContain('<span className="block min-w-0 truncate font-semibold text-warm-800">{l.name}</span>');
    expect(source).not.toContain('<button type="button" onClick={() => setListDetail(l.id)} className="text-left');
    expect(source).toContain("editingName");
    expect(source).toContain('aria-label="Apply name"');
    expect(source).toContain("Cancel rename");
    expect(source).toContain('onClick={() => setListDetail(l.id)}');
    expect(source).not.toContain('<span>Open</span>');
  });

  test("purchase unit options follow the compatible inventory units", () => {
    const source = readFileSync(join(process.cwd(), "app", "(rnd)", "food-service", "procurement", "page.tsx"), "utf8");

    expect(source).toContain("compatibleRecipeUnits(it.inventory_unit ?? it.unit, it.purchase_unit ?? it.unit)");
    expect(source).not.toContain('<input defaultValue={it.purchase_unit ?? it.unit}');
  });
});
