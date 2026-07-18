import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const banner = readFileSync(join(process.cwd(), "components/announcements/SopBanner.tsx"), "utf8");

describe("SOP banner history UX", () => {
  test("keeps revise wording and uses automatic date-labeled collapsible history", () => {
    expect(banner).toContain('sop ? "Revise" : "Set SOP"');
    expect(banner).toContain("<details");
    expect(banner).toContain("<summary");
    expect(banner).toContain("formatTimeStamp(v.created_at)");
    expect(banner).not.toContain("historyName");
  });

  test("shows recorded authors for current and historical SOP versions", () => {
    expect(banner).toContain("Last changed by");
    expect(banner).toContain("Created by");
    expect(banner).toContain("v.author?.role");
  });
});
