"use client";

import React, { useCallback, useEffect, useState } from "react";
import { BellDot, Bell, Megaphone, CalendarClock, CheckCheck } from "lucide-react";
import { PageHeader } from "@/components/ui/PageHeader";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Button } from "@/components/ui/Button";
import {
  Notification,
  fetchNotifications,
  markNotificationRead,
  markAllNotificationsRead,
} from "@/services/notificationService";

function iconFor(type?: string | null) {
  if (type === "follow_up") return <CalendarClock className="h-4 w-4 text-amber-600" />;
  if (type === "announcement") return <Megaphone className="h-4 w-4 text-emerald-600" />;
  return <Bell className="h-4 w-4 text-zinc-500" />;
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

export default function NotificationsPage() {
  const [items, setItems] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [marking, setMarking] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      setItems(await fetchNotifications());
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load notifications.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const unreadCount = items.filter((n) => !n.read).length;

  async function handleRead(n: Notification) {
    if (n.read) return;
    setItems((prev) => prev.map((x) => (x.id === n.id ? { ...x, read: true } : x)));
    try {
      await markNotificationRead(n.id);
    } catch {
      void load();
    }
  }

  async function handleReadAll() {
    setMarking(true);
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
        crumbs={[["Home", "/dashboard"], ["Notifications"]]}
        title="Notifications"
        icon={<BellDot className="h-5 w-5 text-emerald-600" />}
        subtitle="Announcements and upcoming follow-up reminders."
        actions={
          unreadCount > 0 ? (
            <Button variant="secondary" onClick={handleReadAll} loading={marking} className="w-auto">
              <CheckCheck className="h-4 w-4" />
              Mark all read
            </Button>
          ) : undefined
        }
      />

      {error && (
        <div className="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-semibold">
          {error}
        </div>
      )}

      {loading ? (
        <div className="space-y-3">
          {[0, 1, 2].map((i) => (
            <div key={i} className="h-16 bg-zinc-100 rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <EmptyState
          icon={<Bell className="h-8 w-8" />}
          title="No notifications"
          message="You're all caught up. Announcements and follow-up reminders will show up here."
        />
      ) : (
        <div className="space-y-2.5">
          {items.map((n) => (
            <Card
              key={n.id}
              onClick={() => handleRead(n)}
              className={`p-4 flex items-start gap-3 cursor-pointer transition-colors ${
                n.read ? "bg-white" : "bg-emerald-50/40 border-emerald-100"
              }`}
            >
              <div className="mt-0.5 shrink-0">{iconFor(n.type)}</div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <h3 className={`text-sm truncate ${n.read ? "font-semibold text-zinc-700" : "font-extrabold text-zinc-900"}`}>
                    {n.title}
                  </h3>
                  {!n.read && <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />}
                </div>
                <p className="text-xs text-zinc-500 mt-0.5 leading-relaxed">{n.message}</p>
              </div>
              <span className="text-[10px] font-semibold text-zinc-400 shrink-0 mt-0.5">{formatWhen(n.created_at)}</span>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
