import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, test } from "vitest";
import { MenuItemProfile, RecipeMenuItemProfile } from "./MenuItemProfile";

test('saved template profiles use the captured name, yield and ingredients', () => {
  const html = renderToStaticMarkup(<RecipeMenuItemProfile id="archived" name="Current name" snapshot={{ name: 'Saved dish', calories: 100, protein: 2, carbs: 20, fat: 1, micronutrients: { sodium: 30 }, serving_size: 1, serving_unit: 'serving', recipe_yield: 5, recipe_ingredients: [{ name: 'Saved rice', quantity: 500, unit: 'g' }] }} onBack={() => {}} />);
  expect(html).toContain('Saved dish'); expect(html).toContain('Saved rice');
  expect(html).toContain('500'); expect(html).not.toContain('Current name');
});

test("complete clinical profiles retain units for all imported micronutrients", () => {
  const html = renderToStaticMarkup(<MenuItemProfile name="Measured food" servings={1} nutrients={{ micronutrients: { vitamin_e: 2, vitamin_b1: 0.3, selenium: 4 } }} ingredients={[]} onBack={() => {}} />);
  expect(html).toContain('2 mg');
  expect(html).toContain('0.3 mg');
  expect(html).toContain('4 mcg');
});

test("Food Service profiles share the layout without unrelated clinical fields", () => {
  const html = renderToStaticMarkup(<MenuItemProfile name="Measured food" servings={5} ingredients={[]} onBack={() => {}} />);
  expect(html).toContain('Recipe makes: 5 servings');
  expect(html).not.toContain('Micronutrients');
  expect(html).not.toContain('Energy');
});
