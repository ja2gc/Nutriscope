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

  it("describes accomplishment reports as semi-monthly", () => {
    expect(reportsBrowser).toContain("semi-monthly duty sheet");
    expect(reportsBrowser).not.toContain("weekly duty sheet");
  });
});
