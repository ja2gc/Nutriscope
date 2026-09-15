import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("menu template CRUD", () => {
  test("offers create, open, edit, save, instantiate, and delete controls", () => {
    const page = readFileSync(join(root, "app", "(rnd)", "food-service", "menu-cycle", "page.tsx"), "utf8");
    const service = readFileSync(join(root, "services", "menuCycleService.ts"), "utf8");

    expect(page).toContain("New Template");
    expect(page).toContain('mode: "template"');
    expect(page).toContain("onOpenTemplate");
    expect(page).toContain("getTemplate(");
    expect(page).toContain("saveTemplate(");
    expect(page).toContain("Create cycle from this");
    expect(page).toContain("deleteTemplate(");
    expect(service).toContain("export async function saveTemplate");
  });
});
