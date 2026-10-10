import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const read = (path: string) => readFileSync(join(process.cwd(), path), "utf8");

describe("intervention text limits", () => {
  test("renders the shared per-field counter and preserves existing limits", () => {
    const education = read("app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EducationTab.tsx");
    const counseling = read("app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/CounselingTab.tsx");
    for (const source of [education, counseling]) {
      expect(source).toContain("CharacterCountTextarea");
      expect(source).toContain("INTERVENTION_GUIDANCE_FIELD_MAX");
      expect(source).toContain("GuidanceCharacterCount");
    }
  });
});
