import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, test } from "vitest";
import { MenuItemProfile } from "./MenuItemProfile";

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
