import { renderToStaticMarkup } from "react-dom/server";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";
import type { AuditValueDto } from "@/types/audit";
import { AuditValue } from "./AuditValue";

function render(value: AuditValueDto) {
  return renderToStaticMarkup(<AuditValue value={value} />);
}

describe("AuditValue", () => {
  test("formats every supported scalar type without raw serialization", () => {
    expect(render({ type: "currency", value: 1234.5, currency: "PHP" })).toContain("₱1,234.50");
    expect(render({ type: "quantity", value: 150, unit: "g" })).toContain("150 g");
    expect(render({ type: "number", value: 140 })).toContain("140");
    expect(render({ type: "boolean", value: true })).toContain("Yes");
    expect(render({ type: "enum", value: "ready_to_eat" })).toContain("Ready to eat");
    expect(render({ type: "reference", value: "PO-2026-0142" })).toContain("PO-2026-0142");
    expect(render({ type: "date", value: "2026-07-15" })).toContain("Jul 15, 2026");
    expect(render({ type: "field_list", value: ["serving_size", "serving_unit"] })).toContain("Serving size, Serving unit");
    expect(render({ type: "text", value: null })).toContain("Not recorded");
    expect(render({ type: "redacted", value: null })).toContain("Value hidden");
  });

  test("fails closed for nested objects and arrays", () => {
    const unsafeObject = { type: "text", value: { secret: "RAW-OBJECT-SENTINEL" } } as unknown as AuditValueDto;
    const unsafeArray = { type: "field_list", value: [["RAW-ARRAY-SENTINEL"]] } as unknown as AuditValueDto;
    const html = renderToStaticMarkup(<><AuditValue value={unsafeObject} /><AuditValue value={unsafeArray} /></>);

    expect(html).toContain("Unsupported value");
    expect(html).not.toContain("RAW-OBJECT-SENTINEL");
    expect(html).not.toContain("RAW-ARRAY-SENTINEL");
    expect(readFileSync(join(process.cwd(), "components/audit/AuditValue.tsx"), "utf8")).not.toContain("JSON.stringify");
  });
});
