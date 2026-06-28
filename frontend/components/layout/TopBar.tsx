"use client";

import React, { useCallback, useEffect, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Bell, LogOut, Menu, User as UserIcon } from "lucide-react";
import { fetchUnreadCount } from "@/services/notificationService";

export function TopBar({ onMenuClick }: { onMenuClick?: () => void }) {
  const pathname = usePathname();
  const router = useRouter();
  const { user, logout } = useAuth();

  const [unread, setUnread] = useState(0);
  const refreshUnread = useCallback(async () => {
    if (user?.role !== "RND" && user?.role !== "Admin") return;
    try {
      const count = await fetchUnreadCount();
      setUnread(count);
    } catch {
      // Non-fatal — leave the prior count.
    }
  }, [user?.role]);

  useEffect(() => { void refreshUnread(); }, [refreshUnread, pathname]);

  const getModuleTitle = () => {
    if (pathname === "/dashboard") return "Overview & Operations Center";
    if (pathname.startsWith("/admin/dashboard")) return "System Administration Overview";
    if (pathname.startsWith("/admin/users")) return "RBAC & User Access Manager";
    if (pathname.startsWith("/admin/audit-logs")) return "System Activity & Audit Logs";
    if (pathname.startsWith("/admin/announcements")) return "Publish System Announcements";
    if (pathname.startsWith("/announcements")) return "Department Announcements";
    if (pathname.startsWith("/admin/notifications")) return "Activity Notifications";
    if (pathname.startsWith("/admin/reports")) return "Operations & Census Reports";
    if (pathname.startsWith("/admin/settings")) return "Global Hospital Settings";
    if (pathname.startsWith("/recipes")) return "Recipes & Ingredient Database";
    if (pathname.startsWith("/ncp")) return "Patient Nutrition Care Center";
    if (pathname.startsWith("/food-service")) return "Food Service & Kitchen Operations";
    if (pathname.startsWith("/reports")) return "Clinical & Operational Reports";
    if (pathname.startsWith("/calendar")) return "Care Calendar & Schedules";
    if (pathname.startsWith("/notifications")) return "Activity Notifications";
    if (pathname.startsWith("/settings")) return "System Settings & Preferences";
    return "Nutrition Operations Console";
  };

  const handleLogout = async () => {
    try {
      await logout();
      router.replace("/login");
    } catch {
      router.replace("/login");
    }
  };

  const isAdminPath = pathname.startsWith("/admin");

  return (
    <header className={`h-14 border-b flex items-center justify-between px-4 sm:px-6 select-none shrink-0 z-10 font-sans transition-colors duration-150 ${
      isAdminPath
        ? "bg-forest-900 border-forest-line text-white"
        : "bg-white border-warm-200 text-warm-800"
    }`}>
      <div className="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
        {/* Hamburger — mobile only */}
        <button
          onClick={onMenuClick}
          className={`md:hidden shrink-0 p-1.5 rounded-lg cursor-pointer transition-colors ${
            isAdminPath
              ? "text-white/50 hover:text-white hover:bg-white/10"
              : "text-warm-500 hover:text-warm-800 hover:bg-warm-100"
          }`}
          aria-label="Open navigation"
        >
          <Menu className="h-5 w-5" />
        </button>
        {/* Module Title */}
        <h1 className={`text-sm font-bold tracking-wide uppercase truncate ${
          isAdminPath ? "text-white" : "text-warm-800"
        }`}>
          {getModuleTitle()}
        </h1>
      </div>

      {/* User Actions */}
      <div className="flex items-center gap-3 sm:gap-5 shrink-0">
        {/* Alerts Bell */}
        {(user?.role === "RND" || user?.role === "Admin") && (
          <button
            onClick={() => router.push(user.role === "Admin" ? "/admin/notifications" : "/notifications")}
            className={`relative p-1.5 rounded-lg cursor-pointer transition-colors ${
              isAdminPath
                ? "text-white/50 hover:text-white hover:bg-white/10"
                : "text-warm-400 hover:text-warm-600 hover:bg-warm-50"
            }`}
            title="Notifications"
            aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
          >
            <Bell className="h-4.5 w-4.5" />
            {unread > 0 && (
              <span className="absolute -top-0.5 -right-0.5 min-w-4 h-4 px-1 flex items-center justify-center rounded-full bg-orange-500 text-white text-[9px] font-bold ring-2 ring-white">
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
            className={`flex items-center gap-2 sm:gap-3 border-l pl-3 sm:pl-5 rounded-lg transition-colors ${
              isAdminPath ? "border-white/10" : "border-warm-200"
            } ${user.role === "RND" || user.role === "Admin" ? "cursor-pointer hover:opacity-80" : "cursor-default"}`}
          >
            <div className="hidden sm:flex flex-col text-right">
              <span className={`text-xs font-bold leading-tight ${
                isAdminPath ? "text-white" : "text-warm-800"
              }`}>
                {user.name}
              </span>
              <span className="text-[9px] font-extrabold text-brand-orange-600 uppercase tracking-widest leading-tight mt-0.5">
                {user.role}
              </span>
            </div>

            <div className="h-8 w-8 overflow-hidden rounded-full bg-brand-green-50 border border-brand-green-200 flex items-center justify-center text-brand-green-700 shrink-0">
              {user.profile_photo ? (
                <img src={user.profile_photo} alt={user.name} className="h-full w-full object-cover" />
              ) : (
                <UserIcon className="h-4 w-4" />
              )}
            </div>
          </button>
        )}

        {/* Log Out */}
        <button
          onClick={handleLogout}
          className={`flex items-center gap-1.5 px-2 sm:px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-150 cursor-pointer tracking-wide ${
            isAdminPath
              ? "text-white/40 hover:text-orange-400 hover:bg-white/10"
              : "text-warm-500 hover:text-brand-orange-600 hover:bg-brand-orange-50"
          }`}
          title="Sign out of system"
        >
          <LogOut className="h-4 w-4" />
          <span className="hidden sm:inline">Sign Out</span>
        </button>
      </div>
    </header>
  );
}
