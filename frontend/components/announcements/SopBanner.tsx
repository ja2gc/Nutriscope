"use client";

import React, { useEffect, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import { fetchCurrentSop, fetchSopHistory, saveSop, Sop } from "@/services/sopService";
import { ClipboardList, History, PencilLine, X } from "lucide-react";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";

function formatTimeStamp(value: string) {
  return new Date(value).toLocaleString("en-US", {
    month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit",
  });
}

/**
 * Standard Operating Procedure banner — pinned at the top of the announcements
 * board, same visual language as a pinned post. RND/Admin can revise (each save is
 * a new version); History shows the full audit trail of past SOPs.
 */
export function SopBanner() {
  const { user } = useAuth();
  const canEdit = user?.role === "RND" || user?.role === "Admin";

  const [sop, setSop] = useState<Sop | null>(null);
  const [loading, setLoading] = useState(true);

  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState({ title: "", body: "" });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [historyOpen, setHistoryOpen] = useState(false);
  const [history, setHistory] = useState<Sop[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyMeta, setHistoryMeta] = useState<PaginationMeta | null>(null);

  async function load() {
    setLoading(true);
    try {
      setSop(await fetchCurrentSop());
    } catch {
      /* banner is non-critical — stay quiet on load failure */
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  function openEditor() {
    setDraft({ title: sop?.title ?? "Standard Operating Procedure", body: sop?.body ?? "" });
    setError(null);
    setEditing(true);
  }

  async function openHistory(page = 1) {
    setHistoryOpen(true);
    setHistoryLoading(true);
    try {
      const result = await fetchSopHistory(page);
      setHistory(result.data);
      setHistoryMeta(result.meta);
      setHistoryPage(page);
    } catch {
      setHistory([]);
    } finally {
      setHistoryLoading(false);
    }
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!draft.title.trim() || !draft.body.trim()) return;
    setSaving(true);
    setError(null);
    try {
      await saveSop({ title: draft.title.trim(), body: draft.body.trim() });
      setEditing(false);
      void load();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to save SOP.");
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return <div className="h-24 rounded-3xl bg-warm-100 animate-pulse" />;
  }

  if (!sop && !canEdit) return null; // nothing to show non-editors

  return (
    <div className="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row">
        <div className="flex items-center gap-2.5">
          <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white">
            <ClipboardList className="h-4.5 w-4.5" />
          </span>
          <div>
            <div className="text-xs font-extrabold uppercase tracking-[0.18em] text-emerald-700">
              Standard Operating Procedure
            </div>
            {sop?.author && (
              <div className="text-xs font-semibold text-emerald-600/80">
                Last changed by {sop.author.name} · {sop.author.role} · {formatTimeStamp(sop.created_at)}
              </div>
            )}
          </div>
        </div>
        <div className="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto">
          <button
            type="button"
            onClick={() => void openHistory()}
            className="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-bold uppercase tracking-wider text-emerald-700 transition-colors hover:bg-white sm:flex-none"
          >
            <History className="h-3.5 w-3.5" /> History
          </button>
          {canEdit && (
            <button
              type="button"
              onClick={openEditor}
              className="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-bold uppercase tracking-wider text-emerald-700 transition-colors hover:bg-white sm:flex-none"
            >
              <PencilLine className="h-3.5 w-3.5" /> {sop ? "Revise" : "Set SOP"}
            </button>
          )}
        </div>
      </div>

      {sop ? (
        <div className="mt-3">
          <h4 className="text-base font-extrabold text-warm-900">{sop.title}</h4>
          <p className="mt-1 text-sm text-warm-700 leading-6 whitespace-pre-wrap">{sop.body}</p>
        </div>
      ) : (
        <p className="mt-3 text-sm text-emerald-700/80">No SOP set yet. Click “Set SOP” to add one.</p>
      )}

      {/* Editor modal */}
      {editing && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-forest-900/45 backdrop-blur-sm"
          onClick={() => setEditing(false)}
        >
          <div
            className="w-full max-w-2xl bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-warm-100 bg-warm-50 flex items-center justify-between">
              <h3 className="text-sm font-bold text-warm-900 uppercase tracking-[0.18em]">Revise SOP</h3>
              <button
                type="button"
                onClick={() => setEditing(false)}
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-warm-200 text-xs font-bold uppercase tracking-wider text-warm-600 hover:bg-white"
              >
                <X className="h-3.5 w-3.5" /> Close
              </button>
            </div>
            <form onSubmit={submit} className="p-5 space-y-4">
              <p className="text-xs text-warm-500">
                Saving creates a new version. The previous SOP is kept in History.
              </p>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-warm-500 uppercase tracking-wider">Title</label>
                <input
                  value={draft.title}
                  onChange={(e) => setDraft((p) => ({ ...p, title: e.target.value }))}
                  className="w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-warm-500 uppercase tracking-wider">Procedure</label>
                <textarea
                  value={draft.body}
                  onChange={(e) => setDraft((p) => ({ ...p, body: e.target.value }))}
                  className="w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 min-h-40"
                />
              </div>
              {error && (
                <div className="text-sm font-semibold text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
                  {error}
                </div>
              )}
              <div className="flex justify-end">
                <Button variant="primary" loading={saving} className="w-auto px-4 py-2 text-xs font-bold uppercase tracking-wider">
                  Save Version
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* History modal */}
      {historyOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-forest-900/45 backdrop-blur-sm"
          onClick={() => setHistoryOpen(false)}
        >
          <div
            className="w-full max-w-2xl bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-2xl max-h-[85vh] flex flex-col"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-warm-100 bg-warm-50 flex items-center justify-between">
              <h3 className="text-sm font-bold text-warm-900 uppercase tracking-[0.18em]">SOP History</h3>
              <button
                type="button"
                onClick={() => setHistoryOpen(false)}
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-warm-200 text-xs font-bold uppercase tracking-wider text-warm-600 hover:bg-white"
              >
                <X className="h-3.5 w-3.5" /> Close
              </button>
            </div>
            <div className="p-5 space-y-3 overflow-y-auto">
              {historyLoading ? (
                <div className="text-sm text-warm-400 text-center py-8">Loading…</div>
              ) : history.length === 0 ? (
                <div className="text-sm text-warm-400 text-center py-8">No past versions.</div>
              ) : (
                history.map((v, i) => (
                  <details key={v.id} className="group rounded-2xl border border-warm-200 bg-white open:border-emerald-200">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-2.5 text-sm font-bold text-warm-800 transition-colors hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-inset">
                      <span>{formatTimeStamp(v.created_at)}</span>
                      {i === 0 && (
                        <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border bg-emerald-50 text-emerald-700 border-emerald-200">
                          Current
                        </span>
                      )}
                    </summary>
                    <div className="border-t border-warm-100 px-4 pb-4 pt-3">
                      <div className="break-words text-base font-bold text-warm-900">{v.title}</div>
                      <div className="mt-1 text-xs font-semibold text-warm-500">
                        Created by {v.author?.name ?? "Unknown"}{v.author?.role ? ` · ${v.author.role}` : ""} · {formatTimeStamp(v.created_at)}
                      </div>
                      <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-warm-700">{v.body}</p>
                    </div>
                  </details>
                ))
              )}
              <Pagination meta={historyMeta} page={historyPage} onPageChange={(page) => void openHistory(page)} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
