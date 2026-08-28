"use client";

import React, { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { BellDot, Bell, Megaphone, CalendarClock, CheckCheck } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Button } from "@/components/ui/Button";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  Notification,
  fetchNotifications,
  fetchUnreadCount,
  markNotificationOpened,
  markAllNotificationsRead,
  notificationTargetHref,
} from "@/services/notificationService";

function iconFor(type?: string | null) {
  if (type === "follow_up") return <CalendarClock className="h-4 w-4 text-amber-600" />;
  if (type === "announcement") return <Megaphone className="h-4 w-4 text-emerald-600" />;
  return <Bell className="h-4 w-4 text-warm-500" />;
}

function formatWhen(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const diffMs = Date.now() - date.getTime();
  const mins = Math.round(diffMs / 60000);
  if (mins < 1) return "Just now";
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

export default function AdminNotificationsPage() {
  const router = useRouter();
  const [items, setItems] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [marking, setMarking] = useState(false);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [unreadTotal, setUnreadTotal] = useState(0);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [result, count] = await Promise.all([fetchNotifications(page, 10), fetchUnreadCount()]);
      setItems(result.data);
      setMeta(result.meta);
      setUnreadTotal(count);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load notifications.");
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => { void load(); }, [load]);

  function handleRead(n: Notification) {
    const href = notificationTargetHref(n, "Admin");
    if (!n.read) {
      setItems((prev) => prev.map((x) => (x.id === n.id ? { ...x, read: true } : x)));
      setUnreadTotal((count) => Math.max(0, count - 1));
    }
    router.push(href);
    void markNotificationOpened(n.id).catch(() => undefined);
  }

  async function handleReadAll() {
    setMarking(true);
    setUnreadTotal(0);
    setItems((prev) => prev.map((x) => ({ ...x, read: true })));
    try {
      await markAllNotificationsRead();
    } catch {
      void load();
    } finally {
      setMarking(false);
    }
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Admin", "/admin/dashboard"], ["Notifications"]]}
        title="Notifications"
        icon={<BellDot className="h-5 w-5 text-emerald-600" />}
        subtitle="Announcements and system alerts addressed to you."
        actions={
          unreadTotal > 0 ? (
            <Button variant="secondary" onClick={handleReadAll} loading={marking} className="w-auto">
              <CheckCheck className="h-4 w-4" />
              Mark all read
            </Button>
          ) : undefined
        }
      />

      {error && (
        <div className="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-semibold">
          {error}
        </div>
      )}

      {loading ? (
        <div className="space-y-3">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-16 bg-warm-100 rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <EmptyState
          icon={<Bell className="h-8 w-8" />}
          title="No notifications"
          message="You're all caught up. Announcements addressed to Admin or All will show up here."
        />
      ) : (
        <>
        <div className="space-y-2.5">
          {items.map((n) => (
            <Card
              key={n.id}
              onClick={() => handleRead(n)}
              onKeyDown={(event) => {
                if (event.key === "Enter" || event.key === " ") {
                  event.preventDefault();
                  handleRead(n);
                }
              }}
              role="button"
              tabIndex={0}
              className={`p-4 flex items-start gap-3 cursor-pointer transition-colors ${
                n.read ? "bg-white" : "bg-emerald-50/40 border-emerald-100"
              }`}
            >
              <div className="mt-0.5 shrink-0">{iconFor(n.type)}</div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <h3 className={`text-base truncate ${n.read ? "font-semibold text-warm-700" : "font-extrabold text-warm-900"}`}>
                    {n.title}
                  </h3>
                  {!n.read && <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />}
                </div>
                <p className="text-sm text-warm-500 mt-0.5 leading-relaxed">{n.message}</p>
              </div>
              <span className="text-xs font-semibold text-warm-400 shrink-0 mt-0.5">{formatWhen(n.created_at)}</span>
            </Card>
          ))}
        </div>
        <Pagination meta={meta} page={page} onPageChange={setPage} />
        </>
      )}
    </div>
  );
}
