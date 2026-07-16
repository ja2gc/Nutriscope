import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import type { AuditHistorySnapshotDto } from "@/types/auditHistory";
import { PurchaseOrderHistory } from "./PurchaseOrderHistory";
import { ShoppingListHistory } from "./ShoppingListHistory";

function snapshot(type: string, rows: AuditHistorySnapshotDto["tables"][number]["rows"]): AuditHistorySnapshotDto {
  return {
    type,
    title: type === "shopping_list" ? "July Food List" : "PO-JULY-001",
    reference: "ab2cfeb1-063e-4a73-aa56-bd5be9f0d802",
    fields: [{ key: "status", label: "Status", value: { type: "enum", value: "draft" } }],
    tables: [{ key: "lines", label: "Lines", columns: { item: "Item", total: "Total" }, rows }],
  };
}

const beforeRows = [
  { key: "rice", values: { item: { type: "text" as const, value: "Rice" }, total: { type: "currency" as const, value: 800, currency: "PHP" } } },
  { key: "milk", values: { item: { type: "text" as const, value: "Milk" }, total: { type: "currency" as const, value: 250, currency: "PHP" } } },
];
const afterRows = [
  { key: "rice", values: { item: { type: "text" as const, value: "Rice" }, total: { type: "currency" as const, value: 900, currency: "PHP" } } },
  { key: "gloves", values: { item: { type: "text" as const, value: "Gloves" }, total: { type: "currency" as const, value: 500, currency: "PHP" } } },
];

describe("procurement historical views", () => {
  test.each([
    ["shopping list", ShoppingListHistory, "shopping_list"],
    ["purchase order", PurchaseOrderHistory, "purchase_order"],
  ] as const)("renders %s structural changes without edit controls", (_label, Component, type) => {
    const html = renderToStaticMarkup(
      <Component snapshot={snapshot(type, afterRows)} comparison={snapshot(type, beforeRows)} side="after" />,
    );

    for (const value of ["Rice", "Milk", "Gloves", "₱900.00", "Changed", "Added", "Removed"]) {
      expect(html).toContain(value);
    }
    expect(html).not.toContain("<input");
    expect(html).not.toContain("<textarea");
    expect(html).not.toContain("JSON.stringify");
  });
});
