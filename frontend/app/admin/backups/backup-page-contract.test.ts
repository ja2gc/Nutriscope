import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, test } from "vitest";

describe("admin backup page contract", () => {
  test("places Backups between Audit Logs and Help", () => {
    const sidebar = readFileSync(resolve(process.cwd(), "components/layout/Sidebar.tsx"), "utf8");
    expect(sidebar.indexOf('"Audit Logs"')).toBeLessThan(sidebar.indexOf('"Backups"'));
    expect(sidebar.indexOf('"Backups"')).toBeLessThan(sidebar.indexOf('"Help"'));
  });

  test("provides feedback polling confirmations and no provider details", () => {
    const page = readFileSync(resolve(process.cwd(), "app/admin/backups/page.tsx"), "utf8");
    const dialog = readFileSync(resolve(process.cwd(), "components/backups/BackupActionDialog.tsx"), "utf8");
    const summary = readFileSync(resolve(process.cwd(), "components/backups/BackupStatusSummary.tsx"), "utf8");
    const list = readFileSync(resolve(process.cwd(), "components/backups/BackupList.tsx"), "utf8");
    const tabs = readFileSync(resolve(process.cwd(), "components/ui/Tabs.tsx"), "utf8");
    expect(page).toContain("Backup & Recovery");
    expect(page).toContain("setInterval");
    expect(page).toContain('role="status"');
    expect(dialog).toContain('role="dialog"');
    expect(dialog).toContain('aria-modal="true"');
    expect(dialog).toContain("returnFocusRef");
    expect(page).not.toMatch(/object_key|integrity_value|BACKUP_SECRET/);
    expect(summary).not.toContain("Protected scope");
    expect(summary).not.toContain("Production recovery stays operator-controlled.");
    expect(summary).toContain("Recovery test:");
    expect(list).toContain("Delete failed backup");
    expect(page).toContain("Delete failed backup record?");
    expect(list).toContain("Delete permanently");
    expect(page).toContain("Permanently delete backup?");
    expect(list).toContain(">Restore</Button>");
    expect(page).toContain('from "@/components/ui/Tabs"');
    expect(page).toContain("Recently Deleted");
    expect(page).toContain('ariaLabel="Backup views"');
    expect(page).toContain("Restore points");
    expect(page).toContain("Backup activity");
    expect(page).toContain("Restoration activity");
    expect(page).toContain("Filter by backup type");
    expect(page).toContain("Pre-restore");
    expect(page).not.toContain('className="overflow-x-auto"');
    expect(page).not.toContain('ariaLabel="Backup category"');
    expect(tabs).toContain("fill = false");
    expect(page).toContain('"daily"');
    expect(page).toContain('"weekly"');
    expect(page).toContain('"monthly"');
    expect(page).toContain('"manual"');
    expect(list).not.toContain("backup.categories.map");
    expect(list).not.toContain("Kept until you delete it.");
    expect(list).toContain("Expires on");
    expect(list).not.toContain('completed: "Completed"');
    expect(list).toContain("Used for system restore");
    expect(list).toContain("Restore attempt failed");
    expect(list).toContain("Pre-restore backup protected until");
  });

  test("provides three independent default-off automatic schedule controls", () => {
    const page = readFileSync(resolve(process.cwd(), "app/admin/backups/page.tsx"), "utf8");
    const controls = readFileSync(resolve(process.cwd(), "components/backups/BackupScheduleSettings.tsx"), "utf8");
    const summary = readFileSync(resolve(process.cwd(), "components/backups/BackupStatusSummary.tsx"), "utf8");
    expect(controls).toContain("Daily backups");
    expect(controls).toContain("Weekly backups");
    expect(controls).toContain("Monthly backups");
    expect(page).toContain("confirm_disable_all");
    expect(summary).toContain("Automatic backups are disabled.");
    expect(controls).toContain("next_at");
  });

  test("reuses the shared pagination component for ten backup rows per page", () => {
    const page = readFileSync(resolve(process.cwd(), "app/admin/backups/page.tsx"), "utf8");
    expect(page).toContain('from "@/components/ui/Pagination"');
    expect(page).toContain("<Pagination");
    expect(page).toContain("listBackups(requestedPage, section, category)");
    expect(page).toContain('ariaLabel="Backup views"');
    expect(page).toContain('listBackups(1, "in_progress", "all")');
    expect(page).toContain("data?.summary.active_recovery");
    expect(page).toContain("activeRecovery.can_cancel");
  });
});
