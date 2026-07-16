import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";
import type { AuditHistorySnapshotDto } from "@/types/auditHistory";
import { BudgetHistory } from "./BudgetHistory";

function snapshot(amount: number, includeLedger: boolean): AuditHistorySnapshotDto {
  return {
    type: "budget",
    title: "FY 2026 Budget",
    reference: "ab2cfeb1-063e-4a73-aa56-bd5be9f0d802",
    fields: [{ key: "remaining_balance", label: "Remaining balance", value: { type: "currency", value: amount, currency: "PHP" } }],
    tables: [{
      key: "ledger",
      label: "Budget ledger",
      columns: { type: "Type", amount: "Amount", reason: "Reason" },
      rows: includeLedger ? [{
        key: "manual-deduction",
        values: {
          type: { type: "enum", value: "manual_deduction" },
          amount: { type: "currency", value: 5000, currency: "PHP" },
          reason: { type: "text", value: "Correction with supporting memo" },
        },
      }] : [],
    }],
  };
}

describe("budget historical view", () => {
  test("renders typed ledger changes without edit or raw controls", () => {
    const html = renderToStaticMarkup(
      <BudgetHistory snapshot={snapshot(95000, true)} comparison={snapshot(100000, false)} side="after" />,
    );

    for (const value of ["\u20B195,000.00", "Correction with supporting memo", "Added", "Changed"]) {
      expect(html).toContain(value);
    }
    expect(html).not.toContain("<input");
    expect(html).not.toContain("<textarea");
    expect(html).not.toContain("JSON.stringify");
  });
});
