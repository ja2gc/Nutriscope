import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const dateSurfaces = [
  "components/audit/AuditFilters.tsx",
  "app/(rnd)/food-service/procurement/page.tsx",
  "app/(rnd)/food-service/menu-cycle/_components/ServiceLogPanel.tsx",
  "app/(rnd)/ncp/patients/page.tsx",
  "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/LogVisitForm.tsx",
  "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx",
  "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EncounterContextTab.tsx",
];

describe("universal date picker adoption", () => {
  test.each(dateSurfaces)("uses shared picker in %s", (path) => {
    const source = readFileSync(join(process.cwd(), path), "utf8");
    expect(source).not.toMatch(/type=["']date(?:time-local)?["']/);
    expect(source).toMatch(/DatePicker|DateTimePicker/);
  });
});
