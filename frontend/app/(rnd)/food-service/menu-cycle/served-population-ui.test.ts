import { readFileSync } from "fs";
import { join } from "path";
import { describe, expect, test } from "vitest";

const source = () => readFileSync(join(process.cwd(), "app/(rnd)/food-service/menu-cycle/page.tsx"), "utf8");

describe("menu cycle served population UI", () => {
  test("renders saved served population as display text until edit is requested", () => {
    const page = source();

    expect(page).toContain("editingServed");
    expect(page).toContain("beginServedEdit(d)");
    expect(page).toContain("setEditingServed(day)");
    expect(page).toContain("served[d] != null");
    expect(page).not.toContain("defaultValue={served[d] ?? \"\"}");
  });

  test("shows served population for saved past cycles, not only active cycles", () => {
    const page = source();

    expect(page).toContain("savedId && weekStart");
    expect(page).not.toContain("savedId && isActive && weekStart");
  });

  test("does not expose menu-cycle span or procurement-cost preview controls", () => {
    const page = source();

    expect(page).not.toContain("Cycle length");
    expect(page).not.toContain("Save &amp; Cost");
    expect(page).not.toContain("computeCycle");
    expect(page).not.toContain("setCycleDays");
    expect(page).not.toContain("DAYS.slice(0, cycleDays)");
    expect(page).not.toContain("Ingredient usage (whole cycle)");
  });
});
