"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AlertTriangle, ChevronDown, History, Loader2, RefreshCw } from "lucide-react";
import { Badge, type BadgeTone } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import { getActivity } from "@/services/activityService";
import type { AuditEventDto } from "@/types/audit";
import { AuditChangeList } from "./AuditChangeList";
import { AuditTimestamp } from "./AuditTimestamp";

function actionTone(event: AuditEventDto): BadgeTone {
  if (event.outcome === "failure" || event.action === "deleted") return "red";
  if (event.outcome === "blocked" || event.severity === "warning") return "amber";
  if (event.action === "created" || event.action === "completed" || event.action === "archived") return "emerald";
  return "sky";
}

export function AuditTrail({
  path,
  title = "Activity trail",
}: {
  path: string;
  title?: string;
}) {
  const [open, setOpen] = useState(false);
  const [events, setEvents] = useState<AuditEventDto[] | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const requestSequence = useRef(0);
  const activeRequest = useRef<AbortController | null>(null);

  const load = useCallback(async (beforeId?: string | null) => {
    activeRequest.current?.abort();
    const controller = new AbortController();
    activeRequest.current = controller;
    const sequence = ++requestSequence.current;
    if (beforeId) {
      setLoadingMore(true);
    } else {
      setLoading(true);
    }
    setError(null);
    try {
      const page = await getActivity(path, beforeId, { signal: controller.signal });
      if (controller.signal.aborted || sequence !== requestSequence.current) return;
      setEvents((current) => {
        if (!beforeId || current === null) return page.data;
        const knownIds = new Set(current.map((event) => event.id));
        return [...current, ...page.data.filter((event) => !knownIds.has(event.id))];
      });
      setCursor(page.meta.next_before_id);
      setHasMore(page.meta.has_more);
    } catch (loadError) {
      if (controller.signal.aborted || sequence !== requestSequence.current) return;
      setError(loadError instanceof Error ? loadError.message : "Failed to load activity trail.");
    } finally {
      if (sequence === requestSequence.current) {
        activeRequest.current = null;
        setLoading(false);
        setLoadingMore(false);
      }
    }
  }, [path]);

  useEffect(() => {
    activeRequest.current?.abort();
    requestSequence.current += 1;
    setEvents(null);
    setCursor(null);
    setHasMore(false);
    setError(null);
    setLoading(false);
    setLoadingMore(false);
    return () => {
      activeRequest.current?.abort();
      requestSequence.current += 1;
    };
  }, [path]);

  useEffect(() => {
    if (open && events === null && !loading && !error) void load();
  }, [error, events, load, loading, open]);

  return (
    <Card className="overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((current) => !current)}
        aria-expanded={open}
        className="flex min-h-11 w-full cursor-pointer items-center justify-between gap-2 px-5 py-3.5 text-left transition-colors hover:bg-warm-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500/30"
      >
        <span className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-warm-700">
          <History className="h-4 w-4 text-emerald-600" aria-hidden />
          {title}
          {events && <Badge tone="zinc">{events.length}</Badge>}
        </span>
        <ChevronDown className={`h-4 w-4 text-warm-400 transition-transform ${open ? "rotate-180" : ""}`} aria-hidden />
      </button>

      {open && (
        <div className="border-t border-warm-100 px-5 py-4" aria-live="polite">
          {loading && (
            <div className="flex items-center justify-center gap-2 py-6 text-sm text-warm-500">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden /> Loading activity trail…
            </div>
          )}

          {error && !loading && (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700" role="alert">
              <span className="flex items-center gap-1.5"><AlertTriangle className="h-4 w-4" aria-hidden />{error}</span>
              <button type="button" onClick={() => void load()} className="flex min-h-11 cursor-pointer items-center gap-1 text-red-700 hover:text-red-900">
                <RefreshCw className="h-3.5 w-3.5" aria-hidden /> Retry
              </button>
            </div>
          )}

          {!loading && !error && events?.length === 0 && (
            <p className="py-6 text-center text-sm text-warm-500">No activity recorded yet.</p>
          )}

          {!loading && !error && events && events.length > 0 && (
            <>
              <ol className="relative ml-2 space-y-5 border-l border-warm-200">
                {events.map((event) => (
                  <li key={event.id} className="relative ml-5">
                    <span className="absolute -left-[26px] top-1 h-3 w-3 rounded-full border-2 border-emerald-500 bg-white" aria-hidden />
                    <article className="rounded-xl border border-warm-100 bg-warm-50/60 p-3.5">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge tone={actionTone(event)}>{event.action_label}</Badge>
                          <span className="text-sm font-semibold text-warm-800">{event.actor?.name || "System"}</span>
                          {event.actor?.role && <span className="text-xs text-warm-500">{event.actor.role}</span>}
                        </div>
                        <span className="text-xs tabular-nums text-warm-500"><AuditTimestamp value={event.occurred_at} /></span>
                      </div>
                      <p className="mt-2 text-sm leading-relaxed text-warm-700 break-words">{event.summary}</p>
                      {(event.subject?.label || event.context?.label) && (
                        <p className="mt-1 text-xs text-warm-500 break-words">
                          {[event.subject?.label, event.context?.label].filter(Boolean).join(" · ")}
                        </p>
                      )}
                      {event.changes.length > 0 && (
                        <div className="mt-3">
                          <AuditChangeList changes={event.changes} clinical={event.category === "clinical"} />
                        </div>
                      )}
                    </article>
                  </li>
                ))}
              </ol>

              {hasMore && cursor && (
                <div className="mt-4 flex justify-center">
                  <button
                    type="button"
                    disabled={loadingMore}
                    onClick={() => void load(cursor)}
                    className="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-warm-200 bg-white px-4 py-2 text-sm font-semibold text-warm-700 transition-colors hover:bg-warm-50 disabled:cursor-not-allowed disabled:opacity-60"
                  >
                    {loadingMore && <Loader2 className="h-4 w-4 animate-spin" aria-hidden />}
                    Load earlier events
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </Card>
  );
}
