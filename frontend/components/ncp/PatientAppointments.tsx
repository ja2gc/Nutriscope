"use client";

import { useCallback, useEffect, useState } from "react";
import { CalendarDays, Plus } from "lucide-react";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { createAppointment, fetchPatientAppointments, transitionAppointment, type NcpAppointment } from "@/services/ncpAppointmentService";

const labels: Record<string, string> = {
  scheduled: "Scheduled", in_progress: "In progress", completed: "Completed",
  ended_early: "Ended early", no_show: "No-show", cancelled: "Cancelled", rescheduled: "Rescheduled",
};

export function PatientAppointments({ patientId, currentNcpId }: { patientId: string; currentNcpId?: string }) {
  const [items, setItems] = useState<NcpAppointment[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [showForm, setShowForm] = useState(false);
  const [source, setSource] = useState<"scheduled" | "walk_in">("scheduled");
  const [purpose, setPurpose] = useState("");
  const [scheduledAt, setScheduledAt] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [actionItem, setActionItem] = useState<{ id: string; action: "cancel" | "reschedule" } | null>(null);
  const [actionReason, setActionReason] = useState("patient_requested");
  const [rescheduledAt, setRescheduledAt] = useState("");

  const load = useCallback(async () => {
    try {
      setError(null);
      const result = await fetchPatientAppointments(patientId, page);
      setItems(result.data);
      setMeta(result.meta);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load appointments.");
    }
  }, [page, patientId]);

  useEffect(() => { void load(); }, [load]);

  async function create() {
    setBusy("create");
    try {
      await createAppointment(patientId, {
        source,
        purpose,
        ...(source === "scheduled" ? { scheduled_at: scheduledAt } : {}),
        ...(currentNcpId ? { ncp_record_id: currentNcpId } : {}),
      });
      setPurpose(""); setScheduledAt(""); setShowForm(false); await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to create appointment.");
    } finally { setBusy(null); }
  }

  async function act(item: NcpAppointment, action: "start" | "no_show" | "cancel" | "reschedule") {
    setBusy(item.id);
    try {
      await transitionAppointment(item.id, action === "cancel"
        ? { action, reason_code: actionReason }
        : action === "reschedule"
          ? { action, scheduled_at: rescheduledAt, purpose: item.purpose }
          : { action, ...(action === "start" && currentNcpId ? { ncp_record_id: currentNcpId } : {}) });
      setActionItem(null); setRescheduledAt("");
      await load();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to update appointment.");
    } finally { setBusy(null); }
  }

  const upcoming = items.filter((item) => ["scheduled", "in_progress"].includes(item.status));
  const past = items.filter((item) => !["scheduled", "in_progress"].includes(item.status));

  const list = (rows: NcpAppointment[]) => rows.length === 0
    ? <p className="rounded-xl border border-warm-200 bg-warm-50 p-4 text-sm text-warm-500">No appointments in this section.</p>
    : <div className="space-y-2">{rows.map((item) => (
      <div key={item.id} className="rounded-xl border border-warm-200 bg-white p-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm font-bold text-warm-800">{item.scheduled_at ? new Date(item.scheduled_at).toLocaleString() : "Walk-in visit"}</p>
            <p className="mt-1 text-sm text-warm-600">Purpose: {item.purpose}</p>
            <p className="mt-1 text-xs font-bold uppercase tracking-wider text-warm-400">{item.source === "walk_in" ? "Walk-in" : "Scheduled"} · {labels[item.status]}</p>
            {item.worked_on.length > 0 && <p className="mt-1 text-xs text-warm-500">Work recorded: {item.worked_on.join(", ")}</p>}
          </div>
          {item.status === "scheduled" && <div className="flex flex-wrap gap-2">
            <button disabled={busy === item.id} onClick={() => void act(item, "start")} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Start Visit</button>
            <button disabled={busy === item.id} onClick={() => setActionItem({ id: item.id, action: "reschedule" })} className="rounded-lg border border-warm-200 px-3 py-2 text-xs font-bold text-warm-600">Reschedule</button>
            <button disabled={busy === item.id} onClick={() => void act(item, "no_show")} className="rounded-lg border border-warm-200 px-3 py-2 text-xs font-bold text-warm-600">No-show</button>
            <button disabled={busy === item.id} onClick={() => setActionItem({ id: item.id, action: "cancel" })} className="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600">Cancel</button>
          </div>}
        </div>
        {actionItem?.id === item.id && actionItem.action === "cancel" && <div className="mt-3 flex flex-wrap items-center gap-2"><label className="text-xs font-bold" htmlFor={`cancel-${item.id}`}>Cancellation reason</label><select id={`cancel-${item.id}`} value={actionReason} onChange={(event) => setActionReason(event.target.value)} className="rounded-lg border border-warm-200 px-3 py-2 text-sm"><option value="patient_requested">Patient requested</option><option value="provider_unavailable">Provider unavailable</option><option value="patient_hospitalized_or_discharged">Hospitalized or discharged</option><option value="scheduling_conflict">Scheduling conflict</option><option value="lost_to_follow_up">Lost to follow-up</option><option value="other">Other</option></select><button onClick={() => void act(item, "cancel")} className="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white">Confirm Cancel</button></div>}
        {actionItem?.id === item.id && actionItem.action === "reschedule" && <div className="mt-3 flex flex-wrap items-center gap-2"><label className="text-xs font-bold" htmlFor={`reschedule-${item.id}`}>New date and time</label><input id={`reschedule-${item.id}`} type="datetime-local" value={rescheduledAt} onChange={(event) => setRescheduledAt(event.target.value)} className="rounded-lg border border-warm-200 px-3 py-2 text-sm" /><button disabled={!rescheduledAt} onClick={() => void act(item, "reschedule")} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">Confirm Reschedule</button></div>}
      </div>
    ))}</div>;

  return <div className="space-y-5">
    <div className="flex items-center justify-between gap-3">
      <div><h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Appointments</h3><p className="text-xs text-warm-400">Schedules and attendance across all ADIME cycles.</p></div>
      <button onClick={() => setShowForm((value) => !value)} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white"><Plus className="h-4 w-4" /> Schedule or Walk-in</button>
    </div>
    {showForm && <div className="space-y-3 rounded-2xl border border-warm-200 bg-white p-5">
      <div className="flex gap-2"><button onClick={() => setSource("scheduled")} className={`rounded-lg px-3 py-2 text-sm font-bold ${source === "scheduled" ? "bg-emerald-600 text-white" : "bg-warm-100"}`}>Scheduled</button><button onClick={() => setSource("walk_in")} className={`rounded-lg px-3 py-2 text-sm font-bold ${source === "walk_in" ? "bg-emerald-600 text-white" : "bg-warm-100"}`}>Walk-in</button></div>
      {source === "scheduled" && <input type="datetime-local" value={scheduledAt} onChange={(event) => setScheduledAt(event.target.value)} className="w-full rounded-xl border border-warm-200 px-3 py-2" aria-label="Appointment date and time" />}
      <label className="block text-xs font-bold text-warm-600">Purpose<input value={purpose} onChange={(event) => setPurpose(event.target.value)} maxLength={255} className="mt-1 w-full rounded-xl border border-warm-200 px-3 py-2 text-sm font-normal" /></label>
      <button disabled={!purpose.trim() || (source === "scheduled" && !scheduledAt) || busy === "create"} onClick={() => void create()} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">{source === "walk_in" ? "Start Visit" : "Schedule"}</button>
    </div>}
    {error && <p className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    <section className="space-y-2"><h4 className="flex items-center gap-2 text-sm font-bold text-warm-800"><CalendarDays className="h-4 w-4" /> Upcoming Appointments</h4>{list(upcoming)}</section>
    <section className="space-y-2"><h4 className="text-sm font-bold text-warm-800">Past Appointments</h4>{list(past)}</section>
    <Pagination meta={meta} page={page} onPageChange={setPage} />
  </div>;
}
