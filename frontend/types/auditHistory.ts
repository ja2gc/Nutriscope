import type { AuditEventDto, AuditValueDto } from "@/types/audit";

export type AuditHistoryChange = "added" | "changed" | "removed";

export interface AuditHistoryFieldDto {
  key: string;
  label: string;
  value: AuditValueDto;
  change?: AuditHistoryChange;
}

export interface AuditHistoryTableRowDto {
  key: string;
  values: Record<string, AuditValueDto>;
  change?: AuditHistoryChange;
}

export interface AuditHistoryTableDto {
  key: string;
  label: string;
  columns: Record<string, string>;
  rows: AuditHistoryTableRowDto[];
}

export interface AuditHistorySnapshotDto {
  type: string;
  title: string;
  reference: string;
  fields: AuditHistoryFieldDto[];
  tables: AuditHistoryTableDto[];
}

export interface AuditHistoryDto {
  id: string;
  event: AuditEventDto;
  version: {
    serializer: string;
    schema_version: number;
    occurred_at: string;
  };
  before: AuditHistorySnapshotDto | null;
  after: AuditHistorySnapshotDto | null;
  read_only: true;
}
