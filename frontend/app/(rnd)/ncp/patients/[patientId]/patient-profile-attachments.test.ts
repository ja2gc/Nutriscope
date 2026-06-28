import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const source = () =>
  readFileSync(join(process.cwd(), "app/(rnd)/ncp/patients/[patientId]/page.tsx"), "utf8");

describe("patient profile attachments tab", () => {
  test("uses assessment-style attachment actions and display names", () => {
    const page = source();

    expect(page).toContain("deleteAttachment");
    expect(page).toContain("getAttachmentDisplayName");
    expect(page).toContain("title=\"View\"");
    expect(page).toContain("title=\"Download\"");
    expect(page).toContain("title=\"Delete\"");
    expect(page).toContain("Biochemical Data");
    expect(page).toContain("Screening Form");
  });
});
