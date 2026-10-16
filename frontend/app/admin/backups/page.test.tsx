// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import BackupsPage from "./page";
import { getBackupSchedules, keepBackup, listBackups } from "@/services/backupService";
import type { BackupRunDto } from "@/types/backup";

vi.mock("@/services/backupService", () => ({
  listBackups: vi.fn(), getBackupSchedules: vi.fn(), createBackup: vi.fn(),
  deleteBackup: vi.fn(), keepBackup: vi.fn(), requestRecovery: vi.fn(),
  cancelRecovery: vi.fn(), updateBackupSchedules: vi.fn(),
}));

vi.mock("@/components/backups/BackupList", () => ({
  BackupList: ({ backups, section, onKeep, onDelete }: { backups: BackupRunDto[]; section: string; onKeep: (backup: BackupRunDto) => void; onDelete: (backup: BackupRunDto) => void }) => (
    <div data-testid="backup-list">
      {backups.map((backup) => backup.id).join(",")}
      {section === "recently_deleted" && backups.map((backup) => <button key={backup.id} onClick={() => onKeep(backup)}>Keep backup</button>)}
      {backups.filter((backup) => backup.actions?.can_delete).map((backup) => <button key={backup.id} onClick={() => onDelete(backup)}>Delete backup</button>)}
    </div>
  ),
}));
vi.mock("@/components/backups/BackupStatusSummary", () => ({ BackupStatusSummary: () => <div>Backup status</div> }));
vi.mock("@/components/backups/BackupScheduleSettings", () => ({ BackupScheduleSettings: () => <div>Schedule settings</div> }));
vi.mock("@/components/backups/BackupActionDialog", () => ({ BackupActionDialog: ({ open, description }: { open: boolean; description: string }) => open ? <div role="dialog">{description}</div> : null }));
vi.mock("@/components/backups/RecoveryRequestDialog", () => ({ RecoveryRequestDialog: () => null }));

const list = {
  data: [{ id: "saved-point" }],
  meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
  summary: {
    status: "healthy", last_successful_at: null, next_automatic_at: null,
    scope: "Database records", storage_bytes: 0, last_recovery_test_at: null,
    active_recovery: null,
    counts: { available: 1, in_progress: 0, failed: 0, recently_deleted: 0 },
    category_counts: { daily: 1, weekly: 0, monthly: 0, manual: 0, safety: 0 },
  },
};

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let root: Root;
let container: HTMLDivElement;

afterEach(async () => {
  if (root) await act(async () => root.unmount());
  document.body.innerHTML = "";
  vi.clearAllMocks();
  vi.useRealTimers();
});

