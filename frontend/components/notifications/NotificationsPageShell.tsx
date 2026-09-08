"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Bell, BellDot, CalendarClock, CheckCheck, Megaphone, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { EmptyState } from "@/components/ui/EmptyState";
import { PageHeader } from "@/components/ui/PageHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  dismissNotification,
  fetchNotifications,
  fetchUnreadCount,
  markAllNotificationsRead,
  markNotificationOpened,
  notificationTargetHref,
  type Notification,
} from "@/services/notificationService";

function iconFor(type?: string | null) {
  if (type === "follow_up" || type === "appointment_due") return <CalendarClock className="h-4 w-4 text-amber-600" />;
  if (type === "announcement") return <Megaphone className="h-4 w-4 text-emerald-600" />;
  return <Bell className="h-4 w-4 text-warm-500" />;
}

function formatWhen(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const mins = Math.round((Date.now() - date.getTime()) / 60000);
  if (mins < 1) return "Just now";
  if (mins < 60) return `${mins}m ago`;
  if (mins < 1440) return `${Math.round(mins / 60)}h ago`;
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export function NotificationsPageShell({ role }: { role: "RND" | "Admin" }) {
  const router = useRouter();
  const [items, setItems] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [marking, setMarking] = useState(false);
  const [dismissing, setDismissing] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [unreadTotal, setUnreadTotal] = useState(0);

  const load = useCallback(async () => {
    try {
      setLoading(true); setError(null);
      const [result, count] = await Promise.all([fetchNotifications(page, 10), fetchUnreadCount()]);
      setItems(result.data); setMeta(result.meta); setUnreadTotal(count);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load notifications.");
    } finally { setLoading(false); }
  }, [page]);

  useEffect(() => { void load(); }, [load]);

  function open(notification: Notification) {
    if (!notification.read) {
      setItems((current) => current.map((item) => item.id === notification.id ? { ...item, read: true } : item));
      setUnreadTotal((count) => Math.max(0, count - 1));
    }
    router.push(notificationTargetHref(notification, role));
    void markNotificationOpened(notification.id).catch(() => undefined);
  }

  async function dismiss(notification: Notification) {
    setDismissing(notification.id); setError(null);
    try {
      await dismissNotification(notification.id);
      setItems((current) => current.filter((item) => item.id !== notification.id));
      if (!notification.read) setUnreadTotal((count) => Math.max(0, count - 1));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to dismiss notification.");
    } finally { setDismissing(null); }
  }

  async function markAllRead() {
    setMarking(true); setUnreadTotal(0); setItems((current) => current.map((item) => ({ ...item, read: true })));
    try { await markAllNotificationsRead(); } catch { void load(); } finally { setMarking(false); }
  }

  const admin = role === "Admin";
  return <div className="space-y-6 font-sans">
    <PageHeader
      crumbs={admin ? [["Admin", "/admin/dashboard"], ["Notifications"]] : [["Home", "/dashboard"], ["Notifications"]]}
      title="Notifications"
      icon={<BellDot className="h-5 w-5 text-emerald-600" />}
      actions={unreadTotal > 0 ? <Button variant="secondary" onClick={() => void markAllRead()} loading={marking} className="w-auto"><CheckCheck className="h-4 w-4" /> Mark all read</Button> : undefined}
    />
    {error && <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{error}</div>}
    {loading ? <div className="space-y-3">{[0, 1, 2].map((item) => <div key={item} className="h-16 animate-pulse rounded-2xl bg-warm-100" />)}</div>
      : items.length === 0 ? <EmptyState icon={<Bell className="h-8 w-8" />} title="No notifications" message="You're all caught up." />
        : <div className="space-y-2.5">{items.map((notification) => <article key={notification.id} className={`flex items-start gap-3 rounded-2xl border p-4 ${notification.read ? "border-warm-200 bg-white" : "border-emerald-100 bg-emerald-50/40"}`}>
          <button type="button" onClick={() => open(notification)} className="flex min-w-0 flex-1 items-start gap-3 text-left">
            <span className="mt-0.5 shrink-0">{iconFor(notification.type)}</span>
            <span className="min-w-0 flex-1"><span className="flex items-center gap-2"><span className={`truncate text-base ${notification.read ? "font-semibold text-warm-700" : "font-extrabold text-warm-900"}`}>{notification.title}</span>{!notification.read && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />}</span><span className="mt-0.5 block text-sm leading-relaxed text-warm-500">{notification.message}</span>{!notification.dismissible && <span className="mt-2 inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-amber-700">Action required</span>}</span>
            <span className="mt-0.5 shrink-0 text-xs font-semibold text-warm-400">{formatWhen(notification.created_at)}</span>
          </button>
          {notification.dismissible && <button type="button" disabled={dismissing === notification.id} onClick={() => void dismiss(notification)} className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-warm-200 px-2 py-1.5 text-xs font-bold text-warm-600 hover:bg-warm-50 disabled:opacity-50"><X className="h-3.5 w-3.5" /> Dismiss</button>}
        </article>)}</div>}
    {!loading && <Pagination meta={meta} page={page} onPageChange={setPage} />}
  </div>;
}
