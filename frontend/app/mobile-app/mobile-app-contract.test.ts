import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(path.join(__dirname, "page.tsx"), "utf8");

describe("public FSS app landing", () => {
  it("uses the native APK handoff and identifies the intended role", () => {
    expect(source).toContain("<FssAppAccess />");
    expect(source).toContain("Food Service Staff");
    expect(source).toContain("QR code does not change");
  });
});
