import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

const preview = readFileSync(join(process.cwd(), "components/ReportPreview.tsx"), "utf8");
const reportsBrowser = readFileSync(join(process.cwd(), "components/reports/ReportsBrowser.tsx"), "utf8");

describe("report preview contract", () => {
  it("renders PDF pages inside the application instead of relying on the browser PDF plugin", () => {
    expect(preview).toContain("pdfjs-dist/webpack.mjs");
    expect(preview).toContain("<canvas");
    expect(preview).not.toContain("<iframe");
  });

  it("defers expensive canvas rendering until a page is near the viewport", () => {
    expect(preview).toContain("IntersectionObserver");
    expect(preview).toContain('rootMargin: "800px 0px"');
    expect(preview).not.toContain("await Promise.all(");
  });

  it("reuses a parsed PDF when the same report is reopened", () => {
    expect(preview).toContain("pdfDocumentCache");
    expect(preview).toContain("loadPdfDocument(src)");
    expect(preview).toContain("MAX_CACHED_PDF_DOCUMENTS = 3");
  });

  it("keeps template edit actions aligned with their card headings", () => {
    expect(reportsBrowser).toContain('className="flex items-start justify-between gap-3"');
    expect(reportsBrowser).toContain('className="!w-auto shrink-0 !py-1.5 !px-3.5 text-sm">Edit</Button>');
  });

  it("describes accomplishment reports as semi-monthly", () => {
    expect(reportsBrowser).toContain("semi-monthly duty sheet");
    expect(reportsBrowser).not.toContain("weekly duty sheet");
  });
});
