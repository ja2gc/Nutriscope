import { readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, it } from "vitest";

it("uses the shared NCP tabs and keeps education outside the plan editor shell", () => {
  const page = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx"), "utf8");
  const editorStart = page.indexOf("<InterventionPlanEditor");
  const editorEnd = page.indexOf("</InterventionPlanEditor>", editorStart);
  const editorVisibility = page.slice(page.lastIndexOf("<div hidden=", editorStart), editorStart);
  const educationTab = page.indexOf('{tab === "education" &&');

  expect(page).toContain('<NcpSectionTabs');
  expect(page).toContain('ariaLabel="Intervention sections"');
  expect(page).toContain('idPrefix="intervention"');
  expect(editorStart).toBeGreaterThan(-1);
  expect(editorEnd).toBeGreaterThan(editorStart);
  expect(editorVisibility).toContain('hidden={tab !== "nd"}');
  expect(educationTab).toBeGreaterThan(editorEnd);
});

it("gives saved plan rows a white surface", () => {
  const plans = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/InterventionPlansTab.tsx"), "utf8");
  expect(plans).toContain("bg-white sm:rounded-xl");
  expect(plans).toContain('"bg-white hover:bg-warm-50/60"');
});
