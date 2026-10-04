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
    expect(editor).toContain("brandingLogoUrl(apiPrefix, \"left\")");
    expect(editor).toContain("brandingLogoUrl(apiPrefix, \"right\")");
    expect(editor).toContain('alt="Current left report logo"');
    expect(editor).toContain('alt="Current right report logo"');
  });

  test("offers template editing to Admin through Admin report APIs", () => {
    const browser = readFileSync(join(root, "components", "reports", "ReportsBrowser.tsx"), "utf8");
    const service = readFileSync(join(root, "services", "reportService.ts"), "utf8");
    const adminList = readFileSync(join(root, "app", "api", "admin", "report-templates", "route.ts"), "utf8");
    const adminUpdate = readFileSync(join(root, "app", "api", "admin", "report-templates", "[id]", "route.ts"), "utf8");

    expect(browser).toContain('apiPrefix !== "fss"');
    expect(browser).toContain('<TemplateEditor apiPrefix={apiPrefix}');
    expect(service).toContain('`/api/${apiPrefix}/report-templates`');
    expect(service).toContain('`/api/${apiPrefix}/report-templates/${id}`');
    expect(adminList).toContain('proxy("/admin/report-templates")');
    expect(adminUpdate).toContain('proxy(`/admin/report-templates/${id}`');
  });
});
