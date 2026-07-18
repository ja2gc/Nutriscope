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

  test("auto-grows long text without nested field scrolling or equal-height stretching", () => {
    expect(page).toContain("useLayoutEffect");
    expect(page).toContain("scrollHeight");
    expect(page).toContain("overflow-hidden");
    expect(page).toContain("resize-none");
    expect(page).toContain("items-start");
    expect(page).not.toContain("resize-y");
  });

  test("puts explicit save above tabs and removes cycle merge copy", () => {
    expect(page.indexOf("Save Assessment")).toBeLessThan(page.indexOf("{/* Tab Navigation */}"));
    expect(page).not.toContain("All tabs auto-merge");
    expect(page).not.toContain("NCP Cycle #{ncpId}");
  });

  test("keeps both attachment panels below their clinical fields", () => {
    expect(page.indexOf('kind="labs"')).toBeGreaterThan(page.indexOf('legend="Lab values"'));
    expect(page.indexOf('kind="referral"')).toBeGreaterThan(page.indexOf('label="Referral Date & Time"'));
  });

  test("shows BMI class in its card without a separate suggested-goal banner", () => {
    expect(page).toContain("computedBmiClassification.label");
    expect(page).not.toContain("Suggested Goal");
    expect(page).not.toContain("Nutritional Status Badge");
  });

  test("explains current automatic risk rules without changing them", () => {
    expect(page).toContain("How automatic scoring works");
    expect(page).toContain("IBW below 85% or above 130%");
    expect(page).toContain("Albumin below 3.5 g/dL");
    expect(page).toContain("Glucose below 70 or above 125 mg/dL");
  });
});
