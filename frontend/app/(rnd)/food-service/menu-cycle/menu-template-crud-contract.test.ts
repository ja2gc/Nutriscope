import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("menu template CRUD", () => {
  test("offers create, open, edit, save, instantiate, and delete controls", () => {
    const route = readFileSync(join(root, "app", "(rnd)", "food-service", "menu-cycle", "page.tsx"), "utf8");
    expect(route).not.toContain("export function MenuTemplateDirectory");
    expect(route).toContain('export { default } from "./MenuCycleWorkspace"');
    const page = readFileSync(join(root, "app", "(rnd)", "food-service", "menu-cycle", "MenuCycleWorkspace.tsx"), "utf8");
    const service = readFileSync(join(root, "services", "menuCycleService.ts"), "utf8");

    const directory = readFileSync(join(root, "app", "(rnd)", "food-service", "menu-cycle", "templates", "page.tsx"), "utf8");
    expect(page).toContain('href="/food-service/menu-cycle/templates"');
    expect(page).toContain("export function MenuTemplateDirectory");
    expect(directory).toContain("MenuTemplateDirectory");
    expect(page).toContain("New Template");
    expect(page).toContain("getTemplate(");
    expect(page).toContain("saveTemplate(");
    expect(page).toContain("Save as Template");
    expect(page).toContain("Templates");
    expect(page).toContain("loadTemplateIntoDraft");
    expect(page).toContain("gridFromTemplate(template)");
    expect(page).not.toContain("saveCycleAsTemplate");
    expect(page).toContain("deleteTemplate(");
    expect(service).toContain("export async function saveTemplate");
  });
});
