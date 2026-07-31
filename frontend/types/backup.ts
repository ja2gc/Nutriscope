export type BackupState = "queued" | "running" | "verifying" | "completed" | "failed" | "recently_deleted" | "purged";
export type BackupSource = "automatic" | "manual";
export type BackupRetentionTier = "daily" | "weekly" | "monthly";
export type RecoveryIncidentType = "website_unavailable" | "damaged_database" | "accidentally_deleted_records" | "missing_upload" | "bad_deployment";

export interface BackupRunDto {
  id: string;
  state: BackupState;
  source: BackupSource;
  size_bytes: number | null;
  encrypted: boolean;
  retention_tier: BackupRetentionTier | null;
  retention_expires_at: string | null;
  queued_at: string | null;
  started_at: string | null;
  verified_at: string | null;
  recoverable_until: string | null;
  failure: { code: string | null; message: string | null } | null;
  actions: { can_delete: boolean; can_keep: boolean; can_request_recovery: boolean };
}

export interface BackupSummaryDto {
  status: "healthy" | "attention_needed" | "failed";
  last_successful_at: string | null;
  next_automatic_at: string;
  scope: string;
  storage_bytes: number;
  last_recovery_test_at: string | null;
}

export interface BackupListResponse {
  data: BackupRunDto[];
  meta: BackupSummaryDto;
}

export interface RecoveryRequestInput {
  incident_type: RecoveryIncidentType;
  note: string;
}
