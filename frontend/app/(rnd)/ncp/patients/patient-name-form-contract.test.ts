import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const page = readFileSync(join(process.cwd(), "app/(rnd)/ncp/patients/page.tsx"), "utf8");
const assessment = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx"), "utf8");
const diagnosis = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx"), "utf8");
const patientHeader = readFileSync(join(process.cwd(), "app/(rnd)/ncp/_components/NcpPatientHeader.tsx"), "utf8");

describe("patient split-name form", () => {
  test("the create flow requires and submits separate names", () => {
    expect(page).toContain('const [newFirstName, setNewFirstName] = useState("")');
    expect(page).toContain('const [newLastName, setNewLastName] = useState("")');
    expect(page).toContain("requiredPersonNameFields(newFirstName, newLastName)");
    expect(page).toMatch(/createPatient\(\{[\s\S]*?\.\.\.nameFields/);
    expect(page).toContain("First Name");
    expect(page).toContain("Last Name");
    expect(page).not.toContain("Full Name");
    expect(page).toContain('autoFocus');
    expect(page).toContain('min-h-11');
    expect(page).toContain('focus-visible:ring-2');
    expect(page).toContain('onSubmit={handleCreateAndAssess}');
    expect(page).toContain('type="submit"');
  });

  test("patient table renders the display-name contract", () => {
    expect(page).toContain("personDisplayName(patient)");
    expect(patientHeader).toContain('personDisplayName(patient, "Loading patient...")');
    expect(diagnosis).toContain("personDisplayName(patient, systemId)");
  });

  test("assessment demographic edits use the paired-change rule", () => {
    expect(assessment).toContain("firstName: string");
    expect(assessment).toContain("lastName: string");
    expect(assessment).toContain("changedPersonNameFields(patient, screeningDraft.firstName, screeningDraft.lastName)");
    expect(assessment).toContain("...(nameFields ?? {})");
    expect(assessment).not.toContain("name: screeningDraft.patientName");
    expect(assessment).toContain('htmlFor="screening-first-name"');
    expect(assessment).toContain('id="screening-first-name"');
    expect(assessment).toContain('htmlFor="screening-last-name"');
    expect(assessment).toContain('id="screening-last-name"');
  });
});
