import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const source = (path: string) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("report table responsive layout", () => {
  it("keeps archived report columns on one line inside a horizontal scroller", () => {
    const reports = source("components/reports/ReportsBrowser.tsx");
    expect(reports).toContain('className="overflow-x-auto"');
    expect(reports).toContain('className="w-full min-w-[640px] whitespace-nowrap text-sm"');
  });
});
