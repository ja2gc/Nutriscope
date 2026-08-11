import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const root = path.resolve(__dirname, "..");
const read = (relative: string) => fs.readFileSync(path.join(root, relative), "utf8");

describe("PWA security contract", () => {
  it("starts the standalone app at the FSS route", () => {
    const manifest = read("app/manifest.ts");
    expect(manifest).toContain('start_url: "/fss"');
    expect(manifest).toContain('display: "standalone"');
  });

  it("never caches APIs or non-GET requests", () => {
    const worker = read("public/sw.js");
    expect(worker).toContain("url.pathname.startsWith('/api/')");
    expect(worker).toContain("request.method !== 'GET'");
    expect(worker).not.toMatch(/caches\.put\([^\n]*(?:api|request)/i);
  });

  it("keeps install and offline handoff routes public", () => {
    const middleware = read("middleware.ts");
    expect(middleware).toContain('pathname === "/mobile-app"');
    expect(middleware).toContain('pathname === "/offline"');
    expect(read("app/offline/page.tsx")).toContain("NutriScope is offline");
  });
});
