import type { AuditValueDto } from "@/types/audit";
import type {
  AuditHistoryFieldDto,
  AuditHistorySnapshotDto,
  AuditHistoryTableDto,
  AuditHistoryTableRowDto,
} from "@/types/auditHistory";

function valueEquals(left: AuditValueDto, right: AuditValueDto) {
  if (left.type !== right.type || left.unit !== right.unit || left.currency !== right.currency) return false;
  const leftValue = left.value;
  const rightValue = right.value;
  if (Array.isArray(leftValue) || Array.isArray(rightValue)) {
    return Array.isArray(leftValue) && Array.isArray(rightValue)
      && leftValue.length === rightValue.length
      && leftValue.every((value, index) => value === rightValue[index]);
  }
  return leftValue === rightValue;
}

function rowEquals(left: AuditHistoryTableRowDto, right: AuditHistoryTableRowDto) {
  const keys = Object.keys(left.values);
  return keys.length === Object.keys(right.values).length
    && keys.every((key) => right.values[key] && valueEquals(left.values[key], right.values[key]));
}

function compareFields(
  selected: AuditHistoryFieldDto[],
  comparison: AuditHistoryFieldDto[],
  side: "before" | "after",
) {
  const compared = new Map(comparison.map((field) => [field.key, field]));
  const result = selected.map((field) => {
    const other = compared.get(field.key);
    const change = !other ? (side === "after" ? "added" : "removed") : valueEquals(field.value, other.value) ? undefined : "changed";
    return { ...field, change } as AuditHistoryFieldDto;
  });
  if (side === "after") {
    const selectedKeys = new Set(selected.map((field) => field.key));
    result.push(...comparison.filter((field) => !selectedKeys.has(field.key)).map((field) => ({ ...field, change: "removed" as const })));
  }
  return result;
}

function compareTable(
  selected: AuditHistoryTableDto,
  comparison: AuditHistoryTableDto | undefined,
  side: "before" | "after",
): AuditHistoryTableDto {
  const compared = new Map((comparison?.rows || []).map((row) => [row.key, row]));
  const rows = selected.rows.map((row) => {
    const other = compared.get(row.key);
    const change = !other ? (side === "after" ? "added" : "removed") : rowEquals(row, other) ? undefined : "changed";
    return { ...row, change } as AuditHistoryTableRowDto;
  });
  if (side === "after" && comparison) {
    const selectedKeys = new Set(selected.rows.map((row) => row.key));
    rows.push(...comparison.rows.filter((row) => !selectedKeys.has(row.key)).map((row) => ({ ...row, change: "removed" as const })));
  }
  return { ...selected, rows };
}

export function compareHistorySnapshot(
  snapshot: AuditHistorySnapshotDto,
  comparison: AuditHistorySnapshotDto | null,
  side: "before" | "after",
): AuditHistorySnapshotDto {
  if (!comparison) return snapshot;
  const comparisonTables = new Map(comparison.tables.map((table) => [table.key, table]));
  const tables = snapshot.tables.map((table) => compareTable(table, comparisonTables.get(table.key), side));
  if (side === "after") {
    const selectedKeys = new Set(snapshot.tables.map((table) => table.key));
    tables.push(...comparison.tables.filter((table) => !selectedKeys.has(table.key)).map((table) => ({
      ...table,
      rows: table.rows.map((row) => ({ ...row, change: "removed" as const })),
    })));
  }
  return {
    ...snapshot,
    fields: compareFields(snapshot.fields, comparison.fields, side),
    tables,
  };
}
