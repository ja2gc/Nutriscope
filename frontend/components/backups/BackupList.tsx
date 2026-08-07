import { ArchiveRestore, Clock3, DatabaseBackup, RotateCcw, Trash2 } from "lucide-react";
import { Badge, type BadgeTone } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import type { BackupRunDto, BackupState } from "@/types/backup";

const sections: Array<{ title: string; states: BackupState[] }> = [
  { title: "In progress", states: ["queued", "running", "verifying"] },
  { title: "Available", states: ["completed"] },
  { title: "Failed", states: ["failed"] },
  { title: "Recently Deleted", states: ["recently_deleted"] },
];
const labels: Record<BackupState, string> = { queued: "Queued", running: "Running", verifying: "Verifying", completed: "Completed", failed: "Failed", recently_deleted: "Recently Deleted", purged: "Purged" };
const tones: Record<BackupState, BadgeTone> = { queued: "sky", running: "sky", verifying: "amber", completed: "emerald", failed: "red", recently_deleted: "amber", purged: "zinc" };
const date = (value: string | null) => value ? new Intl.DateTimeFormat("en-PH", { dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Manila" }).format(new Date(value)) : "—";
const size = (bytes: number | null) => bytes === null ? "—" : bytes < 1024 ** 2 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 ** 2).toFixed(1)} MB`;

interface Props {
  backups: BackupRunDto[];
  busyId: string | null;
  onDelete: (backup: BackupRunDto) => void;
  onKeep: (backup: BackupRunDto) => void;
  onRecovery: (backup: BackupRunDto) => void;
  onCancelRecovery: (backup: BackupRunDto) => void;
}

export function BackupList({ backups, busyId, onDelete, onKeep, onRecovery, onCancelRecovery }: Props) {
  if (backups.length === 0) {
    return <EmptyState icon={<DatabaseBackup className="h-7 w-7" />} title="No backups yet" message="Automatic backups will appear here after storage is connected. You can also create one now." />;
  }

  return <div className="space-y-6">{sections.map((section) => {
    const items = backups.filter((backup) => section.states.includes(backup.state));
    if (!items.length) return null;
    const sectionId = `backup-section-${section.title.replaceAll(" ", "-").toLowerCase()}`;
    return <section key={section.title} aria-labelledby={sectionId}>
      <h2 id={sectionId} className="mb-3 text-sm font-extrabold uppercase tracking-wide text-warm-700">{section.title} ({items.length})</h2>
      <div className="space-y-3">{items.map((backup) => <Card padded key={backup.id}>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={tones[backup.state]}>{labels[backup.state]}</Badge>
              <span className="text-xs font-bold uppercase tracking-wide text-warm-500">{backup.source}</span>
              {backup.retention_tier && <Badge tone="zinc">{backup.retention_tier}</Badge>}
              {backup.recovery && <Badge tone="sky">Recovery: {backup.recovery.state.replaceAll("_", " ")}</Badge>}
            </div>
            <p className="mt-2 flex items-center gap-2 text-sm font-semibold text-warm-900"><Clock3 className="h-4 w-4 text-warm-400" />{date(backup.verified_at || backup.queued_at)}</p>
            <p className="mt-1 text-xs text-warm-500">Size: {size(backup.size_bytes)}{backup.retention_expires_at ? ` · Expected retention change: ${date(backup.retention_expires_at)}` : ""}{backup.recoverable_until ? ` · Recover before: ${date(backup.recoverable_until)}` : ""}</p>
            {backup.failure?.message && <p role="alert" className="mt-2 text-sm font-semibold text-red-700">{backup.failure.message}</p>}
            {backup.recovery?.failure_message && <p role="alert" className="mt-2 text-sm font-semibold text-red-700">{backup.recovery.failure_message}</p>}
          </div>
          <div className="flex flex-wrap gap-2">
            {backup.actions.can_keep && <Button variant="secondary" className="min-h-11" loading={busyId === backup.id} onClick={() => onKeep(backup)}><RotateCcw className="h-4 w-4" />Keep backup</Button>}
            {backup.actions.can_request_recovery && <Button variant="secondary" className="min-h-11" disabled={busyId !== null} onClick={() => onRecovery(backup)}><ArchiveRestore className="h-4 w-4" />Restore whole system</Button>}
            {backup.recovery?.can_cancel && <Button variant="secondary" className="min-h-11" disabled={busyId !== null} onClick={() => onCancelRecovery(backup)}>Cancel recovery</Button>}
            {backup.actions.can_delete && <Button variant="ghost" className="min-h-11 text-red-700" disabled={busyId !== null} onClick={() => onDelete(backup)}><Trash2 className="h-4 w-4" />Move to Recently Deleted</Button>}
          </div>
        </div>
      </Card>)}</div>
    </section>;
  })}</div>;
}
