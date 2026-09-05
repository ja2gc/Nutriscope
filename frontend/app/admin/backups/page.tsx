"use client";

import { useCallback, useEffect, useState } from "react";
import { DatabaseBackup, RefreshCw } from "lucide-react";
import { BackupActionDialog } from "@/components/backups/BackupActionDialog";
import { BackupList } from "@/components/backups/BackupList";
import { BackupScheduleSettings } from "@/components/backups/BackupScheduleSettings";
import { RecoveryRequestDialog } from "@/components/backups/RecoveryRequestDialog";
import { BackupStatusSummary } from "@/components/backups/BackupStatusSummary";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { PageHeader } from "@/components/ui/PageHeader";
import { Pagination } from "@/components/ui/Pagination";
import { Tabs } from "@/components/ui/Tabs";
import {
  cancelRecovery,
  createBackup,
  deleteBackup,
  getBackupSchedules,
  keepBackup,
  listBackups,
  requestRecovery,
  updateBackupSchedules,
} from "@/services/backupService";
import type {
  BackupCategory,
  BackupListResponse,
  BackupRunDto,
  BackupScheduleInput,
  BackupSchedulesDto,
  BackupSection,
  RecoveryRequestInput,
  RecoveryStatus,
} from "@/types/backup";

type BackupView = Exclude<BackupSection, "in_progress">;

const activeRecoveryStates = new Set<RecoveryStatus>([
  "requested",
  "preparing",
  "checking",
  "ready",
  "switching",
]);

const categoryOptions: Array<[BackupCategory, string]> = [
  ["daily", "Daily"],
  ["weekly", "Weekly"],
  ["monthly", "Monthly"],
  ["manual", "Manual"],
  ["safety", "Pre-restore"],
];

const recoveryStageLabels: Record<RecoveryStatus, string> = {
  requested: "Queued",
  preparing: "Preparing safety checks",
  checking: "Validating restore point",
  ready: "Ready to switch",
  switching: "Restoring system",
  completed: "Completed",
  failed: "Failed",
  rolled_back: "Rolled back",
  cancelled: "Cancelled",
};

