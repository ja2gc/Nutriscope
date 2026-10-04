import fs from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const frontend = path.resolve(__dirname, "../..");
const read = (relative: string) => fs.readFileSync(path.join(frontend, relative), "utf8");

describe("purchase-order receiving UX contract", () => {
  it("uses progressive vendor changes at group and item scope", () => {
    const controls = read("components/foodservice/VendorChangeControls.tsx");
    expect(controls).toContain("Change vendor for all");
    expect(controls).toContain("Change vendor");
    expect(controls).toContain("item_id");
    expect(controls).toContain("can_change_vendor");
  });

  it("keeps Actual Total and the collapsed planned/actual details while removing three comparison fields", () => {
    const web = read("app/(rnd)/food-service/procurement/page.tsx");
    const native = fs.readFileSync(path.join(frontend, "..", "mobile", "app", "(tabs)", "procurement.tsx"), "utf8");
    const comparison = read("components/foodservice/PurchaseValueComparison.tsx");
    for (const label of ["Actual Quantity", "Unit", "Actual Cost/unit", "Actual Total"]) {
      expect(web).toContain(label);
      expect(native).toContain(label);
    }
    expect(web).toContain("Show planned total");
    expect(native).toContain("Show planned total");
    expect(web).toContain("PurchaseValueComparison");
    for (const label of ["Calculation details", "Planned purchase:", "Actual purchased:"]) {
      expect(comparison).toContain(label);
      expect(native).toContain(label);
    }
    for (const label of ["Calculated need", "Quantity difference", "Cost difference"]) {
      expect(comparison).not.toContain(label);
      expect(native).not.toContain(label);
    }
    expect(comparison).not.toContain("Not reviewed");
    expect(comparison).not.toContain("Reviewed");
    expect(native).not.toContain("Not reviewed");
    expect(native).not.toContain("Reviewed");
  });

  it("removes the purchase-order activity panel from the user-facing screen", () => {
    const web = read("app/(rnd)/food-service/procurement/page.tsx");
    const native = fs.readFileSync(path.join(frontend, "..", "mobile", "app", "(tabs)", "procurement.tsx"), "utf8");
    expect(web).not.toContain('title="Purchase order activity"');
    expect(web).not.toContain("/purchase-orders/${po.id}/activity");
    expect(native).not.toContain("po.ppa.activity");
  });

  it("keeps vendor correction available in both web and native purchase screens", () => {
    expect(read("app/(rnd)/food-service/procurement/page.tsx")).toContain("VendorChangeControls");
    const native = fs.readFileSync(path.join(frontend, "..", "mobile", "app", "(tabs)", "procurement.tsx"), "utf8");
    expect(native).toContain("Change vendor for all");
    expect(native).toContain("item_id");
  });
});
