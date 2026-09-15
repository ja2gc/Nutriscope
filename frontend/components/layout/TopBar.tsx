"use client";

import React, { useCallback, useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Bell, Menu, User as UserIcon } from "lucide-react";
import { fetchUnreadCount } from "@/services/notificationService";
import { personDisplayName } from "@/lib/personName";

export function TopBar({ onMenuClick }: { onMenuClick?: () => void }) {
  const pathname = usePathname();
  const router = useRouter();
  const { user } = useAuth();

  const [unread, setUnread] = useState(0);
  const refreshUnread = useCallback(async () => {
    if (!user?.role) return;
    try {
      const count = await fetchUnreadCount();
      setUnread(count);
    } catch {
      // Non-fatal — leave the prior count.
    }
  }, [user?.role]);

  useEffect(() => { void refreshUnread(); }, [refreshUnread, pathname]);

  return (
    <header className="h-14 border-b flex items-center justify-between px-4 sm:px-6 select-none shrink-0 z-10 font-sans bg-forest-900 border-forest-line text-white">
      <div className="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
        {/* Hamburger — mobile only */}
        <button
          onClick={onMenuClick}
          className="md:hidden shrink-0 p-1.5 rounded-lg cursor-pointer transition-colors text-white/60 hover:text-white hover:bg-white/10"
          aria-label="Open navigation"
        >
          <Menu className="h-5 w-5" />
        </button>
      </div>

      {/* User Actions */}
      <div className="flex items-center gap-3 sm:gap-5 shrink-0">
        {/* Alerts Bell */}
        {user && (
          <button
            onClick={() => router.push(user.role === "Admin" ? "/admin/notifications" : "/notifications")}
            className="relative p-1.5 rounded-lg cursor-pointer transition-colors text-white/60 hover:text-white hover:bg-white/10"
            title="Notifications"
            aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
          >
            <Bell className="h-4.5 w-4.5" />
            {unread > 0 && (
              <span className="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 flex items-center justify-center rounded-full bg-orange-500 text-white text-xs font-bold ring-2 ring-forest-900">
                {unread > 9 ? "9+" : unread}
              </span>
            )}
          </button>
        )}

        {/* User Card */}
        {user && (
          <button
            type="button"
            onClick={
              user.role === "Admin"
                ? () => router.push("/admin/profile")
                : user.role === "RND"
                ? () => router.push("/profile")
                : undefined
            }
            disabled={user.role !== "RND" && user.role !== "Admin"}
            title={user.role === "RND" || user.role === "Admin" ? "Edit profile" : undefined}
            className={`flex items-center gap-2 sm:gap-3 border-l border-white/10 pl-3 sm:pl-5 rounded-lg transition-colors ${user.role === "RND" || user.role === "Admin" ? "cursor-pointer hover:opacity-80" : "cursor-default"}`}
          >
            <div className="hidden sm:flex flex-col text-right">
              <span className="text-sm font-bold leading-tight text-white">
                {personDisplayName(user)}
              </span>
              <span className="text-xs font-extrabold text-brand-orange-600 uppercase tracking-widest leading-tight mt-0.5">
                {user.role}
              </span>
            </div>

            <div className="h-8 w-8 overflow-hidden rounded-full bg-brand-green-50 border border-brand-green-200 flex items-center justify-center text-brand-green-700 shrink-0">
              {user.profile_photo ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={user.profile_photo} alt={personDisplayName(user)} className="h-full w-full object-cover" />
              ) : (
                <UserIcon className="h-4 w-4" />
              )}
            </div>
          </button>
        )}

      </div>
    </header>
  );
}
