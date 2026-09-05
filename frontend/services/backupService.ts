import { apiFetch } from "@/lib/apiFetch";
import type { BackupCategoryFilter, BackupListResponse, BackupRunDto, BackupScheduleInput, BackupSchedulesDto, BackupSection, RecoveryRequestInput } from "@/types/backup";

export class BackupServiceError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
    this.name = "BackupServiceError";
  }
}

async function unwrap<T>(response: Response, fallback: string): Promise<T> {
  const payload = await response.json().catch(() => null) as { data?: T; message?: string } | null;
  if (!response.ok) {
    throw new BackupServiceError(payload?.message || fallback, response.status);
  }
  if (payload?.data === undefined) {
    throw new BackupServiceError(fallback, 502);
  }
  return payload.data;
}

export async function listBackups(page = 1, section: BackupSection = "available", category: BackupCategoryFilter = "daily"): Promise<BackupListResponse> {
  const response = await apiFetch(`/api/admin/backups?page=${page}&section=${section}&category=${category}`, { headers: { Accept: "application/json" } });
  const payload = await response.json().catch(() => null) as BackupListResponse | { message?: string } | null;
  if (!response.ok || !payload || !("data" in payload) || !("meta" in payload) || !("summary" in payload)) {
    throw new BackupServiceError((payload && "message" in payload && payload.message) || "Backups could not be loaded.", response.ok ? 502 : response.status);
  }
  return payload;
}

export async function createBackup(): Promise<BackupRunDto> {
  return unwrap(await apiFetch("/api/admin/backups", {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  }), "Backup could not be queued.");
}

export async function getBackupSchedules(): Promise<BackupSchedulesDto> {
  return unwrap(await apiFetch("/api/admin/backup-schedules", {
    headers: { Accept: "application/json" },
  }), "Automatic backup settings could not be loaded.");
}

export async function updateBackupSchedules(input: BackupScheduleInput): Promise<BackupSchedulesDto> {
  return unwrap(await apiFetch("/api/admin/backup-schedules", {
    method: "PUT",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(input),
  }), "Automatic backup settings could not be updated.");
}

export async function deleteBackup(id: string): Promise<BackupRunDto> {
  return unwrap(await apiFetch(`/api/admin/backups/${encodeURIComponent(id)}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  }), "Backup could not be deleted.");
}

export async function keepBackup(id: string): Promise<BackupRunDto> {
  return unwrap(await apiFetch(`/api/admin/backups/${encodeURIComponent(id)}/keep`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  }), "Backup could not be kept.");
}

export async function requestRecovery(id: string, input: RecoveryRequestInput): Promise<{ id: string; state: "requested" }> {
  return unwrap(await apiFetch(`/api/admin/backups/${encodeURIComponent(id)}/recovery-requests`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify(input),
  }), "Recovery request could not be sent.");
}

export async function cancelRecovery(id: string): Promise<void> {
  await unwrap(await apiFetch(`/api/admin/recovery-requests/${encodeURIComponent(id)}/cancel`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
  }), "Recovery could not be cancelled.");
}
