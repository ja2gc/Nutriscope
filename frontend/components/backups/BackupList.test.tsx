import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { afterEach, expect, test, vi } from "vitest";
import { BackupList } from "./BackupList";
import type { BackupRunDto } from "@/types/backup";

afterEach(() => vi.useRealTimers());

test("shows independent category countdowns and the shared archive's final expiration", () => {
  vi.useFakeTimers();
  vi.setSystemTime(new Date("2026-11-01T01:30:00+08:00"));
  const backup = {
    id: "shared-point", state: "completed", source: "automatic",
    categories: ["daily", "weekly", "monthly"], size_bytes: 100,
    retention_expires_at: "2027-02-01T01:30:00+08:00", retention_is_active: true,
    schedule_expirations: [
      { category: "daily", expires_at: "2026-11-05T01:30:00+08:00" },
      { category: "weekly", expires_at: "2026-11-15T01:30:00+08:00" },
      { category: "monthly", expires_at: "2027-02-01T01:30:00+08:00" },
    ],
    actions: {},
  } as BackupRunDto;
  const render = (value = backup) => renderToStaticMarkup(<BackupList backups={[value]} section="available" busyId={null} onDelete={vi.fn()} onKeep={vi.fn()} onRecovery={vi.fn()} onCancelRecovery={vi.fn()} />);
  const html = render();
  expect(html).toContain("Daily: 4 days left");
  expect(html).toContain("Weekly: 14 days left");
  expect(html).toContain("Monthly: 92 days left");
  expect(html).toContain("Final expiration:");
  expect(html.match(/Backup reference:/g)).toHaveLength(1);
  vi.setSystemTime(new Date("2026-11-05T01:00:00+08:00"));
  expect(render()).toContain("Daily: 30 minutes left");
  vi.setSystemTime(new Date("2026-11-05T01:30:00+08:00"));
  expect(render({ ...backup, categories: ["weekly", "monthly"], schedule_expirations: backup.schedule_expirations!.slice(1) })).not.toContain("Daily:");
  expect(render({ ...backup, source: "manual", categories: ["manual"], schedule_expirations: [], retention_expires_at: null })).not.toContain("Final expiration:");
});
