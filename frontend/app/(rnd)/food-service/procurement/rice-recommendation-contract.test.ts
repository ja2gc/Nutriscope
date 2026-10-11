import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

describe("shopping-list item picker", () => {
  test("suggests Non-Auto Grocery items when the picker initially opens", () => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/procurement/page.tsx"), "utf8");

    expect(source).toContain("onFocus={showInitialItemSuggestions}");
    expect(source).toContain('searchCatalog("", "ingredient", 5, "non_auto")');
    expect(source).toContain("itemSearchRequest.current");
  });
});
