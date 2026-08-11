import { describe, expect, it } from "vitest";
import fs from "node:fs";
import path from "node:path";

const frontend = path.resolve(__dirname, "../..");
const read = (relative: string) => fs.readFileSync(path.join(frontend, relative), "utf8");

describe("FSS web shell contract", () => {
  it("uses five exact mobile tabs", () => {
    const shell = read("components/fss/FssShell.tsx");
    for (const pair of [
      ['label: "Home"', 'href: "/fss"'],
      ['label: "Menu"', 'href: "/fss/menu"'],
      ['label: "Meal Prep"', 'href: "/fss/meal-prep"'],
      ['label: "Accomplish"', 'href: "/fss/accomplish"'],
      ['label: "Purchase"', 'href: "/fss/purchase"'],
    ]) {
      expect(shell).toContain(pair[0]);
      expect(shell).toContain(pair[1]);
    }
    expect((shell.match(/label:/g) ?? []).length).toBe(5);
  });

  it("guards FSS routes and hands desktop users to the QR", () => {
    const shell = read("components/fss/FssShell.tsx");
    expect(shell).toContain('user.role !== "FSS"');
    expect(shell).toContain("FssDesktopHandoff");
    expect(shell).not.toContain("Sidebar");
  });

  it("keeps FSS out of the RND shell", () => {
    const rndLayout = read("app/(rnd)/layout.tsx");
    expect(rndLayout).toContain('user.role !== "RND"');
  });

  it("reuses existing menu, purchase, and FSS reports", () => {
    expect(read("app/fss/menu/page.tsx")).toContain('@/app/(rnd)/food-service/menu-cycle/page');
    expect(read("app/fss/purchase/page.tsx")).toContain('@/app/(rnd)/food-service/procurement/page');
    const accomplish = read("app/fss/accomplish/page.tsx");
    expect(accomplish).toContain("FSS_CATALOG");
    expect(accomplish).toContain('apiPrefix="fss"');
  });
});
