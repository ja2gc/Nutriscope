import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

describe("Food Library public identifier contract", () => {
  test.each(["foodLibraryService.ts", "foodDatabaseService.ts"])("%s uses public UUID types", (file) => {
    const source = readFileSync(join(process.cwd(), "services", file), "utf8");

    expect(source).toContain("id: string;");
    expect(source).toContain("usda_fdc_id: number | null;");
    expect(source).toContain("food_item_id: string;");
    expect(source).toContain("meal_types: string[] | null;");
  });
});
