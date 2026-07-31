"use client";

import { useEffect, useRef, useState } from "react";
import { LifeBuoy } from "lucide-react";
import { Button } from "@/components/ui/Button";
import type { RecoveryIncidentType, RecoveryRequestInput } from "@/types/backup";

const incidents: Array<[RecoveryIncidentType, string]> = [["website_unavailable", "Website is unavailable"], ["damaged_database", "Database appears damaged"], ["accidentally_deleted_records", "Records were deleted by mistake"], ["missing_upload", "An uploaded file is missing"], ["bad_deployment", "A deployment caused a problem"]];

export function RecoveryRequestDialog({ open, loading, onClose, onSubmit }: { open: boolean; loading: boolean; onClose: () => void; onSubmit: (input: RecoveryRequestInput) => void }) {
  const [incident, setIncident] = useState<RecoveryIncidentType>("damaged_database");
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);
  const selectRef = useRef<HTMLSelectElement>(null);
  useEffect(() => { if (open) selectRef.current?.focus(); }, [open]);
  if (!open) return null;

  const submit = () => { const clean = note.trim(); if (clean.length < 10) { setError("Describe what failed using at least 10 characters."); return; } setError(null); onSubmit({ incident_type: incident, note: clean }); };
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div role="dialog" aria-modal="true" aria-labelledby="recovery-title" onKeyDown={(event) => { if (event.key === "Escape" && !loading) onClose(); }} className="w-full max-w-lg rounded-2xl border border-warm-200 bg-white p-6 shadow-xl">
        <div className="flex items-start gap-3"><LifeBuoy className="mt-0.5 h-6 w-6 text-brand-green-600" /><div><h2 id="recovery-title" className="text-lg font-extrabold text-warm-900">Request recovery</h2><p className="mt-1 text-sm text-warm-600">This protects the backup and alerts the technical maintainer. It does not replace the live database.</p></div></div>
        <div className="mt-5 space-y-4"><div><label htmlFor="incident-type" className="text-sm font-bold text-warm-800">What happened?</label><select ref={selectRef} id="incident-type" value={incident} onChange={(event) => setIncident(event.target.value as RecoveryIncidentType)} className="mt-1 min-h-11 w-full rounded-lg border border-warm-300 bg-white px-3 text-base text-warm-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30">{incidents.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><div><label htmlFor="recovery-note" className="text-sm font-bold text-warm-800">Short description</label><textarea id="recovery-note" value={note} maxLength={500} rows={4} onChange={(event) => setNote(event.target.value)} className="mt-1 w-full rounded-lg border border-warm-300 px-3 py-2 text-base text-warm-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30" aria-describedby="recovery-help recovery-error" /><p id="recovery-help" className="mt-1 text-xs text-warm-500">Do not enter passwords, patient information, or provider credentials.</p>{error && <p id="recovery-error" role="alert" className="mt-1 text-sm font-semibold text-red-700">{error}</p>}</div></div>
        <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><Button variant="secondary" className="min-h-11" disabled={loading} onClick={onClose}>Cancel</Button><Button className="min-h-11" loading={loading} onClick={submit}>Send recovery request</Button></div>
      </div>
    </div>
  );
}
