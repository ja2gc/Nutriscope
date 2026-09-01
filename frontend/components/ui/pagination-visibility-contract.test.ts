import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

function source(path: string): string {
  return readFileSync(resolve(process.cwd(), path), "utf8");
}

describe("pagination visibility contract", () => {
  test("does not hide paginated consumers when their current result is empty", () => {
    const adminUsers = source("app/admin/users/page.tsx");
    const adminNotifications = source("app/admin/notifications/page.tsx");
    const rndNotifications = source("app/(rnd)/notifications/page.tsx");
    const auditLogs = source("app/admin/audit-logs/page.tsx");
    const rndDashboard = source("app/(rnd)/dashboard/page.tsx");
    const reports = source("components/reports/ReportsBrowser.tsx");
    const recipes = source("app/(rnd)/food-service/recipes/page.tsx");
    const procurement = source("app/(rnd)/food-service/procurement/page.tsx");
    const menuCycles = source("app/(rnd)/food-service/menu-cycle/page.tsx");
    const mealPlans = source("app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx");
    const patientProfile = source("app/(rnd)/ncp/patients/[patientId]/page.tsx");

    expect(adminUsers).toContain("!loading && !error && <Pagination");
    expect(adminNotifications).toContain("!loading && <Pagination");
    expect(rndNotifications).toContain("!loading && <Pagination");
    expect(auditLogs).toContain("<Pagination meta={meta} page={page} onPageChange={setPage} />");
    expect(rndDashboard).not.toContain("!announcementsLoading && orderedPosts.length > 0");
    expect(rndDashboard).toMatch(/!announcementsLoading && \(\s*<Pagination/);
    expect(rndDashboard).toContain("!loading && <Pagination");
    expect(reports).not.toContain("!loading && reports.length > 0");
    expect(reports.match(/!loading && \(\s*<Pagination/g)).toHaveLength(2);
    expect(recipes).toContain("!loading && <Pagination");
    expect(procurement).toContain('tab !== "pos" && <Pagination');
    expect(menuCycles).not.toContain("{templates.length > 0 && (");
    expect(mealPlans).toContain("!viewingTemplate && <Pagination");
    expect(patientProfile).not.toContain("if (items.length === 0)");
  });

  test("keeps Quick Actions labels while removing decorative icons and summaries", () => {
    const dashboard = source("app/admin/dashboard/page.tsx");
    const start = dashboard.indexOf("{/* Main Grid */}");
    const end = dashboard.indexOf("{/* Right: Activity feed */}");
    const quickActions = dashboard.slice(start, end);

    expect(quickActions).toContain('href="/admin/users"');
    expect(quickActions).toContain('href="/admin/audit-logs"');
    expect(quickActions).toContain('href="/admin/announcements"');
    expect(quickActions).toContain("Manage Accounts");
    expect(quickActions).toContain("Audit Log Browser");
    expect(quickActions).toContain("Publish Feed");
    expect(quickActions).not.toContain("RBAC &amp; credentials setup");
    expect(quickActions).not.toContain("Filter &amp; monitor operational actions");
    expect(quickActions).not.toContain("Broadcast system updates");
    expect(quickActions).not.toMatch(/<(Users|Activity|Megaphone|ArrowRight)\b/);
  });
});
