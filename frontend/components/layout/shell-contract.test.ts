import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string): string {
  return readFileSync(resolve(process.cwd(), path), "utf8");
}

describe("shared application shell contract", () => {
  test("uses one forest header without a duplicate module title or logout action", () => {
    const topBar = source("components/layout/TopBar.tsx");

    expect(topBar).toContain("bg-forest-900 border-forest-line text-white");
    expect(topBar).not.toContain("getModuleTitle");
    expect(topBar).not.toContain("Overview & Operations Center");
    expect(topBar).not.toContain("Patient Nutrition Care Center");
    expect(topBar).not.toContain("Sign Out");
    expect(topBar).not.toContain("handleLogout");
  });

  test("puts logout in the sidebar footer", () => {
    const sidebar = source("components/layout/Sidebar.tsx");

    expect(sidebar).toContain("Log Out");
    expect(sidebar).toContain("mt-auto");
    expect(sidebar).toContain("await logout()");
  });

  test("does not render decorative page-title icons", () => {
    const pageHeader = source("components/ui/PageHeader.tsx");

    expect(pageHeader).not.toContain("{icon}");
  });
});
