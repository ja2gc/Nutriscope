"use client";

import React from "react";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Bell, LogOut, Menu, User as UserIcon } from "lucide-react";

export function TopBar({ onMenuClick }: { onMenuClick?: () => void }) {
  const pathname = usePathname();
  const router = useRouter();
  const { user, logout } = useAuth();

  const getModuleTitle = () => {
    if (pathname === "/dashboard") return "Overview & Operations Center";
    if (pathname.startsWith("/admin/dashboard")) return "System Administration Overview";
    if (pathname.startsWith("/admin/users")) return "RBAC & User Access Manager";
    if (pathname.startsWith("/admin/audit-logs")) return "System Activity & Audit Logs";
    if (pathname.startsWith("/admin/announcements")) return "Publish System Announcements";
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
      // Force redirect even on failure — cookie is cleared server-side
      router.replace("/login");
    }
  };

  const isAdminPath = pathname.startsWith("/admin");

  return (
    <header className={`h-14 border-b flex items-center justify-between px-6 select-none shrink-0 z-10 font-sans transition-colors duration-150 ${
      isAdminPath
        ? "bg-zinc-950 border-zinc-900 text-zinc-100"
        : "bg-white border-zinc-200 text-zinc-800"
    }`}>
      <div className="flex items-center gap-3">
        {/* Hamburger — mobile only */}
        <button
          onClick={onMenuClick}
          className={`md:hidden p-1.5 rounded-lg cursor-pointer transition-colors ${
            isAdminPath ? "text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900" : "text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100"
          }`}
          aria-label="Open navigation"
        >
          <Menu className="h-5 w-5" />
        </button>
        {/* Module Title */}
        <h1 className={`text-sm font-bold tracking-wide uppercase ${
          isAdminPath ? "text-zinc-100" : "text-zinc-800"
        }`}>
          {getModuleTitle()}
        </h1>
      </div>

      {/* User Actions */}
      <div className="flex items-center gap-5">
        {/* Alerts Bell */}
        <button 
          className={`relative p-1.5 rounded-lg cursor-pointer transition-colors ${
            isAdminPath ? "text-zinc-400 hover:text-zinc-200 hover:bg-zinc-900" : "text-zinc-400 hover:text-zinc-600 hover:bg-zinc-50"
          }`}
          title="System notifications"
        >
          <Bell className="h-4.5 w-4.5" />
          <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-orange-500 ring-2 ring-white animate-pulse" />
        </button>

        {/* User Card */}
        {user && (
          <div className={`flex items-center gap-3 border-l pl-5 ${
            isAdminPath ? "border-zinc-850" : "border-zinc-200"
          }`}>
            <div className="flex flex-col text-right">
              <span className={`text-xs font-bold leading-tight ${
                isAdminPath ? "text-zinc-100" : "text-zinc-800"
              }`}>
                {user.name}
              </span>
              <span className="text-[9px] font-extrabold text-orange-600 uppercase tracking-widest leading-tight mt-0.5">
                {user.role}
              </span>
            </div>
            
            <div className="h-8 w-8 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700">
              <UserIcon className="h-4 w-4" />
            </div>
          </div>
        )}

        {/* Log Out */}
        <button
          onClick={handleLogout}
          className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-150 cursor-pointer tracking-wide ${
            isAdminPath
              ? "text-zinc-400 hover:text-orange-500 hover:bg-zinc-900"
              : "text-zinc-500 hover:text-orange-600 hover:bg-orange-50"
          }`}
          title="Sign out of system"
        >
          <LogOut className="h-4 w-4" />
          <span>Sign Out</span>
        </button>
      </div>
    </header>
  );
}

