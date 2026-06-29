import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

function source(path: string) {
  return readFileSync(join(root, path), "utf8");
}

describe("NCP active patient header", () => {
  test("shared header shows patient identity and returns to patient directory", () => {
    const header = source("app/(rnd)/ncp/_components/NcpPatientHeader.tsx");

    expect(header).toContain("NcpPatientHeader");
    expect(header).toContain("/ncp/patients");
    expect(header).toContain("Change Patient");
    expect(header).toContain("NS-");
    expect(header).toContain("NCP-");
    expect(header).toContain("Loading patient");
  });

  test("all concrete NCP steps use the shared header", () => {
    const pages = [
      "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx",
      "app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx",
      "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx",
      "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx",
    ];

    for (const pagePath of pages) {
      const page = source(pagePath);
      expect(page, pagePath).toContain("NcpPatientHeader");
      expect(page, pagePath).toContain("stepLabel=");
    }
  });

  test("intervention protects unsaved edits before changing patient", () => {
    const page = source("app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx");

    expect(page).toContain("handleChangePatient");
    expect(page).toContain("You have unsaved changes. Leave without saving?");
    expect(page).toContain('router.push("/ncp/patients")');
    expect(page).toContain("onChangePatientClick={handleChangePatient}");
  });

  test("monitoring fetches patient details for the header without blocking clinical data", () => {
    const page = source("app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx");

    expect(page).toContain("fetchPatientById(patientId)");
    expect(page).toContain("setPatient");
    expect(page).toContain("patientData.status === \"fulfilled\"");
  });
});
