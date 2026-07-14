export type AuditCategory = "security" | "clinical" | "operations";
export type AuditSeverity = "info" | "notice" | "warning" | "critical";
export type AuditOutcome = "success" | "failure" | "blocked";

export interface AuditActorDto {
  id: string | null;
  kind: "user" | "system" | "anonymous";
  name: string;
  role: string | null;
}

export interface AuditEntityDto {
  type: string;
  id: string | null;
  label: string;
}

export interface AuditDetailDto {
  key: string;
  label: string;
  kind: "text" | "number" | "money" | "date" | "status" | "field_list";
  value: string | number | string[] | null;
}

export interface AuditChangeDto {
  field: string;
  label: string;
  old_value: string | number | boolean | null;
  new_value: string | number | boolean | null;
  redacted: boolean;
}

export interface AuditEventDto {
  id: string;
  category: AuditCategory;
  domain: "accounts" | "patients" | "ncp" | "reports" | "budget" | "procurement" | "food_service" | "system";
  action: string;
  action_label: string;
  summary: string;
  severity: AuditSeverity;
  outcome: AuditOutcome;
  actor: AuditActorDto | null;
  subject: AuditEntityDto | null;
  context: AuditEntityDto | null;
  occurred_at: string;
  details: AuditDetailDto[];
  changes: AuditChangeDto[];
}

export interface AuditFilterOption {
  value: string;
  label: string;
}

export interface AuditFilterMetadata {
  categories: AuditFilterOption[];
  domains: AuditFilterOption[];
  actions: AuditFilterOption[];
  outcomes: AuditFilterOption[];
  severities: AuditFilterOption[];
  category_actions: Record<AuditCategory, string[]>;
}

export interface AuditCapabilities {
  export: boolean;
  temporary_ip_block: boolean;
}

export interface AuditRetentionState {
  enabled: boolean;
  source: "config" | "database";
  periods: Record<AuditCategory | "legacy", number>;
}
