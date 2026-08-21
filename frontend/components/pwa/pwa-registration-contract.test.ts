import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(path.join(__dirname, "PwaRegistration.tsx"), "utf8");

describe("PWA update registration", () => {
  it("registers only for FSS surfaces with the FSS scope", () => {
    expect(source).toContain('pathname.startsWith("/fss")');
    expect(source).toContain('pathname === "/mobile-app"');
    expect(source).toContain('scope: "/fss/"');
    expect(source).toContain("if (!pwaSurface || !waitingWorker) return null");
  });

  it("waits for explicit approval before activating an update", () => {
    expect(source).toContain("registration.waiting");
    expect(source).toContain('postMessage({ type: "SKIP_WAITING" })');
    expect(source).toContain("Update available");
    expect(source).toContain("Update and restart");
    expect(source).toContain("controllerchange");
  });
});
