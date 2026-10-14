import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const banner = readFileSync(join(process.cwd(), "components/announcements/SopBanner.tsx"), "utf8");
const historyPage = readFileSync(join(process.cwd(), "components/announcements/SopHistoryPage.tsx"), "utf8");

describe("SOP banner history UX", () => {
  test("keeps revise wording and opens history as a page with list and detail", () => {
    expect(banner).toContain('sop ? "Revise" : "Set SOP"');
    expect(banner).toContain("href={historyHref}");
    expect(banner).not.toContain("<details");
    expect(historyPage).toContain("setSelectedVersion(version)");
    expect(historyPage).toContain("Back to history");
    expect(historyPage).toContain("<Pagination");
  });

  test("shows recorded authors for current and historical SOP versions", () => {
    expect(banner).toContain("Last changed by");
    expect(historyPage).toContain("Created by");
    expect(historyPage).toContain("selectedVersion.author?.role");
  });
});
