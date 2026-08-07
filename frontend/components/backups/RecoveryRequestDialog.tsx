"use client";

import { useEffect, useRef, useState } from "react";
import { LifeBuoy } from "lucide-react";
import { Button } from "@/components/ui/Button";
import type { RecoveryIncidentType, RecoveryRequestInput } from "@/types/backup";

const incidents: Array<[RecoveryIncidentType, string]> = [["website_unavailable", "Website is unavailable"], ["damaged_database", "Database appears damaged"], ["accidentally_deleted_records", "Records were deleted by mistake"], ["missing_upload", "An uploaded file is missing"], ["bad_deployment", "A deployment caused a problem"]];

export function RecoveryRequestDialog({ open, backupId, loading, onClose, onSubmit }: { open: boolean; backupId: string | null; loading: boolean; onClose: () => void; onSubmit: (input: RecoveryRequestInput) => void }) {
  const [incident, setIncident] = useState<RecoveryIncidentType>("damaged_database");
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const selectRef = useRef<HTMLSelectElement>(null);
  const returnFocusRef = useRef<HTMLElement | null>(null);
  useEffect(() => {
    if (!open) return;
    returnFocusRef.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    selectRef.current?.focus();
    return () => returnFocusRef.current?.focus();
  }, [open]);
  if (!open) return null;

  const phrase = `RESTORE ${backupId ?? ""}`;
  const submit = () => { const clean = note.trim(); if (clean.length < 10) { setError("Describe what failed using at least 10 characters."); return; } if (!password || confirmation !== phrase) { setError("Enter your current password and the exact confirmation phrase."); return; } setError(null); onSubmit({ incident_type: incident, note: clean, current_password: password, confirmation }); };
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div role="dialog" aria-modal="true" aria-labelledby="recovery-title" onKeyDown={(event) => { if (event.key === "Escape" && !loading) onClose(); }} className="w-full max-w-lg rounded-2xl border border-warm-200 bg-white p-6 shadow-xl">
        <div className="flex items-start gap-3"><LifeBuoy className="mt-0.5 h-6 w-6 text-brand-green-600" /><div><h2 id="recovery-title" className="text-lg font-extrabold text-warm-900">Restore whole system</h2><p className="mt-1 text-sm text-warm-600">Newer production records will be discarded and retained only in the 48-hour safety snapshot. Preparation runs before maintenance mode.</p></div></div>
        <div className="mt-5 space-y-4"><div><label htmlFor="incident-type" className="text-sm font-bold text-warm-800">What happened?</label><select ref={selectRef} id="incident-type" value={incident} onChange={(event) => setIncident(event.target.value as RecoveryIncidentType)} className="mt-1 min-h-11 w-full rounded-lg border border-warm-300 bg-white px-3 text-base text-warm-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30">{incidents.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><div><label htmlFor="recovery-note" className="text-sm font-bold text-warm-800">Reason</label><textarea id="recovery-note" value={note} maxLength={500} rows={3} onChange={(event) => setNote(event.target.value)} className="mt-1 w-full rounded-lg border border-warm-300 px-3 py-2 text-base text-warm-900" /></div><div><label htmlFor="recovery-password" className="text-sm font-bold text-warm-800">Current password</label><input id="recovery-password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-warm-300 px-3" /></div><div><label htmlFor="recovery-confirmation" className="text-sm font-bold text-warm-800">Type {phrase}</label><input id="recovery-confirmation" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-warm-300 px-3" /><p className="mt-1 text-xs text-warm-500">Do not enter provider credentials or patient information.</p>{error && <p id="recovery-error" role="alert" className="mt-1 text-sm font-semibold text-red-700">{error}</p>}</div></div>
        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><Button variant="secondary" className="min-h-11" disabled={loading} onClick={onClose}>Cancel</Button><Button className="min-h-11" loading={loading} onClick={submit}>Prepare restoration</Button></div>
      </div>
    </div>
  );
}
