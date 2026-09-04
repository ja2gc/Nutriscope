export type BackupState = "queued" | "running" | "verifying" | "completed" | "failed" | "recently_deleted" | "purged";
export type BackupSource = "automatic" | "manual" | "safety";
export type RecoveryStatus = "requested" | "preparing" | "checking" | "ready" | "switching" | "completed" | "failed" | "rolled_back" | "cancelled";
export type BackupRetentionTier = "daily" | "weekly" | "monthly";
export type BackupSection = "available" | "in_progress" | "failed" | "recently_deleted";
export type BackupCategory = "daily" | "weekly" | "monthly" | "manual" | "safety";
export type RecoveryIncidentType = "website_unavailable" | "damaged_database" | "accidentally_deleted_records" | "missing_upload" | "bad_deployment";

export interface BackupRunDto {
  id: string;
  state: BackupState;
  source: BackupSource;
  categories: BackupCategory[];
  size_bytes: number | null;
  encrypted: boolean;
  retention_tier: BackupRetentionTier | null;
  retention_expires_at: string | null;
  queued_at: string | null;
  started_at: string | null;
  verified_at: string | null;
  recoverable_until: string | null;
  failure: { code: string | null; message: string | null } | null;
  recovery?: { id: string; state: RecoveryStatus; requested_at: string | null; resolved_at: string | null; safety_snapshot_expires_at: string | null; failure_message: string | null; can_cancel: boolean } | null;
  actions: { can_delete: boolean; can_purge: boolean; can_keep: boolean; can_request_recovery: boolean };
}

export interface BackupSummaryDto {
  status: "healthy" | "attention_needed" | "failed";
  last_successful_at: string | null;
  next_automatic_at: string | null;
  scope: string;
  storage_bytes: number;
  last_recovery_test_at: string | null;
  counts: Record<BackupSection, number>;
  category_counts: Record<BackupCategory, number>;
}

export interface BackupScheduleOptionDto {
  enabled: boolean;
  next_at: string | null;
}

export interface BackupSchedulesDto {
  daily: BackupScheduleOptionDto;
  weekly: BackupScheduleOptionDto;
  monthly: BackupScheduleOptionDto;
  message: string | null;
}

export interface BackupScheduleInput {
  daily: boolean;
  weekly: boolean;
  monthly: boolean;
  confirm_disable_all?: boolean;
}

export interface BackupListResponse {
  data: BackupRunDto[];
  meta: PaginationMeta;
  summary: BackupSummaryDto;
}

export interface RecoveryRequestInput {
  incident_type: RecoveryIncidentType;
  note: string;
  current_password: string;
  confirmation: string;
}
import type { PaginationMeta } from "@/components/ui/Pagination";
