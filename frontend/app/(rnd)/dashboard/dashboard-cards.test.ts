import { readFileSync } from "node:fs";
import { expect, it } from "vitest";

it("makes each dashboard KPI card a consistently styled destination", () => {
  const source = readFileSync("app/(rnd)/dashboard/page.tsx", "utf8");
  for (const label of ["Open patients in care", "Open scheduled visits", "Open pending purchase orders"]) expect(source).toContain(`aria-label="${label}"`);
  expect(source).toContain('<Link href="/ncp/patients" aria-label="Open scheduled visits"');
  expect(source).not.toContain('href="/calendar"');
  const cards = source.slice(source.indexOf('aria-label="Open patients in care"'), source.indexOf("Appointment Queue"));
  expect(cards).not.toContain("text-[#EA580C]");
  expect(source).toContain("Pending POs");
});
