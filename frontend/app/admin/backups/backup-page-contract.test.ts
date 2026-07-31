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
    expect(page).toContain("Backup & Recovery");
    expect(page).toContain("setInterval");
    expect(page).toContain('role="status"');
    expect(dialog).toContain('role="dialog"');
    expect(dialog).toContain('aria-modal="true"');
    expect(dialog).toContain("returnFocusRef");
    expect(page).not.toMatch(/object_key|integrity_value|BACKUP_SECRET/);
  });
});