describe("backup page loading", () => {
  it.each([
    [["daily", "weekly"], "Daily and Weekly"],
    [["daily", "weekly", "monthly"], "Daily, Weekly, and Monthly"],
  ])("names every affected category before deleting a shared backup (%s)", async (categories, labels) => {
    vi.mocked(listBackups).mockImplementation(async (_page, section) => ({ ...list, data: section === "in_progress" ? [] : [{ id: "shared-point", state: "completed", source: "automatic", categories, actions: { can_delete: true } }] }) as never);
    vi.mocked(getBackupSchedules).mockResolvedValue({
      daily: { enabled: false, next_at: null }, weekly: { enabled: false, next_at: null },
      monthly: { enabled: false, next_at: null }, message: null,
    });
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => { root.render(<BackupsPage />); });
    const button = Array.from(container.querySelectorAll("button")).find((item) => item.textContent === "Delete backup");
    await act(async () => { button!.click(); });
    expect(container.querySelector('[role="dialog"]')?.textContent).toContain(`This removes the same backup from ${labels}.`);
    expect(container.querySelector('[role="dialog"]')?.textContent).toContain("Recently Deleted for 7 days");
  });

  it("refreshes idle categories and countdowns every minute", async () => {
    vi.useFakeTimers();
    vi.mocked(listBackups).mockImplementation(async (_page, section) => section === "in_progress" ? { ...list, data: [] } as never : list as never);
    vi.mocked(getBackupSchedules).mockResolvedValue({
      daily: { enabled: false, next_at: null }, weekly: { enabled: false, next_at: null },
      monthly: { enabled: false, next_at: null }, message: null,
    });
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => { root.render(<BackupsPage />); });
    vi.mocked(listBackups).mockClear();
    await act(async () => { await vi.advanceTimersByTimeAsync(60_000); });
    expect(listBackups).toHaveBeenCalledWith(1, "available", "all");
    expect(listBackups).toHaveBeenCalledWith(1, "in_progress", "all");
  });

  it("loads all restore-point types on first visit", async () => {
    vi.mocked(listBackups).mockImplementation(async (_page, section) => section === "in_progress" ? { ...list, data: [] } as never : list as never);
    vi.mocked(getBackupSchedules).mockResolvedValue({
      daily: { enabled: false, next_at: null },
      weekly: { enabled: false, next_at: null },
      monthly: { enabled: false, next_at: null }, message: null,
    });
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);

    await act(async () => { root.render(<BackupsPage />); });
    await act(async () => { await Promise.resolve(); });

    expect(listBackups).toHaveBeenCalledWith(1, "available", "all");
  });

  it("keeps the saved-point list visible when schedule loading fails", async () => {
    vi.mocked(listBackups).mockImplementation(async (_page, section) => section === "in_progress" ? { ...list, data: [] } as never : list as never);
    vi.mocked(getBackupSchedules).mockRejectedValue(new Error("schedule unavailable"));
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);

    await act(async () => { root.render(<BackupsPage />); });
    await act(async () => { await Promise.resolve(); });

    expect(container.textContent).toContain("saved-point");
    expect(container.textContent).toContain("schedule");
    expect(container.textContent).not.toContain("Backups could not be loaded");
  });

  it("hides old restore points while another tab loads", async () => {
    let finishFailed!: (value: typeof list) => void;
    const failedRequest = new Promise<typeof list>((resolve) => { finishFailed = resolve; });
    vi.mocked(listBackups).mockImplementation(async (_page, section) => {
      if (section === "in_progress") return { ...list, data: [] } as never;
      if (section === "failed") return failedRequest as never;
      return list as never;
    });
    vi.mocked(getBackupSchedules).mockResolvedValue({
      daily: { enabled: false, next_at: null },
      weekly: { enabled: false, next_at: null },
      monthly: { enabled: false, next_at: null }, message: null,
    });
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => { root.render(<BackupsPage />); });
    await act(async () => { await Promise.resolve(); });
    expect(container.textContent).toContain("saved-point");

    const failedTab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.startsWith("Failed ("));
    expect(failedTab).toBeDefined();
    await act(async () => { failedTab!.click(); });
    expect(container.textContent).toContain("Loading selected backups");
    expect(container.textContent).not.toContain("saved-point");

    await act(async () => { finishFailed({ ...list, data: [] }); });
  });

  it("shows a failed automatic backup even when its schedule period was cleared for retry", async () => {
    vi.mocked(listBackups).mockImplementation(async (_page, section, category) => {
      if (section === "in_progress") return { ...list, data: [] } as never;
      if (section === "failed") {
        return { ...list, data: category === "all" ? [{ id: "failed-without-period" }] : [], summary: {
          ...list.summary,
          counts: { ...list.summary.counts, failed: 1 },
          category_counts: { ...list.summary.category_counts, daily: 0 },
        } } as never;
      }
      return list as never;
    });
    vi.mocked(getBackupSchedules).mockResolvedValue({
      daily: { enabled: false, next_at: null },
      weekly: { enabled: false, next_at: null },
      monthly: { enabled: false, next_at: null }, message: null,
    });
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);

    await act(async () => { root.render(<BackupsPage />); });
    await act(async () => { await Promise.resolve(); });
    const failedTab = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.startsWith("Failed ("));
    await act(async () => { failedTab!.click(); });
    await act(async () => { await Promise.resolve(); });

    expect(container.textContent).toContain("failed-without-period");
  });

  it("keeps the current type filter when an earlier Keep backup action finishes", async () => {
    const deleted: BackupRunDto = {
      id: "deleted-manual", state: "recently_deleted", source: "manual", categories: ["manual"],
      size_bytes: 1024, encrypted: true, retention_tier: null, retention_expires_at: null,
      retention_is_active: false, queued_at: "2026-10-10T03:23:50Z", started_at: "2026-10-10T03:23:51Z",
      verified_at: "2026-10-10T03:24:22Z", recoverable_until: "2026-10-18T00:00:00Z", failure: null,
      recovery: null, actions: { can_delete: false, can_purge: true, can_keep: true, can_request_recovery: false },
    };
    const daily: BackupRunDto = { ...deleted, id: "daily-point", state: "completed", source: "automatic", categories: ["daily"], recoverable_until: null };
    let finishKeep!: (value: BackupRunDto) => void;
    const keepRequest = new Promise<BackupRunDto>((resolve) => { finishKeep = resolve; });
    let kept = false;
    vi.mocked(keepBackup).mockReturnValue(keepRequest);
    vi.mocked(listBackups).mockImplementation(async (_page, section, category) => ({
      ...list,
      data: section === "in_progress" ? [] : section === "recently_deleted" ? (kept ? [] : [deleted]) : category === "daily" ? [daily] : [daily, deleted],
    }) as never);
    vi.mocked(getBackupSchedules).mockResolvedValue({
      daily: { enabled: false, next_at: null }, weekly: { enabled: false, next_at: null },
      monthly: { enabled: false, next_at: null }, message: null,
    });
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
    await act(async () => { root.render(<BackupsPage />); });
    const click = async (label: string) => {
      const button = Array.from(container.querySelectorAll("button")).find((item) => item.textContent?.startsWith(label));
      expect(button).toBeDefined();
      await act(async () => { button!.click(); });
    };

    await click("Recently deleted (");
    await click("Keep backup");
    await click("Restore points (");
    await click("Daily (");
    expect(container.querySelector('[data-testid="backup-list"]')?.textContent).toBe("daily-point");

    kept = true;
    await act(async () => { finishKeep({ ...deleted, state: "completed", recoverable_until: null }); });

    expect(container.querySelector('[aria-pressed="true"]')?.textContent).toMatch(/^Daily /);
    expect(container.querySelector('[data-testid="backup-list"]')?.textContent).toBe("daily-point");
  });
});
