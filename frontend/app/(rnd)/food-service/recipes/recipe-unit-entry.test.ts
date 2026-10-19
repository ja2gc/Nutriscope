// @vitest-environment jsdom
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";
import { numericStepBase } from '@/components/ui/NumericInput';
import { quantityInputValue } from '@/lib/foodServiceFormat';

describe("Food Service recipe unit entry", () => {
  test.each(["new/page.tsx", "[id]/page.tsx"])("requires Meal, Snack, or Beverage category in %s", (page) => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/recipes", page), "utf8");
    expect(source).toContain('"meal", "snack", "beverage"');
    expect(source).toMatch(/<select value=\{category\}[^\r\n]* required>/);
    expect(source).not.toContain('"breakfast", "lunch"');
    expect(source).toContain('router.push("/food-service/inventory?tab=menu-items")');
    expect(source).not.toContain('>Foods</Link>');
  });
  test("uses the same Food Service filters and Single food item action", () => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/inventory/_components/FoodServiceMenuItems.tsx"), "utf8");
    expect(source).toContain('"All", "meal", "snack", "beverage"');
    expect(source).toContain("Single food item");
  });
  test("keeps recipe cost and serving preview without formula copy in the editor", () => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/recipes/[id]/page.tsx"), "utf8");
    expect(source).not.toContain("Cost formula:");
    expect(source).not.toContain("Σ qty × ₱/unit");
    expect(source).toContain("totalCost / servingsNum");
    expect(source).toContain("Preview at");
  });
  test.each(["new/page.tsx", "[id]/page.tsx"])("explains the offered unit families with InfoHint in %s", (page) => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/recipes", page), "utf8");
    expect(source).toContain('<InfoHint label="About recipe units">');
    expect(source).toContain("Weight: g, kg, oz.");
    expect(source).toContain("Liquid: mL, L, cup, tbsp, tsp.");
    expect(source).toContain("pc, bundle, pack, tray, unit each match only themselves.");
    expect(source).toContain("Nonconvertible units keep their saved unit.");
  });
  test.each(["new/page.tsx", "[id]/page.tsx"])("keeps Ingredient units in a matching select in %s", (page) => {
    const source = readFileSync(resolve(process.cwd(), "app/(rnd)/food-service/recipes", page), "utf8");
    expect(source).not.toContain("row.catalogItem?.include_in_generated_lists === false ?");
    expect(source).toContain('aria-label="Recipe unit"');
    expect(source).toContain('<select aria-label="Recipe unit"');
    expect(source).toContain("compatibleRecipeUnits(");
    expect(source).toContain("adjust_to_taste: r.adjust_to_taste");
    expect(source).toContain("Adjust to taste");
    expect(source).toContain("Enter a baseline quantity for every ingredient.");
  });

  test.each([['1.234567', '1.23', '1.33'], ['2.6789', '2.68', '2.78']])('keeps a rounded recipe quantity valid on its one-tenth step grid: %s', (raw, displayed, incremented) => {
    const input = document.createElement('input');
    input.type = 'number'; input.step = '0.1';
    input.value = quantityInputValue(raw);
    input.min = String(numericStepBase(input.value));
    expect(input.value).toBe(displayed);
    expect(input.checkValidity()).toBe(true);
    input.stepUp(); expect(input.value).toBe(incremented);
  });
});
