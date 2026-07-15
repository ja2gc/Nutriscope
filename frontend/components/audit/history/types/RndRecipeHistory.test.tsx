import { renderToStaticMarkup } from "react-dom/server";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";
import type { AuditHistorySnapshotDto } from "@/types/auditHistory";
import { RndRecipeHistory } from "./RndRecipeHistory";

const before: AuditHistorySnapshotDto = {
  type: "rnd_recipe",
  title: "Brown Rice Bowl",
  reference: "RCP-001",
  fields: [
    { key: "name", label: "Name", value: { type: "text", value: "Brown Rice Bowl" } },
    { key: "servings", label: "Servings", value: { type: "number", value: 2 } },
  ],
  tables: [{
    key: "ingredients",
    label: "Ingredients",
    columns: { ingredient: "Ingredient", quantity: "Quantity", unit: "Unit" },
    rows: [
      { key: "rice", values: { ingredient: { type: "text", value: "Brown Rice" }, quantity: { type: "number", value: 100 }, unit: { type: "text", value: "g" } } },
      { key: "chicken", values: { ingredient: { type: "text", value: "Chicken" }, quantity: { type: "number", value: 50 }, unit: { type: "text", value: "g" } } },
    ],
  }],
};

const after: AuditHistorySnapshotDto = {
  ...before,
  fields: [
    before.fields[0],
    { key: "servings", label: "Servings", value: { type: "number", value: 4 } },
  ],
  tables: [{
    ...before.tables[0],
    rows: [
      { key: "rice", values: { ingredient: { type: "text", value: "Brown Rice" }, quantity: { type: "number", value: 150 }, unit: { type: "text", value: "g" } } },
      { key: "tofu", values: { ingredient: { type: "text", value: "Tofu" }, quantity: { type: "number", value: 50 }, unit: { type: "text", value: "g" } } },
    ],
  }],
};

describe("RND recipe historical view", () => {
  test("renders the After version with added, changed, and removed recipe structure", () => {
    const html = renderToStaticMarkup(<RndRecipeHistory snapshot={after} comparison={before} side="after" />);

    for (const value of ["Brown Rice", "Chicken", "Tofu", "150", "Changed", "Added", "Removed"]) {
      expect(html).toContain(value);
    }
    expect(html).not.toContain("<input");
    expect(html).not.toContain("<textarea");
  });

  test("compares typed values without raw serialization", () => {
    const source = readFileSync(join(process.cwd(), "components/audit/history/types/RndRecipeHistory.tsx"), "utf8");
    expect(source).not.toContain("JSON.stringify");
    expect(source).toContain("AuditValueDto");
  });
});
