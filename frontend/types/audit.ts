export type AuditCategory = "security" | "clinical" | "operations";
export type AuditModule = "security_administration" | "nutrition_care" | "food_service_operations" | "reports";
export type AuditSeverity = "info" | "notice" | "warning" | "critical";
export type AuditOutcome = "success" | "failure" | "blocked";
export type AuditValueType = "text" | "number" | "currency" | "quantity" | "boolean" | "date" | "datetime" | "enum" | "reference" | "field_list" | "redacted";
export type AuditScalar = string | number | boolean | string[] | null;

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
  kind: AuditValueType;
  value: AuditScalar;
  typed_value: AuditValueDto;
}

export interface AuditValueDto {
  type: AuditValueType;
  value: AuditScalar;
  unit?: string;
  currency?: string;
}

export interface AuditChangeDto {
  field: string;
  label: string;
  old_value: AuditScalar;
  new_value: AuditScalar;
  before: AuditValueDto;
  after: AuditValueDto;
  redacted: boolean;
}

export interface AuditHistoryLinkDto {
  id: string;
  action: string;
  label: string;
  url: string;
}

export interface AuditEventDto {
  id: string;
  module: AuditModule | "legacy_unclassified";
  category: AuditCategory | "legacy_unclassified";
  domain: "accounts" | "patients" | "ncp" | "reports" | "budget" | "procurement" | "food_service" | "nutrition_library" | "system" | "legacy_unclassified";
  record_type: string;
  action: string;
  action_label: string;
  summary: string;
  severity: AuditSeverity;
  outcome: AuditOutcome;
  actor: AuditActorDto | null;
  subject: AuditEntityDto | null;
  context: AuditEntityDto | null;
  patient: { display_name: string } | null;
  ncp_reference: string | null;
  detail_mode: "field_names" | "changes" | "history";
  reason: string | null;
  history: AuditHistoryLinkDto | null;
  current_record_url: string | null;
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
  modules: AuditFilterOption[];
  actions: AuditFilterOption[];
  outcomes: AuditFilterOption[];
  severities: AuditFilterOption[];
  category_actions: Record<AuditCategory, string[]>;
  module_subfilters: Record<AuditModule, AuditFilterOption[]>;
  module_actions: Record<AuditModule, string[]>;
  module_counts: Record<"all" | AuditModule, number>;
}

export interface AuditCapabilities {
  export: boolean;
}

export interface AuditRetentionState {
  enabled: boolean;
  source: "config" | "database";
  periods: Record<AuditCategory | "legacy", number>;
}
