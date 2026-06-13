"use client";

import React, { useCallback, useEffect, useState } from "react";
import {
  History, ChevronDown, Plus, Pencil, Trash2, Loader2, AlertTriangle, RefreshCw,
} from "lucide-react";
import { Card } from "@/components/ui/Card";
import { Badge, BadgeTone } from "@/components/ui/Badge";
import { ActivityEvent, getActivity } from "@/services/activityService";

/** Map an audit event to an icon + tone (color never the only signal — label too). */
function eventVisual(event: string): { icon: React.ReactNode; tone: BadgeTone; label: string } {
  switch (event) {
    case "created": return { icon: <Plus className="h-3 w-3" aria-hidden />, tone: "emerald", label: "Created" };
    case "deleted": return { icon: <Trash2 className="h-3 w-3" aria-hidden />, tone: "red", label: "Deleted" };
    default:        return { icon: <Pencil className="h-3 w-3" aria-hidden />, tone: "sky", label: "Updated" };
  }
}

const REDACTED = "••• redacted";
const fmt = (v: unknown) =>
  v === null || v === undefined || v === "" ? "—" : typeof v === "object" ? JSON.stringify(v) : String(v);

function ChangeRow({ field, oldVal, newVal }: { field: string; oldVal: unknown; newVal: unknown }) {
  const redacted = newVal === REDACTED || oldVal === REDACTED;
  return (
    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-[11px]">
      <span className="font-semibold text-zinc-600">{field}</span>
      {redacted ? (
        <Badge tone="zinc">value hidden (PHI)</Badge>
      ) : (
        <span className="text-zinc-500 tabular-nums">
          <span className="line-through text-zinc-400">{fmt(oldVal)}</span>
          <span className="mx-1 text-zinc-300" aria-label="changed to">→</span>
          <span className="font-medium text-zinc-700">{fmt(newVal)}</span>
        </span>
      )}
    </div>
  );
}

/**
 * Per-record audit history (Spec 5). A collapsible timeline of who changed what,
 * when. Lazy-loads on first expand. Reuses Card / Badge surfaces for visual
 * consistency. Clinical subjects arrive PHI-redacted from the backend.
 */
export function HistoryPanel({ path, title = "Change history" }: { path: string; title?: string }) {
  const [open, setOpen] = useState(false);
  const [events, setEvents] = useState<ActivityEvent[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setError(null);
    try { setEvents(await getActivity(path)); }
    catch (e) { setError(e instanceof Error ? e.message : "Failed to load history."); }
    finally { setLoading(false); }
  }, [path]);

  useEffect(() => { if (open && events === null && !loading && !error) load(); }, [open, events, loading, error, load]);

  return (
    <Card className="overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        className="w-full flex items-center justify-between gap-2 px-5 py-3.5 text-left cursor-pointer hover:bg-zinc-50/60 transition-colors"
      >
        <span className="flex items-center gap-2 text-xs font-extrabold text-zinc-700 uppercase tracking-wider">
          <History className="h-4 w-4 text-emerald-600" aria-hidden /> {title}
          {events && <Badge tone="zinc">{events.length}</Badge>}
        </span>
        <ChevronDown className={`h-4 w-4 text-zinc-400 transition-transform duration-200 ${open ? "rotate-180" : ""}`} aria-hidden />
      </button>

      {open && (
        <div className="border-t border-zinc-100 px-5 py-4">
          {loading && (
            <div className="flex items-center gap-2 text-xs text-zinc-400 py-6 justify-center">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden /> Loading history…
            </div>
          )}

          {error && !loading && (
            <div className="flex items-center justify-between gap-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2">
              <span className="flex items-center gap-1.5"><AlertTriangle className="h-3.5 w-3.5" aria-hidden /> {error}</span>
              <button onClick={load} className="flex items-center gap-1 text-red-600 hover:text-red-800 cursor-pointer">
                <RefreshCw className="h-3 w-3" aria-hidden /> Retry
              </button>
            </div>
          )}

          {!loading && !error && events && events.length === 0 && (
            <p className="text-xs text-zinc-400 text-center py-6">No changes recorded yet.</p>
          )}

          {!loading && !error && events && events.length > 0 && (
            <ol className="relative ml-1 border-l border-zinc-200 space-y-4">
              {events.map((ev) => {
                const v = eventVisual(ev.event);
                const fields = Array.from(new Set([
                  ...Object.keys(ev.changes?.new ?? {}),
                  ...Object.keys(ev.changes?.old ?? {}),
                ]));
                return (
                  <li key={ev.id} className="ml-4">
                    <span className={`absolute -left-[9px] flex h-4 w-4 items-center justify-center rounded-full border bg-white ${
                      v.tone === "emerald" ? "border-emerald-300 text-emerald-600"
                      : v.tone === "red" ? "border-red-300 text-red-600" : "border-sky-300 text-sky-600"}`}>
                      {v.icon}
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge tone={v.tone}>{v.label}</Badge>
                      <span className="text-xs font-semibold text-zinc-700">{ev.causer}</span>
                      <span className="text-[10px] text-zinc-400 tabular-nums">{new Date(ev.created_at).toLocaleString()}</span>
                    </div>
                    {fields.length > 0 && (
                      <div className="mt-1.5 space-y-1">
                        {fields.map((f) => (
                          <ChangeRow key={f} field={f} oldVal={ev.changes.old?.[f]} newVal={ev.changes.new?.[f]} />
                        ))}
                      </div>
                    )}
                  </li>
                );
              })}
            </ol>
          )}
        </div>
      )}
    </Card>
  );
}
