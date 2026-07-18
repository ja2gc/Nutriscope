import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const page = readFileSync(
  join(process.cwd(), "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx"),
  "utf8",
);

describe("Assessment page UX", () => {
  test("uses compact responsive grids without reducing field text size", () => {
    expect(page).toContain("xl:grid-cols-6");
    expect(page).toContain("xl:grid-cols-4");
    expect(page).toContain("AssessmentSection");
    expect(page).toContain("text-sm");
  });

  test("keeps mobile layouts single-column and navigation reachable", () => {
    expect(page).toContain("grid-cols-1");
    expect(page).toContain("overflow-x-auto");
    expect(page).toContain("xl:grid-cols-[");
    expect(page).toContain('aria-selected={');
  });

  test("splits referral work into accessible local sections", () => {
    expect(page).toContain('role="tablist"');
    expect(page).toContain('role="tab"');
    expect(page).toContain("Referral details");
    expect(page).toContain("Clinical conditions");
    expect(page).toContain("Intake / weight history");
  });

  test("keeps verbose lab guidance and documents progressively disclosed", () => {
    expect(page).toContain("Reference guidance");
    expect(page).toContain("Supporting documents");
    expect(page).toContain("<details");
  });

  test("offers explicit editable summary generation with stale and undo states", () => {
    expect(page).toContain("buildAssessmentSummary");
    expect(page).toContain("Generate Summary");
    expect(page).toContain("Regenerate Summary");
    expect(page).toContain("Assessment changed - regenerate to refresh");
    expect(page).toContain("Undo");
    expect(page).toContain("onChange={handleSummaryChange}");
  });
});
