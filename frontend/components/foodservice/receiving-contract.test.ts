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

  it("shows planned values first and calculation details only on request", () => {
    const comparison = read("components/foodservice/PurchaseValueComparison.tsx");
    expect(comparison).toContain("Planned purchase");
    expect(comparison).toContain("Actual purchased");
    expect(comparison).toContain("Calculation details");
    expect(comparison).toContain("Calculated need");
    expect(comparison).toContain("Not reviewed");
    expect(comparison).not.toContain("Calculated qty");
  });

  it("reuses the controls in both RND and FSS purchase screens", () => {
    expect(read("app/(rnd)/food-service/procurement/page.tsx")).toContain("VendorChangeControls");
    expect(read("components/fss/FssPurchaseOrders.tsx")).toContain("VendorChangeControls");
  });
});
