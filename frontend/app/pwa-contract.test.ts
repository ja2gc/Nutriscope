import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const root = path.resolve(__dirname, "..");
const read = (relative: string) => fs.readFileSync(path.join(root, relative), "utf8");

describe("PWA security contract", () => {
  it("gives only the FSS surface a stable, scoped install identity", () => {
    const manifest = JSON.parse(read("public/fss.webmanifest"));
    expect(manifest).toMatchObject({
      id: "/fss/",
      start_url: "/fss/",
      scope: "/fss/",
      display: "standalone",
    });
    expect(manifest.icons.every((icon: { purpose?: string }) => icon.purpose === "any maskable")).toBe(true);
    expect(fs.existsSync(path.join(root, "app/manifest.ts"))).toBe(false);
    expect(read("app/fss/layout.tsx")).toContain('manifest: "/fss.webmanifest"');
    expect(read("app/mobile-app/layout.tsx")).toContain('manifest: "/fss.webmanifest"');
  });

  it("never caches APIs or non-GET requests", () => {
    const worker = read("public/sw.js");
    expect(worker).toContain("url.pathname.startsWith('/api/')");
    expect(worker).toContain("request.method !== 'GET'");
    expect(worker).not.toMatch(/caches\.put\([^\n]*(?:api|request)/i);
  });

  it("activates updates only after the app asks the waiting worker", () => {
    const worker = read("public/sw.js");
    expect(worker).toContain("nutriscope-fss-static-v2");
    expect(worker).toContain("SKIP_WAITING");
    const installHandler = worker.slice(worker.indexOf("addEventListener('install'"), worker.indexOf("addEventListener('message'"));
    expect(installHandler).not.toContain("skipWaiting");
  });

  it("keeps install and offline handoff routes public", () => {
    const middleware = read("middleware.ts");
    expect(middleware).toContain('pathname === "/mobile-app"');
    expect(middleware).toContain('pathname === "/offline"');
    expect(read("app/offline/page.tsx")).toContain("NutriScope is offline");
  });
});
