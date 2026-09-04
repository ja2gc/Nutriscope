"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
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
import { cancelRecovery, createBackup, deleteBackup, getBackupSchedules, keepBackup, listBackups, requestRecovery, updateBackupSchedules } from "@/services/backupService";
import type { BackupCategory, BackupListResponse, BackupRunDto, BackupScheduleInput, BackupSchedulesDto, BackupSection, RecoveryRequestInput } from "@/types/backup";

const activeStates = new Set(["queued", "running", "verifying"]);

export default function BackupsPage() {
  const [data, setData] = useState<BackupListResponse | null>(null);
  const [schedules, setSchedules] = useState<BackupSchedulesDto | null>(null);
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
  const [section, setSection] = useState<BackupSection>("available");
  const [category, setCategory] = useState<BackupCategory>("daily");

  const load = useCallback(async (quiet = false, requestedPage = page) => { if (!quiet) setLoading(true); try { const [initialBackups, automatic] = await Promise.all([listBackups(requestedPage, section, category), getBackupSchedules()]); let backups = initialBackups; if (backups.data.length === 0 && requestedPage > 1) { requestedPage -= 1; backups = await listBackups(requestedPage, section, category); setPage(requestedPage); } setData(backups); setSchedules(automatic); setError(null); } catch { setError("Backups could not be loaded. Check the connection and try again."); } finally { if (!quiet) setLoading(false); } }, [category, page, section]);
  useEffect(() => { void load(); }, [load]);
  useEffect(() => {
    if (category !== "safety" || !data || data.summary.category_counts.safety > 0) return;
    const fallback = (["daily", "weekly", "monthly", "manual"] as BackupCategory[])
      .find((key) => data.summary.category_counts[key] > 0) ?? "daily";
    setCategory(fallback);
    setPage(1);
  }, [category, data]);
  const hasActive = useMemo(() => (data?.summary.counts.in_progress ?? 0) > 0 || (data?.data.some((backup) => activeStates.has(backup.state)) ?? false), [data]);
  useEffect(() => { if (!hasActive) return; const timer = setInterval(() => void load(true), 5000); return () => clearInterval(timer); }, [hasActive, load]);

  const refreshAfter = async (success: string) => { setMessage(success); await load(true); };
  const create = async () => { setCreating(true); setError(null); try { await createBackup(); setSection("in_progress"); setCategory("manual"); setPage(1); setMessage("Backup queued. Status will update automatically."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Backup could not be queued."); } finally { setCreating(false); } };
  const remove = async () => { if (!deleting) return; const failed = deleting.state === "failed"; const permanent = deleting.state === "recently_deleted"; setBusyId(deleting.id); try { await deleteBackup(deleting.id); setDeleting(null); await refreshAfter(permanent ? "Backup permanently deleted." : failed ? "Failed backup record deleted." : "Backup moved to Recently Deleted. You have 48 hours to keep it."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Backup could not be deleted."); } finally { setBusyId(null); } };
  const keep = async (backup: BackupRunDto) => { setBusyId(backup.id); try { await keepBackup(backup.id); await refreshAfter("Backup restored to Available."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Backup could not be kept."); } finally { setBusyId(null); } };
  const recover = async (input: RecoveryRequestInput) => { if (!recovering) return; setBusyId(recovering.id); try { await requestRecovery(recovering.id, input); setRecovering(null); await refreshAfter("Recovery request sent. The selected backup is now protected."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Recovery request could not be sent."); } finally { setBusyId(null); } };
  const cancel = async (backup: BackupRunDto) => { if (!backup.recovery) return; setBusyId(backup.id); try { await cancelRecovery(backup.recovery.id); await refreshAfter("Recovery cancelled. The safety snapshot remains protected for 48 hours."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Recovery could not be cancelled."); } finally { setBusyId(null); } };
  const saveSchedules = async (input: BackupScheduleInput) => { const finalOff = schedules && (schedules.daily.enabled || schedules.weekly.enabled || schedules.monthly.enabled) && !input.daily && !input.weekly && !input.monthly; if (finalOff && !input.confirm_disable_all) { setPendingDisableAll(input); return; } setSavingSchedules(true); try { setSchedules(await updateBackupSchedules(input)); setMessage("Automatic backup schedules updated."); setError(null); setPendingDisableAll(null); } catch (cause) { setError(cause instanceof Error ? cause.message : "Automatic backup settings could not be updated."); } finally { setSavingSchedules(false); } };

  return <div className="space-y-6 font-sans"><PageHeader crumbs={[["Admin", "/admin/dashboard"], ["Backup & Recovery"]]} title="Backup & Recovery" icon={<DatabaseBackup className="h-5 w-5 text-brand-green-600" />} actions={<Button className="min-h-11" loading={creating} disabled={hasActive || busyId !== null} onClick={() => void create()}><DatabaseBackup className="h-4 w-4" />Create backup now</Button>} />
    <div role="status" aria-live="polite" className="min-h-5">{message && <p className="text-sm font-semibold text-brand-green-700">{message}</p>}{hasActive && <p className="flex items-center gap-2 text-sm font-semibold text-sky-700"><RefreshCw className="h-4 w-4 animate-spin" />Backup work is active. This page updates automatically.</p>}</div>
    {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">{error} <button className="ml-2 min-h-11 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500" onClick={() => void load()}>Try again</button></div>}
    {loading && (!data || !schedules) ? <Card padded role="status" className="py-12 text-center"><RefreshCw className="mx-auto h-6 w-6 animate-spin text-brand-green-600" /><p className="mt-3 text-sm font-semibold text-warm-600">Loading backup status</p></Card> : data && schedules && <><BackupStatusSummary summary={data.summary} schedules={schedules} /><BackupScheduleSettings schedules={schedules} disabled={savingSchedules || hasActive} onChange={(input) => void saveSchedules(input)} /><Tabs ariaLabel="Backup status" items={(["available", "in_progress", "failed", "recently_deleted"] as BackupSection[]).map((key) => ({ key, label: `${key === "in_progress" ? "In progress" : key === "recently_deleted" ? "Recently Deleted" : key[0].toUpperCase() + key.slice(1)} (${data.summary.counts[key]})` }))} value={section} onChange={(value) => { setSection(value); setPage(1); }} className="overflow-x-auto" /><Tabs ariaLabel="Backup category" items={(["daily", "weekly", "monthly", "manual", ...(data.summary.category_counts.safety > 0 ? ["safety" as const] : [])] as BackupCategory[]).map((key) => ({ key, label: `${key[0].toUpperCase() + key.slice(1)} (${data.summary.category_counts[key]})` }))} value={category} onChange={(value) => { setCategory(value); setPage(1); }} className="overflow-x-auto" /><BackupList backups={data.data} section={section} busyId={busyId} onDelete={setDeleting} onKeep={(backup) => void keep(backup)} onRecovery={setRecovering} onCancelRecovery={(backup) => void cancel(backup)} /><Pagination meta={data.meta} page={page} onPageChange={setPage} /></>}
    <BackupActionDialog open={deleting !== null} title={deleting?.state === "failed" ? "Delete failed backup record?" : deleting?.state === "recently_deleted" ? "Permanently delete backup?" : "Delete backup?"} description={deleting?.state === "failed" ? "This removes the failed entry from this list. No usable backup archive exists. The audit event remains." : deleting?.state === "recently_deleted" ? "This permanently deletes the archive and its unreferenced protected-file copies. This cannot be undone." : "This backup will move to Recently Deleted for 48 hours. The latest verified backup cannot be deleted."} confirmLabel={deleting?.state === "failed" ? "Delete failed record" : deleting?.state === "recently_deleted" ? "Delete permanently" : "Delete backup"} loading={busyId !== null} onClose={() => setDeleting(null)} onConfirm={() => void remove()} />
    <RecoveryRequestDialog open={recovering !== null} backupId={recovering?.id ?? null} loading={busyId !== null} onClose={() => setRecovering(null)} onSubmit={(input) => void recover(input)} />
    <BackupActionDialog open={pendingDisableAll !== null} title="Disable all automatic backups?" description="No future automatic backups will be created. Existing restore points keep their assigned retention." confirmLabel="Disable automatic backups" loading={savingSchedules} onClose={() => setPendingDisableAll(null)} onConfirm={() => pendingDisableAll && void saveSchedules({ ...pendingDisableAll, confirm_disable_all: true })} />
  </div>;
}
