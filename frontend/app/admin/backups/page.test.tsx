// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import BackupsPage from "./page";
import { getBackupSchedules, listBackups } from "@/services/backupService";

vi.mock("@/services/backupService", () => ({
  listBackups: vi.fn(), getBackupSchedules: vi.fn(), createBackup: vi.fn(),
  deleteBackup: vi.fn(), keepBackup: vi.fn(), requestRecovery: vi.fn(),
  cancelRecovery: vi.fn(), updateBackupSchedules: vi.fn(),
}));

vi.mock("@/components/backups/BackupList", () => ({
  BackupList: ({ backups }: { backups: Array<{ id: string }> }) => <div data-testid="backup-list">{backups.map((backup) => backup.id).join(",")}</div>,
}));
vi.mock("@/components/backups/BackupStatusSummary", () => ({ BackupStatusSummary: () => <div>Backup status</div> }));
vi.mock("@/components/backups/BackupScheduleSettings", () => ({ BackupScheduleSettings: () => <div>Schedule settings</div> }));
vi.mock("@/components/backups/BackupActionDialog", () => ({ BackupActionDialog: () => null }));
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
});

describe("backup page loading", () => {
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
});
