"use client";

import Link from "next/link";
import { AlertCircle, CalendarDays, ClipboardList, ShoppingBag } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "@/lib/apiFetch";
import { getFssDashboard, type FssDashboardSummary } from "@/services/menuCycleService";

interface Announcement {
  id: number;
  title: string;
  body: string;
  pinned: boolean;
  created_at: string;
}

async function getAnnouncements(): Promise<Announcement[]> {
  const response = await apiFetch("/api/fss/announcements?per_page=3");
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(body.message ?? "Failed to load announcements.");
  return body.data ?? [];
}

export function FssHome() {
  const [dashboard, setDashboard] = useState<FssDashboardSummary | null>(null);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [summary, posts] = await Promise.all([getFssDashboard(), getAnnouncements()]);
      if (!summary) throw new Error("Could not load dashboard.");
      setDashboard(summary);
      setAnnouncements(posts);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Could not load dashboard.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  if (loading) return <p role="status" className="py-16 text-center text-sm font-semibold text-warm-500">Loading dashboard…</p>;
  if (error) return (
    <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-800">
      {error} <button type="button" onClick={() => void load()} className="ml-2 min-h-11 cursor-pointer underline">Try again</button>
    </div>
  );

  return (
    <div className="space-y-5">
      <div>
        <p className="text-sm font-bold text-emerald-700">Today</p>
        <h1 className="mt-1 text-2xl font-extrabold tracking-tight">Food service overview</h1>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div className="rounded-2xl border border-emerald-200 bg-white p-4">
          <ClipboardList className="h-5 w-5 text-emerald-700" aria-hidden="true" />
          <p className="mt-3 text-2xl font-extrabold">{dashboard?.meals_to_log_today ?? 0}</p>
          <p className="text-sm text-warm-500">Meals to log</p>
        </div>
        <Link href="/fss/purchase" className="rounded-2xl border border-amber-200 bg-white p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
          <ShoppingBag className="h-5 w-5 text-amber-700" aria-hidden="true" />
          <p className="mt-3 text-2xl font-extrabold">{dashboard?.pending_pos_count ?? 0}</p>
          <p className="text-sm text-warm-500">Pending POs</p>
        </Link>
      </div>

      {dashboard?.active_cycle ? (
        <Link href="/fss/menu" className="flex min-h-16 items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
          <CalendarDays className="h-6 w-6 shrink-0 text-emerald-700" aria-hidden="true" />
          <span className="min-w-0"><span className="block text-xs font-bold uppercase tracking-wide text-emerald-700">Active menu cycle</span><span className="block truncate font-bold text-warm-900">{dashboard.active_cycle.name}</span></span>
        </Link>
      ) : (
        <div className="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800"><AlertCircle className="h-5 w-5 shrink-0" />No active menu cycle. Contact RND.</div>
      )}

      <section className="rounded-2xl border border-warm-200 bg-white">
        <h2 className="border-b border-warm-100 px-4 py-3 text-base font-extrabold">Today&apos;s service</h2>
        {(dashboard?.today_service.length ?? 0) === 0 ? <p className="p-4 text-sm text-warm-500">No service entries for today.</p> : (
          <ul className="divide-y divide-warm-100">{dashboard!.today_service.map((row, index) => <li key={`${row.meal_type}-${index}`} className="flex items-center justify-between gap-3 px-4 py-3"><span><span className="block text-xs font-bold uppercase text-warm-400">{row.meal_type.replaceAll("_", " ")}</span><span className="block text-sm font-semibold">{row.name}</span></span>{row.prepped && <span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">Prepped</span>}</li>)}</ul>
        )}
      </section>

      <section className="rounded-2xl border border-warm-200 bg-white">
        <h2 className="border-b border-warm-100 px-4 py-3 text-base font-extrabold">Announcements</h2>
        {announcements.length === 0 ? <p className="p-4 text-sm text-warm-500">No announcements.</p> : <ul className="divide-y divide-warm-100">{announcements.map((post) => <li key={post.id} className="px-4 py-3"><p className="font-bold">{post.title}</p><p className="mt-1 line-clamp-2 text-sm leading-5 text-warm-600">{post.body}</p></li>)}</ul>}
      </section>
    </div>
  );
}
