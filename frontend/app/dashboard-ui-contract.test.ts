import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

const source = (path: string) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("dashboard presentation contract", () => {
  test("RND dashboard uses a plain title, compact cards, and requested copy", () => {
    const dashboard = source("app/(rnd)/dashboard/page.tsx");

    expect(dashboard).toContain(">Dashboard<");
    expect(dashboard).not.toContain("Good morning");
    expect(dashboard).not.toContain("Open NCP or resolve scheduled attendance.");
    expect(dashboard).not.toContain("Manage announcements");
    expect(dashboard).toContain("Go to announcement");
    expect(dashboard).not.toContain("pendingKpi.sub");
    expect(dashboard).not.toMatch(/<(Compass|HeartHandshake|Calendar|TrendingUp)\b/);
    expect(dashboard).toContain("grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))]");
    expect(dashboard).toContain("grid-cols-[repeat(auto-fit,minmax(min(100%,9.5rem),1fr))]");
    expect(dashboard).not.toContain("grid-cols-[repeat(auto-fit,minmax(min(100%,11rem),1fr))]");
    expect(dashboard).not.toContain("xl:h-[480px]");
  });

  test("Admin dashboard has no decorative title or card icons and packs cards by minimum width", () => {
    const dashboard = source("app/admin/dashboard/page.tsx");

    expect(dashboard).toContain("grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))]");
    expect(dashboard).toContain("grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))]");
    expect(dashboard).toMatch(/\{\/\* Inline edit \*\/\}[\s\S]{0,160}grid-cols-\[repeat\(auto-fit,minmax\(min\(100%,15rem\),1fr\)\)\]/);
    expect(dashboard).not.toMatch(/<(LayoutDashboard|Cpu|Activity)\b/);
    expect(dashboard).not.toContain("Refreshing...");
  });
});
