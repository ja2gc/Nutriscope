"use client";

import React from "react";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Bell, LogOut, User as UserIcon } from "lucide-react";

export function TopBar() {
  const pathname = usePathname();
  const router = useRouter();
  const { user, logout } = useAuth();

  const getModuleTitle = () => {
    if (pathname === "/dashboard") return "Overview & Operations Center";
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
      router.push("/login");
    } catch (err) {
      console.error("Failed to log out:", err);
    }
  };

  return (
    <header className="h-14 bg-white border-b border-zinc-200 flex items-center justify-between px-6 select-none shrink-0 z-10 font-sans">
      {/* Module Title */}
      <h1 className="text-sm font-bold text-zinc-800 tracking-wide uppercase">
        {getModuleTitle()}
      </h1>

      {/* User Actions */}
      <div className="flex items-center gap-5">
        {/* Alerts Bell */}
        <button 
          className="relative p-1.5 text-zinc-400 hover:text-zinc-600 rounded-lg hover:bg-zinc-50 cursor-pointer transition-colors"
          title="System notifications"
        >
          <Bell className="h-4.5 w-4.5" />
          <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-orange-500 ring-2 ring-white animate-pulse" />
        </button>

        {/* User Card */}
        {user && (
          <div className="flex items-center gap-3 border-l border-zinc-200 pl-5">
            <div className="flex flex-col text-right">
              <span className="text-xs font-bold text-zinc-800 leading-tight">
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
          className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-zinc-500 hover:text-orange-600 rounded-lg hover:bg-orange-50 transition-all duration-150 cursor-pointer tracking-wide"
          title="Sign out of system"
        >
          <LogOut className="h-4 w-4" />
          <span>Sign Out</span>
        </button>
      </div>
    </header>
  );
}

