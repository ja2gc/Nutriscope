import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

describe("shopping-list item picker", () => {
  test("suggests Non-Auto Grocery items when the picker initially opens", () => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/procurement/page.tsx"), "utf8");

    expect(source).toContain('setGroceryFilter(isSupplies ? "" : "non_auto")');
    expect(source).toContain("listCatalog(kind, itemPickerPage, itemSearch.trim(), grocery)");
    expect(source).toContain("filters={isSupplies ? undefined");
  });
});
