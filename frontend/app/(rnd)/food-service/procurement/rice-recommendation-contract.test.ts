import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

describe("existing shopping-list item picker", () => {
  test("recommends catalog Rice first when the food picker initially opens", () => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/procurement/page.tsx"), "utf8");

    expect(source).toContain("onFocus={showInitialItemSuggestions}");
    expect(source).toContain('searchCatalog("Rice", "ingredient")');
    expect(source).toContain('item.name.localeCompare("Rice"');
    expect(source).toContain("itemSearchRequest.current");
  });
});
