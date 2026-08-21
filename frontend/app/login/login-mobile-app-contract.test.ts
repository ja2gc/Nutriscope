import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const frontend = path.resolve(__dirname, "../..");
const read = (relative: string) => fs.readFileSync(path.join(frontend, relative), "utf8");

describe("login native-app entry contract", () => {
  it("routes each authenticated role to its own surface", () => {
    const login = read("app/login/page.tsx");
    const root = read("app/page.tsx");
    expect(login).toContain('user.role === "FSS"');
    expect(login).toContain('router.replace("/mobile-app")');
    expect(login).toContain('router.replace("/admin/dashboard")');
    expect(login).toContain('router.replace("/dashboard")');
    expect(root).toContain('role === "FSS"');
    expect(root).toContain('redirect("/mobile-app")');
  });

  it("hands FSS users to the install page without advertising a desktop app", () => {
    const login = read("app/login/page.tsx");
    expect(login).toContain("<FssAppAccess compact />");
    const handoff = read("components/mobile-app/FssAppAccess.tsx");
    expect(handoff).toContain('href="/mobile-app"');
    expect(handoff).toContain("/downloads/nutriscope-fss.apk");
  });
});
