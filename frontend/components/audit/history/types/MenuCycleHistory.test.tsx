import { readFileSync } from "node:fs";
import { join } from "node:path";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import type { AuditHistorySnapshotDto } from "@/types/auditHistory";
import { MenuCycleHistory } from "./MenuCycleHistory";

const before: AuditHistorySnapshotDto = {
  type: "menu_cycle",
  title: "July Week One",
  reference: "573e6017-b8a5-4699-9142-ad2cc62fadf0",
  fields: [
    { key: "status", label: "Status", value: { type: "enum", value: "upcoming" } },
    { key: "total_cost", label: "Total cost", value: { type: "currency", value: 240, currency: "PHP" } },
  ],
  tables: [{
    key: "slots",
    label: "Planned meals",
    columns: { day: "Day", meal: "Meal", item: "Item", servings: "Servings" },
    rows: [
      { key: "monday-rice", values: { day: { type: "enum", value: "Monday" }, meal: { type: "enum", value: "lunch" }, item: { type: "text", value: "Rice Bowl" }, servings: { type: "number", value: 20 } } },
      { key: "tuesday-banana", values: { day: { type: "enum", value: "Tuesday" }, meal: { type: "enum", value: "am_snack" }, item: { type: "text", value: "Banana" }, servings: { type: "number", value: 20 } } },
    ],
  }],
};

const after: AuditHistorySnapshotDto = {
  ...before,
  fields: [
    { ...before.fields[0], value: { type: "enum", value: "active" } },
    { ...before.fields[1], value: { type: "currency", value: 300, currency: "PHP" } },
  ],
  tables: [{
    ...before.tables[0],
    rows: [
      { ...before.tables[0].rows[0], values: { ...before.tables[0].rows[0].values, servings: { type: "number", value: 30 } } },
      { key: "wednesday-milk", values: { day: { type: "enum", value: "Wednesday" }, meal: { type: "enum", value: "am_snack" }, item: { type: "text", value: "Milk" }, servings: { type: "number", value: 20 } } },
    ],
  }],
};

describe("menu cycle historical view", () => {
  test("renders changed, added, and removed weekly menu slots as read-only typed values", () => {
    const html = renderToStaticMarkup(<MenuCycleHistory snapshot={after} comparison={before} side="after" />);

    for (const value of ["Rice Bowl", "Banana", "Milk", "Active", "₱300.00", "Changed", "Added", "Removed"]) {
      expect(html).toContain(value);
    }
    expect(html).not.toContain("<input");
    expect(html).not.toContain("<textarea");
  });

  test("uses the shared typed comparator without raw serialization", () => {
    const source = readFileSync(join(process.cwd(), "components/audit/history/types/MenuCycleHistory.tsx"), "utf8");
    expect(source).toContain("compareHistorySnapshot");
    expect(source).not.toContain("JSON.stringify");
  });
});
