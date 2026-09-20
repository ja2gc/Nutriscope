import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const frontend = path.resolve(__dirname, "../..");
const read = (relative: string) => fs.readFileSync(path.join(frontend, relative), "utf8");

describe("login native-app entry contract", () => {
  it("landing page centers only an enlarged brand lockup on the image panel", () => {
    const login = read("app/login/page.tsx");

    expect(login).not.toContain("featureItems");
    expect(login).not.toContain("Precision nutrition.");
    expect(login).toContain('<Logo variant="dark" />');
    expect(login).toContain('aria-label="NutriScope"');
    expect(login).not.toContain("min-h-[11rem] w-full max-w-[27rem]");
    expect(login).not.toContain("rounded-3xl bg-forest-950/45");
    expect(login).toContain('className="relative z-10 origin-center scale-[3.25]"');
    expect(login).toContain("lg:items-center lg:justify-center");
    expect(login).toContain('className="text-center"');
  });

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
