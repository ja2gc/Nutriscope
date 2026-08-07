import { beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import { createBackup, getBackupSchedules, listBackups, requestRecovery, updateBackupSchedules } from "./backupService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

const apiFetchMock = vi.mocked(apiFetch);

describe("backup service", () => {
  beforeEach(() => apiFetchMock.mockReset());

  test("returns privacy-safe list and summary data", async () => {
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({
      data: [{ id: "backup-1", state: "completed", source: "automatic", size_bytes: 100, encrypted: true, retention_tier: "daily", retention_expires_at: null, queued_at: null, started_at: null, verified_at: "2026-08-01T01:30:00+08:00", recoverable_until: null, failure: null, actions: { can_delete: false, can_keep: false, can_request_recovery: true } }],
      meta: { status: "healthy", last_successful_at: "2026-08-01T01:30:00+08:00", next_automatic_at: "2026-08-02T01:30:00+08:00", scope: "Database records", storage_bytes: 100, last_recovery_test_at: null },
    }), { status: 200, headers: { "Content-Type": "application/json" } }));

    const result = await listBackups();

    expect(result.data[0].id).toBe("backup-1");
    expect(result.meta.status).toBe("healthy");
  });

  test("uses protected endpoints for create and recovery", async () => {
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: { id: "backup-1", state: "queued" } }), { status: 202, headers: { "Content-Type": "application/json" } }));
    await createBackup();
    expect(apiFetchMock).toHaveBeenLastCalledWith("/api/admin/backups", expect.objectContaining({ method: "POST" }));

    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: { id: "request-1", state: "requested" } }), { status: 201, headers: { "Content-Type": "application/json" } }));
    await requestRecovery("backup-1", {
      incident_type: "damaged_database",
      note: "Database records cannot be opened.",
      current_password: "test-password",
      confirmation: "RESTORE backup-1",
    });
    expect(apiFetchMock).toHaveBeenLastCalledWith(
      "/api/admin/backups/backup-1/recovery-requests",
      expect.objectContaining({ method: "POST" }),
    );
  });

  test("loads and updates independent schedule booleans", async () => {
    const schedules = { daily: { enabled: false, next_at: null }, weekly: { enabled: true, next_at: "2026-08-09T01:30:00+08:00" }, monthly: { enabled: false, next_at: null }, message: null };
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: schedules }), { status: 200, headers: { "Content-Type": "application/json" } }));
    expect((await getBackupSchedules()).weekly.enabled).toBe(true);

    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({ data: schedules }), { status: 200, headers: { "Content-Type": "application/json" } }));
    await updateBackupSchedules({ daily: false, weekly: true, monthly: false });
    expect(apiFetchMock).toHaveBeenLastCalledWith("/api/admin/backup-schedules", expect.objectContaining({ method: "PUT", body: JSON.stringify({ daily: false, weekly: true, monthly: false }) }));
  });
});
