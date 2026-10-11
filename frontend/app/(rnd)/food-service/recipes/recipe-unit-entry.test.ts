import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

describe("Food Service recipe unit entry", () => {
  test.each(["new/page.tsx", "[id]/page.tsx"])("lets Non-Auto Grocery use a typed unit in %s", (page) => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/recipes", page), "utf8");
    expect(source).toContain("row.catalogItem?.include_in_generated_lists === false ?");
    expect(source).toContain('aria-label="Recipe unit"');
    expect(source).toContain('list={`recipe-units-${row.key}`}');
    expect(source).toContain("compatibleRecipeUnits(");
    expect(source).toContain("adjust_to_taste: r.adjust_to_taste");
    expect(source).toContain("Adjust to taste");
    expect(source).toContain("Enter a baseline quantity for every ingredient.");
  });
});
