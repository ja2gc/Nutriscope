import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(path.join(__dirname, "page.tsx"), "utf8");

describe("public FSS app landing", () => {
  it("uses the native APK handoff without redundant role copy", () => {
    expect(source).toContain("<FssAppAccess />");
    expect(source).not.toMatch(/<p[^>]*>\s*Food Service Staff\s*<\/p>/);
    expect(source).not.toContain("Download the Food Service Staff app for menu viewing");
    expect(source).not.toContain("allow installation from your browser");
    expect(source).not.toContain("QR code does not change");
  });
});
