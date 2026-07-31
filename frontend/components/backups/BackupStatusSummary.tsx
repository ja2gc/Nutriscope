import { AlertTriangle, CalendarClock, CheckCircle2, Database, HardDrive } from "lucide-react";
import { Badge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import type { BackupSummaryDto } from "@/types/backup";

const date = (value: string | null) => value ? new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Manila" }).format(new Date(value)) : "Not yet available";
const size = (bytes: number) => bytes < 1024 ? `${bytes} B` : bytes < 1024 ** 2 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 ** 2).toFixed(1)} MB`;

export function BackupStatusSummary({ summary }: { summary: BackupSummaryDto }) {
  const healthy = summary.status === "healthy";
  const label = healthy ? "Healthy" : summary.status === "failed" ? "Failed" : "Attention needed";

  return (
    <Card padded>
      <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div className="flex items-start gap-3">
          {healthy ? <CheckCircle2 className="mt-0.5 h-6 w-6 text-brand-green-600" /> : <AlertTriangle className="mt-0.5 h-6 w-6 text-brand-orange-600" />}
          <div><p className="text-sm font-bold text-warm-900">Backup status</p><Badge tone={healthy ? "emerald" : summary.status === "failed" ? "red" : "amber"} className="mt-2">{label}</Badge></div>
        </div>
        <dl className="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div className="rounded-xl bg-warm-50 p-3"><dt className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-warm-500"><Database className="h-4 w-4" />Last backup</dt><dd className="mt-1 text-sm font-semibold text-warm-900">{date(summary.last_successful_at)}</dd></div>
          <div className="rounded-xl bg-warm-50 p-3"><dt className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-warm-500"><CalendarClock className="h-4 w-4" />Next automatic</dt><dd className="mt-1 text-sm font-semibold text-warm-900">{date(summary.next_automatic_at)}</dd></div>
          <div className="rounded-xl bg-warm-50 p-3"><dt className="text-xs font-bold uppercase tracking-wide text-warm-500">Protected scope</dt><dd className="mt-1 text-sm font-semibold text-warm-900">{summary.scope}</dd></div>
          <div className="rounded-xl bg-warm-50 p-3"><dt className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-warm-500"><HardDrive className="h-4 w-4" />Backup storage</dt><dd className="mt-1 text-sm font-semibold text-warm-900">{size(summary.storage_bytes)}</dd></div>
        </dl>
      </div>
      <p className="mt-4 text-xs leading-relaxed text-warm-500">Recovery test: {date(summary.last_recovery_test_at)}. Production recovery stays operator-controlled.</p>
    </Card>
  );
}
