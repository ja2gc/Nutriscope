"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  createAppointment,
  fetchActiveAppointment,
  fetchPatientAppointments,
  transitionAppointment,
  type NcpAppointment,
} from "@/services/ncpAppointmentService";
import { personDisplayName } from "@/lib/personName";

const reasonOptions = [
  ["patient_left", "Patient left"],
  ["lost_to_follow_up", "Lost to follow-up"],
  ["patient_requested", "Patient requested"],
  ["patient_hospitalized_or_discharged", "Hospitalized or discharged"],
  ["other", "Other"],
] as const;

export function NcpVisitBar({ patientId, ncpId }: { patientId: string; ncpId: string }) {
  const [active, setActive] = useState<NcpAppointment | null>(null);
  const [scheduled, setScheduled] = useState<NcpAppointment | null>(null);
  const [purpose, setPurpose] = useState("");
  const [scheduledAt, setScheduledAt] = useState("");
  const [reason, setReason] = useState("patient_left");
  const [showWalkIn, setShowWalkIn] = useState(false);
  const [showSchedule, setShowSchedule] = useState(false);
  const [showEarlyEnd, setShowEarlyEnd] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      const [activeVisit, appointments] = await Promise.all([
        fetchActiveAppointment(),
        fetchPatientAppointments(patientId, 1),
      ]);
      setActive(activeVisit);
      setScheduled(appointments.data.find((item) => item.status === "scheduled") ?? null);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load visit status.");
    }
  }, [patientId]);

  useEffect(() => { void load(); }, [load]);

  async function transition(id: string, payload: Record<string, string>) {
    setBusy(true);
    try {
      await transitionAppointment(id, payload);
      setShowEarlyEnd(false);
      await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to update visit.");
    } finally { setBusy(false); }
  }

  async function create(source: "walk_in" | "scheduled") {
    setBusy(true);
    try {
      await createAppointment(patientId, {
        source,
        purpose,
        ncp_record_id: ncpId,
        ...(source === "scheduled" ? { scheduled_at: scheduledAt } : {}),
      });
      setPurpose(""); setScheduledAt(""); setShowWalkIn(false); setShowSchedule(false);
      await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to create visit.");
    } finally { setBusy(false); }
  }

  if (active && active.patient_id !== patientId) {
    const href = active.ncp_record_id
      ? `/ncp/${active.patient_id}/assessment/${active.ncp_record_id}`
      : `/ncp/patients/${active.patient_id}`;
    return <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Another visit is active for {personDisplayName(active.patient, "a patient")}. <Link className="font-bold underline" href={href}>Resume visit</Link></div>;
  }

  return <div className="mt-3 border-t border-warm-100 pt-3">
    {error && <p className="mb-2 text-sm font-semibold text-red-600">{error}</p>}
    {active ? (
      <div className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div><p className="text-xs font-extrabold uppercase tracking-wider text-emerald-700">Visit in progress · {active.source === "walk_in" ? "Walk-in" : "Scheduled"}</p><p className="text-sm text-warm-700">{active.purpose}</p>{active.worked_on.length > 0 && <p className="text-xs text-warm-500">Worked on: {active.worked_on.join(", ")}</p>}</div>
          <div className="flex flex-wrap gap-2">
            <button disabled={busy} onClick={() => void transition(active.id, { action: "finish" })} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Finish Visit</button>
            <button disabled={busy} onClick={() => { setShowSchedule((value) => !value); setShowWalkIn(false); }} className="rounded-lg border border-warm-200 px-3 py-2 text-xs font-bold text-warm-700">Schedule Next</button>
            <button disabled={busy} onClick={() => setShowEarlyEnd((value) => !value)} className="rounded-lg border border-amber-200 px-3 py-2 text-xs font-bold text-amber-700">End Early</button>
            {active.worked_on.length === 0 && <button disabled={busy} onClick={() => void transition(active.id, { action: "discard" })} className="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600">Discard Mistaken Start</button>}
          </div>
        </div>
        {showEarlyEnd && <div className="flex flex-wrap items-center gap-2"><label className="text-xs font-bold" htmlFor="early-end-reason">Reason</label><select id="early-end-reason" value={reason} onChange={(event) => setReason(event.target.value)} className="rounded-lg border border-warm-200 px-3 py-2 text-sm">{reasonOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><button disabled={busy} onClick={() => void transition(active.id, { action: "end_early", reason_code: reason })} className="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white">Confirm End Early</button></div>}
      </div>
    ) : (
      <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-xs font-bold uppercase tracking-wider text-warm-500">No visit in progress</p><div className="flex flex-wrap gap-2">{scheduled && <button disabled={busy} onClick={() => void transition(scheduled.id, { action: "start", ncp_record_id: ncpId })} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Start Scheduled Visit</button>}<button onClick={() => { setShowWalkIn((value) => !value); setShowSchedule(false); }} className="rounded-lg border border-emerald-200 px-3 py-2 text-xs font-bold text-emerald-700">Start Walk-in</button><button onClick={() => { setShowSchedule((value) => !value); setShowWalkIn(false); }} className="rounded-lg border border-warm-200 px-3 py-2 text-xs font-bold text-warm-700">Schedule Next</button></div></div>
    )}
    {(showWalkIn || showSchedule) && <div className="mt-3 grid gap-2 rounded-xl bg-warm-50 p-3 sm:grid-cols-2"><label className="text-xs font-bold text-warm-600">Purpose<input value={purpose} onChange={(event) => setPurpose(event.target.value)} maxLength={255} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2 text-sm font-normal" /></label>{showSchedule && <label className="text-xs font-bold text-warm-600">Date and time<input type="datetime-local" value={scheduledAt} onChange={(event) => setScheduledAt(event.target.value)} className="mt-1 w-full rounded-lg border border-warm-200 px-3 py-2 text-sm font-normal" /></label>}<button disabled={busy || !purpose.trim() || (showSchedule && !scheduledAt)} onClick={() => void create(showWalkIn ? "walk_in" : "scheduled")} className="w-fit rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">{showWalkIn ? "Confirm and Start" : "Schedule"}</button></div>}
  </div>;
}
