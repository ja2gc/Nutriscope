import { compareHistorySnapshot } from "@/components/audit/history/compareHistorySnapshot";
import { StructuredHistorySnapshot } from "@/components/audit/history/StructuredHistorySnapshot";
import type { AuditHistorySnapshotDto } from "@/types/auditHistory";

export function BudgetHistory({ snapshot, comparison, side }: {
  snapshot: AuditHistorySnapshotDto;
  comparison: AuditHistorySnapshotDto | null;
  side: "before" | "after";
}) {
  return <StructuredHistorySnapshot snapshot={compareHistorySnapshot(snapshot, comparison, side)} />;
}