export default function BackupsPage() {
  const [data, setData] = useState<BackupListResponse | null>(null);
  const [schedules, setSchedules] = useState<BackupSchedulesDto | null>(null);
  const [activeBackups, setActiveBackups] = useState<BackupRunDto[]>([]);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<BackupRunDto | null>(null);
  const [recovering, setRecovering] = useState<BackupRunDto | null>(null);
  const [pendingDisableAll, setPendingDisableAll] = useState<BackupScheduleInput | null>(null);
  const [savingSchedules, setSavingSchedules] = useState(false);
  const [page, setPage] = useState(1);
  const [section, setSection] = useState<BackupView>("available");
  const [category, setCategory] = useState<BackupCategory>("daily");

  const load = useCallback(async (quiet = false, requestedPage = page) => {
    if (!quiet) setLoading(true);
    try {
      const [initialBackups, automatic, activity] = await Promise.all([
        listBackups(requestedPage, section, category),
        getBackupSchedules(),
        listBackups(1, "in_progress", "all"),
      ]);
      let backups = initialBackups;
      if (backups.data.length === 0 && requestedPage > 1) {
        requestedPage -= 1;
        backups = await listBackups(requestedPage, section, category);
        setPage(requestedPage);
      }
      setData(backups);
      setSchedules(automatic);
      setActiveBackups(activity.data);
      setError(null);
    } catch {
      setError("Backups could not be loaded. Check the connection and try again.");
    } finally {
      if (!quiet) setLoading(false);
    }
  }, [category, page, section]);

  useEffect(() => { void load(); }, [load]);

  const activeRecovery = data?.summary.active_recovery ?? null;
  const hasActive = activeBackups.length > 0
    || (activeRecovery !== null && activeRecoveryStates.has(activeRecovery.state));

  useEffect(() => {
    if (!hasActive) return;
    const timer = setInterval(() => void load(true), 5000);
    return () => clearInterval(timer);
  }, [hasActive, load]);

  const refreshAfter = async (success: string) => {
    setMessage(success);
    await load(true);
  };

  const create = async () => {
    setCreating(true);
    setError(null);
    try {
      const queued = await createBackup();
      setActiveBackups([queued]);
      setMessage("Backup queued. Status will update automatically.");
      void load(true);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Backup could not be queued.");
    } finally {
      setCreating(false);
    }
  };

  const remove = async () => {
    if (!deleting) return;
    const failed = deleting.state === "failed";
    const permanent = deleting.state === "recently_deleted";
    setBusyId(deleting.id);
    try {
      await deleteBackup(deleting.id);
      setDeleting(null);
      await refreshAfter(permanent
        ? "Backup permanently deleted."
        : failed
          ? "Failed backup record deleted."
          : "Backup moved to Recently Deleted. You have 48 hours to keep it.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Backup could not be deleted.");
    } finally {
      setBusyId(null);
    }
  };

  const keep = async (backup: BackupRunDto) => {
    setBusyId(backup.id);
    try {
      await keepBackup(backup.id);
      await refreshAfter("Backup restored to Restore points.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Backup could not be kept.");
    } finally {
      setBusyId(null);
    }
  };

  const recover = async (input: RecoveryRequestInput) => {
    if (!recovering) return;
    setBusyId(recovering.id);
    try {
      await requestRecovery(recovering.id, input);
      setRecovering(null);
      await refreshAfter("Restoration request sent. The selected restore point is protected.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Restoration request could not be sent.");
    } finally {
      setBusyId(null);
    }
  };

  const cancel = async (recoveryId: string) => {
    setBusyId(recoveryId);
    try {
      await cancelRecovery(recoveryId);
      await refreshAfter("Restoration cancelled. Its pre-restore backup remains protected for 48 hours.");
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Restoration could not be cancelled.");
    } finally {
      setBusyId(null);
    }
  };

  const saveSchedules = async (input: BackupScheduleInput) => {
    const finalOff = schedules
      && (schedules.daily.enabled || schedules.weekly.enabled || schedules.monthly.enabled)
      && !input.daily
      && !input.weekly
      && !input.monthly;
    if (finalOff && !input.confirm_disable_all) {
      setPendingDisableAll(input);
      return;
    }
    setSavingSchedules(true);
    try {
      setSchedules(await updateBackupSchedules(input));
      setMessage("Automatic backup schedules updated.");
      setError(null);
      setPendingDisableAll(null);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Automatic backup settings could not be updated.");
    } finally {
      setSavingSchedules(false);
    }
  };

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Admin", "/admin/dashboard"], ["Backup & Recovery"]]}
        title="Backup & Recovery"
        icon={<DatabaseBackup className="h-5 w-5 text-brand-green-600" />}
        actions={(
          <Button className="min-h-11" loading={creating} disabled={hasActive || busyId !== null} onClick={() => void create()}>
            <DatabaseBackup className="h-4 w-4" />
            Create backup now
          </Button>
        )}
      />

      <div role="status" aria-live="polite" className="min-h-5">
        {message && <p className="text-sm font-semibold text-brand-green-700">{message}</p>}
      </div>

      {error && (
        <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">
          {error}
          <button className="ml-2 min-h-11 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500" onClick={() => void load()}>
            Try again
          </button>
        </div>
      )}

      {loading && (!data || !schedules) ? (
        <Card padded role="status" className="py-12 text-center">
          <RefreshCw className="mx-auto h-6 w-6 animate-spin text-brand-green-600" />
          <p className="mt-3 text-sm font-semibold text-warm-600">Loading backup status</p>
        </Card>
      ) : data && schedules && (
        <>
          <BackupStatusSummary summary={data.summary} schedules={schedules} />
          <BackupScheduleSettings schedules={schedules} disabled={savingSchedules || hasActive} onChange={(input) => void saveSchedules(input)} />

          {activeBackups.length > 0 && (
            <section aria-labelledby="backup-activity-heading" className="space-y-3">
              <div>
                <h2 id="backup-activity-heading" className="text-base font-extrabold text-warm-900">Backup activity</h2>
                <p className="mt-1 text-sm text-warm-600">Creation status updates automatically. Another backup cannot start until this finishes.</p>
              </div>
              <BackupList backups={activeBackups} section="in_progress" busyId={busyId} onDelete={setDeleting} onKeep={(backup) => void keep(backup)} onRecovery={setRecovering} onCancelRecovery={(backup) => backup.recovery && void cancel(backup.recovery.id)} />
            </section>
          )}

          {activeRecovery && activeRecoveryStates.has(activeRecovery.state) && (
            <Card padded role="status" className="border-sky-200 bg-sky-50">
              <div className="flex items-start gap-3">
                <RefreshCw className="mt-0.5 h-5 w-5 shrink-0 animate-spin text-sky-700" />
                <div>
                  <h2 className="text-base font-extrabold text-sky-900">Restoration activity</h2>
                  <p className="mt-1 text-sm text-sky-800">
                    {recoveryStageLabels[activeRecovery.state]}. The selected restore point remains protected while this page updates.
                  </p>
                  {activeRecovery.can_cancel && (
                    <Button variant="secondary" size="sm" className="mt-3 min-h-11" loading={busyId === activeRecovery.id} onClick={() => void cancel(activeRecovery.id)}>
                      Cancel restoration
                    </Button>
                  )}
                </div>
              </div>
            </Card>
          )}

          <Card className="overflow-hidden">
            <div className="p-5 pb-3">
              <h2 className="text-base font-extrabold text-warm-900">Restore points</h2>
              <p className="mt-1 text-sm text-warm-600">Choose a saved point, review failed work, or recover a recently deleted backup.</p>
            </div>
            <Tabs
              fill
              ariaLabel="Backup views"
              items={([
                ["available", "Restore points"],
                ["failed", "Failed"],
                ["recently_deleted", "Recently deleted"],
              ] as Array<[BackupView, string]>).map(([key, label]) => ({
                key,
                label: `${label} (${data.summary.counts[key]})`,
              }))}
              value={section}
              onChange={(value) => {
                setSection(value);
                setPage(1);
              }}
            />
          </Card>

          <div>
            <p id="backup-type-filter-label" className="text-xs font-extrabold uppercase tracking-wide text-warm-500">Filter by backup type</p>
            <div role="group" aria-labelledby="backup-type-filter-label" className="mt-2 flex flex-wrap gap-2">
              {categoryOptions.map(([key, label]) => (
                <Button
                  key={key}
                  type="button"
                  size="sm"
                  variant={category === key ? "primary" : "secondary"}
                  aria-pressed={category === key}
                  className="min-h-11"
                  onClick={() => {
                    setCategory(key);
                    setPage(1);
                  }}
                >
                  {label} ({data.summary.category_counts[key]})
                </Button>
              ))}
            </div>
          </div>

          <BackupList backups={data.data} section={section} busyId={busyId} onDelete={setDeleting} onKeep={(backup) => void keep(backup)} onRecovery={setRecovering} onCancelRecovery={(backup) => backup.recovery && void cancel(backup.recovery.id)} />
          <Pagination meta={data.meta} page={page} onPageChange={setPage} />
        </>
      )}

      <BackupActionDialog
        open={deleting !== null}
        title={deleting?.state === "failed" ? "Delete failed backup record?" : deleting?.state === "recently_deleted" ? "Permanently delete backup?" : "Delete backup?"}
        description={deleting?.state === "failed"
          ? "This removes the failed entry from this list. No usable backup archive exists. The audit event remains."
          : deleting?.state === "recently_deleted"
            ? "This permanently deletes the archive and its unreferenced protected-file copies. This cannot be undone."
            : "This backup will move to Recently Deleted for 48 hours. The latest verified backup and protected pre-restore backups cannot be deleted."}
        confirmLabel={deleting?.state === "failed" ? "Delete failed record" : deleting?.state === "recently_deleted" ? "Delete permanently" : "Delete backup"}
        loading={busyId !== null}
        onClose={() => setDeleting(null)}
        onConfirm={() => void remove()}
      />
      <RecoveryRequestDialog open={recovering !== null} backupId={recovering?.id ?? null} loading={busyId !== null} onClose={() => setRecovering(null)} onSubmit={(input) => void recover(input)} />
      <BackupActionDialog
        open={pendingDisableAll !== null}
        title="Disable all automatic backups?"
        description="No future automatic backups will be created. Existing restore points keep their assigned retention."
        confirmLabel="Disable automatic backups"
        loading={savingSchedules}
        onClose={() => setPendingDisableAll(null)}
        onConfirm={() => pendingDisableAll && void saveSchedules({ ...pendingDisableAll, confirm_disable_all: true })}
      />
    </div>
  );
}
