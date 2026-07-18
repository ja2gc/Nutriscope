import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

function source(path: string) {
  return readFileSync(join(root, path), "utf8");
}

describe("NCP active patient header", () => {
  test("shared header shows only approved clinical context and change action", () => {
    const header = source("app/(rnd)/ncp/_components/NcpPatientHeader.tsx");

    expect(header).toContain("NcpPatientHeader");
    expect(header).toContain("/ncp/patients");
    expect(header).toContain("Change Patient");
    expect(header).toContain("Physician");
    expect(header).toContain("Risk");
    expect(header).toContain("Foods");
    expect(header).toContain("Goal");
    expect(header).toContain("Medical diagnosis");
    expect(header).not.toContain("NS-");
    expect(header).not.toContain("NCP-");
    expect(header).not.toContain("Heart");
    expect(header).not.toContain("ArrowLeftRight");
    expect(header).not.toContain("Ward:");
    expect(header).toContain("Loading patient");
  });

  test("shared header stays compact without shrinking its primary action", () => {
    const header = source("app/(rnd)/ncp/_components/NcpPatientHeader.tsx");

    expect(header).toContain("min-h-11");
    expect(header).toContain("break-words");
    expect(header).toContain("lg:flex-nowrap");
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
      expect(page, pagePath).toContain("riskScore=");
      expect(page, pagePath).toContain("foodDetails=");
      expect(page, pagePath).toContain("interventionGoal=");
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
