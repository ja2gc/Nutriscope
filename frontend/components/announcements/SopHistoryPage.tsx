"use client";
import { SectionLoading } from "@/components/ui/SectionLoading";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { fetchSopHistory, type Sop } from "@/services/sopService";

function dateLabel(value: string) {
  return new Date(value).toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
}

export function SopHistoryPage({ backHref }: { backHref: string }) {
  const [history, setHistory] = useState<Sop[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [selectedVersion, setSelectedVersion] = useState<Sop | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  async function loadPage(next: number) {
    setLoading(true);
    setError(false);
    try {
      const result = await fetchSopHistory(next);
      setHistory(result.data);
      setMeta(result.meta);
      setPage(next);
    } catch {
      setError(true);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { void loadPage(1); }, []);

  return <div className="mx-auto max-w-3xl space-y-5">
    <div className="flex items-center gap-3 border-b border-warm-200 pb-4">
      {selectedVersion ? <button type="button" onClick={() => setSelectedVersion(null)} className="inline-flex min-h-11 items-center gap-1 rounded-lg border border-warm-200 px-3 text-sm font-semibold text-warm-700 focus-visible:ring-2 focus-visible:ring-emerald-500"><ChevronLeft className="h-4 w-4" /> Back to history</button> : <Link href={backHref} className="inline-flex min-h-11 items-center gap-1 rounded-lg border border-warm-200 px-3 text-sm font-semibold text-warm-700 focus-visible:ring-2 focus-visible:ring-emerald-500"><ChevronLeft className="h-4 w-4" /> Back to SOP</Link>}
      <h1 className="text-xl font-extrabold text-warm-900">{selectedVersion ? "SOP version" : "SOP history"}</h1>
    </div>
    {selectedVersion ? <article className="rounded-2xl border border-warm-200 bg-white p-5"><h2 className="break-words text-lg font-bold text-warm-900">{selectedVersion.title}</h2><p className="mt-2 text-sm text-warm-500">Created by {selectedVersion.author?.name ?? "Unknown"}{selectedVersion.author?.role ? ` · ${selectedVersion.author?.role}` : ""} · {dateLabel(selectedVersion.created_at)}</p><p className="mt-5 whitespace-pre-wrap break-words text-base leading-7 text-warm-700">{selectedVersion.body}</p></article> : <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white">
      {loading ? <SectionLoading label="Loading SOP history…" skeleton /> : error ? <div className="p-6"><p className="text-sm text-red-700">Could not load SOP history.</p><button type="button" onClick={() => void loadPage(page)} className="mt-3 min-h-11 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white">Retry</button></div> : history.length === 0 ? <p className="p-6 text-sm text-warm-500">No past versions.</p> : history.map((version, index) => <button key={version.id} type="button" onClick={() => setSelectedVersion(version)} className="flex min-h-16 w-full items-center gap-3 border-b border-warm-100 px-5 py-3 text-left hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-inset"><span className="min-w-0 flex-1"><span className="block truncate text-sm font-bold text-warm-900">{version.title}</span><span className="mt-1 block text-xs text-warm-500">{dateLabel(version.created_at)}</span></span>{page === 1 && index === 0 && <span className="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Current</span>}<ChevronRight className="h-4 w-4 shrink-0 text-warm-500" /></button>)}
      <Pagination meta={meta} page={page} onPageChange={(next) => void loadPage(next)} />
    </div>}
  </div>;
}
