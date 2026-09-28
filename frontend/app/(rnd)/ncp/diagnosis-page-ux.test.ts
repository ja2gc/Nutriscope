import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const page = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx"), "utf8");
const goalModal = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx"), "utf8");
const monitoring = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/LogVisitForm.tsx"), "utf8");

describe("NCP explicit save and diagnosis UX", () => {
  test("AI edit is a local draft with matching selections checked", () => {
    expect(page).toContain("splitStoredComponent(s.etiology");
    expect(page).toContain("splitStoredComponent(s.signs");
    expect(page).toContain("matchStoredOption(s.label");
    expect(page).toContain("> Edit");
    expect(page).not.toContain("Edit then Accept");
  });

  test("PES previews use light readable surfaces", () => {
    expect(page).toContain("bg-warm-50 border border-warm-200");
    expect(page).not.toContain("bg-forest-900 border border-forest-line");
    expect(page).not.toContain("text-emerald-300");
  });

  test("uses bounded assessment-based drafts with sources, evidence, cache, and persisted dismissal", () => {
    expect(page).toContain("Assessment-based PES drafts");
    expect(page).toContain("<InfoHint");
    expect(page).toContain("How PES drafts work");
    expect(page).toContain("Evidence used");
    expect(page).toContain("Source");
    expect(page).toContain("Cached for this unchanged Assessment");
    expect(page).toContain("No sufficiently supported PES draft was found.");
    expect(page).toContain("External AI drafts are unavailable");
    expect(page).toContain("dismissPesSuggestion");
    expect(page).toContain("> Dismiss");
    expect(page).not.toContain("Generate AI Suggestions");
    expect(page).not.toContain("% confidence");
    expect(page).not.toContain("conditions.push");
    expect(page).not.toContain("aiSuggestDiagnoses(ncpId, {");
    expect(page).not.toContain("Source-gated drafts use only eligible, de-identified Assessment evidence. Review and edit before accepting. Manual PES entry remains available.");
  });

  test("mutating NCP actions use explicit save wording", () => {
    expect(page).toContain("Save Diagnosis");
    expect(goalModal).toContain("Save Goal");
    expect(monitoring).toContain("Save Visit");
  });
});
