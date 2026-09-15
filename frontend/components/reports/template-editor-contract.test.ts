import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

describe("report template editor", () => {
  test("uses display-first edit save cancel states and hides clinical auto-filled signatories", () => {
    const source = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");

    expect(source).toContain("editingBranding");
    expect(source).toContain("editingTemplateId");
    expect(source).toContain("Cancel");
    expect(source).toContain("isClinicalAutoFilledSignatory");
  });

  test("uses the visible shared image picker and symmetric report logo box", () => {
    const editor = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const adminSettings = readFileSync(join(root, "app", "admin", "settings", "page.tsx"), "utf8");
    const letterhead = readFileSync(join(root, "..", "backend", "resources", "views", "reports", "partials", "letterhead.blade.php"), "utf8");

    expect(editor).toContain("<ImageFilePicker");
    expect(adminSettings).toContain("<ImageFilePicker");
    expect(adminSettings).toContain("editingBranding");
    expect(letterhead).toContain("width:56px; height:56px; object-fit:contain;");
  });
});
