"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { DatabaseBackup, RefreshCw } from "lucide-react";
import { BackupActionDialog } from "@/components/backups/BackupActionDialog";
import { BackupList } from "@/components/backups/BackupList";
import { RecoveryRequestDialog } from "@/components/backups/RecoveryRequestDialog";
import { BackupStatusSummary } from "@/components/backups/BackupStatusSummary";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { PageHeader } from "@/components/ui/PageHeader";
import { createBackup, deleteBackup, keepBackup, listBackups, requestRecovery } from "@/services/backupService";
import type { BackupListResponse, BackupRunDto, RecoveryRequestInput } from "@/types/backup";

const activeStates = new Set(["queued", "running", "verifying"]);

export default function BackupsPage() {
  const [data, setData] = useState<BackupListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<BackupRunDto | null>(null);
  const [recovering, setRecovering] = useState<BackupRunDto | null>(null);

  const load = useCallback(async (quiet = false) => { if (!quiet) setLoading(true); try { setData(await listBackups()); setError(null); } catch { setError("Backups could not be loaded. Check the connection and try again."); } finally { if (!quiet) setLoading(false); } }, []);
  useEffect(() => { void load(); }, [load]);
  const hasActive = useMemo(() => data?.data.some((backup) => activeStates.has(backup.state)) ?? false, [data]);
  useEffect(() => { if (!hasActive) return; const timer = setInterval(() => void load(true), 5000); return () => clearInterval(timer); }, [hasActive, load]);

  const refreshAfter = async (success: string) => { setMessage(success); await load(true); };
  const create = async () => { setCreating(true); setError(null); try { await createBackup(); await refreshAfter("Backup queued. Status will update automatically."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Backup could not be queued."); } finally { setCreating(false); } };
  const remove = async () => { if (!deleting) return; setBusyId(deleting.id); try { await deleteBackup(deleting.id); setDeleting(null); await refreshAfter("Backup moved to Recently Deleted. You have 48 hours to keep it."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Backup could not be deleted."); } finally { setBusyId(null); } };
  const keep = async (backup: BackupRunDto) => { setBusyId(backup.id); try { await keepBackup(backup.id); await refreshAfter("Backup restored to Available."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Backup could not be kept."); } finally { setBusyId(null); } };
  const recover = async (input: RecoveryRequestInput) => { if (!recovering) return; setBusyId(recovering.id); try { await requestRecovery(recovering.id, input); setRecovering(null); await refreshAfter("Recovery request sent. The selected backup is now protected."); } catch (cause) { setError(cause instanceof Error ? cause.message : "Recovery request could not be sent."); } finally { setBusyId(null); } };

  return <div className="space-y-6 font-sans"><PageHeader crumbs={[["Admin", "/admin/dashboard"], ["Backup & Recovery"]]} title="Backup & Recovery" icon={<DatabaseBackup className="h-5 w-5 text-brand-green-600" />} subtitle="Check database protection, create a backup, recover deleted backups for 48 hours, or ask the technical maintainer for recovery." actions={<Button className="min-h-11" loading={creating} disabled={hasActive || busyId !== null} onClick={() => void create()}><DatabaseBackup className="h-4 w-4" />Create backup now</Button>} />
    <div role="status" aria-live="polite" className="min-h-5">{message && <p className="text-sm font-semibold text-brand-green-700">{message}</p>}{hasActive && <p className="flex items-center gap-2 text-sm font-semibold text-sky-700"><RefreshCw className="h-4 w-4 animate-spin" />Backup work is active. This page updates automatically.</p>}</div>
    {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800">{error} <button className="ml-2 min-h-11 underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500" onClick={() => void load()}>Try again</button></div>}
    {loading && !data ? <Card padded role="status" className="py-12 text-center"><RefreshCw className="mx-auto h-6 w-6 animate-spin text-brand-green-600" /><p className="mt-3 text-sm font-semibold text-warm-600">Loading backup status</p></Card> : data && <><BackupStatusSummary summary={data.meta} /><BackupList backups={data.data} busyId={busyId} onDelete={setDeleting} onKeep={(backup) => void keep(backup)} onRecovery={setRecovering} /></>}
    <BackupActionDialog open={deleting !== null} title="Move backup to Recently Deleted?" description="This backup will remain recoverable for 48 hours, then be permanently removed automatically. The latest verified backup cannot be deleted." confirmLabel="Move to Recently Deleted" loading={busyId !== null} onClose={() => setDeleting(null)} onConfirm={() => void remove()} />
    <RecoveryRequestDialog open={recovering !== null} loading={busyId !== null} onClose={() => setRecovering(null)} onSubmit={(input) => void recover(input)} />
  </div>;
}
