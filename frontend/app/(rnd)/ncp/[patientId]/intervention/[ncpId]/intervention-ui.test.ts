import { readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, it } from "vitest";

it("uses scrollable accessible intervention tabs and keeps education outside the plan editor shell", () => {
  const page = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx"), "utf8");
  const editorStart = page.indexOf("<InterventionPlanEditor");
  const editorEnd = page.indexOf("</InterventionPlanEditor>", editorStart);
  const editorVisibility = page.slice(page.lastIndexOf("<div hidden=", editorStart), editorStart);
  const educationTab = page.indexOf('{tab === "education" &&');

  expect(page).toContain('<Tabs');
  expect(page).toContain('ariaLabel="Intervention sections"');
  expect(page).toContain("overflow-x-auto");
  expect(editorStart).toBeGreaterThan(-1);
  expect(editorEnd).toBeGreaterThan(editorStart);
  expect(editorVisibility).toContain('hidden={tab !== "nd"}');
  expect(educationTab).toBeGreaterThan(editorEnd);
});
