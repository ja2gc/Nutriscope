import { describe, expect, test } from "vitest";
import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";

const root = process.cwd();
const read = (path: string) =>
  existsSync(join(root, path)) ? readFileSync(join(root, path), "utf8") : "";

describe("Help page contract", () => {
  test("RND and Admin use fixed-role wrappers around one reusable page", () => {
    const rnd = read("app/(rnd)/help/page.tsx");
    const admin = read("app/admin/help/page.tsx");

    expect(rnd).toContain('<HelpPage role="RND" />');
    expect(admin).toContain('<HelpPage role="Admin" />');
    expect(rnd).toContain("useAuth");
    expect(rnd).toContain('router.replace("/admin/help")');
    expect(rnd).toContain('user?.role !== "RND"');
    expect(rnd).not.toContain('<HelpPage role="Admin" />');
    expect(admin).not.toContain('role="RND"');
  });

  test("persistent navigation labels the destination Help", () => {
    const sidebar = read("components/layout/Sidebar.tsx");
    const topBar = read("components/layout/TopBar.tsx");

    expect(sidebar).toContain('navLink("/help"');
    expect(sidebar).toContain('navLink("/admin/help"');
    expect(sidebar).toContain('"Help"');
    expect(topBar).toContain('pathname.startsWith("/help")');
    expect(topBar).toContain('pathname.startsWith("/admin/help")');
  });

  test("reusable components expose accessible search and disclosures", () => {
    const page = read("components/help/HelpPage.tsx");
    const questions = read("components/help/HelpQuestionList.tsx");

    expect(page).toContain('htmlFor="help-search"');
    expect(page).toContain('id="help-search"');
    expect(page).toContain('aria-live="polite"');
    expect(page).toContain("HelpQuestionList");
    expect(questions).toContain("aria-expanded");
    expect(questions).toContain("aria-controls");
    expect(questions).toContain("aria-labelledby");
    expect(page).not.toMatch(/role switch|View all roles|All roles/i);
  });
});
