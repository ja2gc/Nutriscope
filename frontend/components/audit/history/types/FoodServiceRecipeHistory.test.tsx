import { readFileSync } from "node:fs";
import { join } from "node:path";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import type { AuditHistorySnapshotDto } from "@/types/auditHistory";
import { FoodServiceRecipeHistory } from "./FoodServiceRecipeHistory";

const before: AuditHistorySnapshotDto = {
  type: "food_service_recipe",
  title: "Hospital Tray Meal",
  reference: "2f614555-b142-4c7f-8bd4-a02dad747177",
  fields: [
    { key: "name", label: "Name", value: { type: "text", value: "Hospital Tray Meal" } },
    { key: "servings", label: "Servings", value: { type: "number", value: 20 } },
    { key: "cost", label: "Estimated cost", value: { type: "currency", value: 160, currency: "PHP" } },
  ],
  tables: [{
    key: "ingredients",
    label: "Ingredients",
    columns: { ingredient: "Ingredient", quantity: "Quantity", unit: "Unit", catalog_unit: "Catalog unit", unit_cost: "Unit cost" },
    rows: [
      { key: "rice", values: { ingredient: { type: "text", value: "Brown Rice" }, quantity: { type: "number", value: 1000 }, unit: { type: "text", value: "g" }, catalog_unit: { type: "text", value: "g" }, unit_cost: { type: "currency", value: 0.08, currency: "PHP" } } },
      { key: "chicken", values: { ingredient: { type: "text", value: "Chicken" }, quantity: { type: "number", value: 500 }, unit: { type: "text", value: "g" }, catalog_unit: { type: "text", value: "g" }, unit_cost: { type: "currency", value: 0.24, currency: "PHP" } } },
    ],
  }],
};

const after: AuditHistorySnapshotDto = {
  ...before,
  fields: [before.fields[0], { ...before.fields[1], value: { type: "number", value: 30 } }, before.fields[2]],
  tables: [{
    ...before.tables[0],
    rows: [
      { ...before.tables[0].rows[0], values: { ...before.tables[0].rows[0].values, quantity: { type: "number", value: 1500 } } },
      { key: "tofu", values: { ingredient: { type: "text", value: "Tofu" }, quantity: { type: "number", value: 400 }, unit: { type: "text", value: "g" }, catalog_unit: { type: "text", value: "g" }, unit_cost: { type: "currency", value: 0.12, currency: "PHP" } } },
    ],
  }],
};

describe("food service recipe historical view", () => {
  test("renders added, changed, and removed ingredient structure as read-only typed values", () => {
    const html = renderToStaticMarkup(<FoodServiceRecipeHistory snapshot={after} comparison={before} side="after" />);

    for (const value of ["Brown Rice", "Chicken", "Tofu", "1,500", "₱160.00", "Changed", "Added", "Removed"]) {
      expect(html).toContain(value);
    }
    expect(html).not.toContain("<input");
    expect(html).not.toContain("<textarea");
  });

  test("shares typed comparison logic without raw serialization", () => {
    const source = readFileSync(join(process.cwd(), "components/audit/history/types/FoodServiceRecipeHistory.tsx"), "utf8");
    expect(source).not.toContain("JSON.stringify");
    expect(source).toContain("compareHistorySnapshot");
  });
});
