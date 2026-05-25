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
    if (pathname === "/dashboard") return "Clinical Overview Dashboard";
    if (pathname.startsWith("/recipes")) return "USDA & Custom Recipes Database";
    if (pathname.startsWith("/ncp")) return "Nutrition Care Process (NCP)";
    if (pathname.startsWith("/food-service")) return "Food Service & Kitchen Oversight";
    if (pathname.startsWith("/reports")) return "Clinical Reports & Document Center";
    if (pathname.startsWith("/calendar")) return "Care Calendar & Follow-ups";
    if (pathname.startsWith("/notifications")) return "System Alerts";
    if (pathname.startsWith("/settings")) return "Preferences & System Variables";
    return "Clinical Operations Console";
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
    <header className="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-6 select-none shrink-0 z-10">
      {/* Module Title */}
      <h1 className="text-sm font-bold text-gray-800 uppercase tracking-wider">
        {getModuleTitle()}
      </h1>

      {/* User Actions */}
      <div className="flex items-center gap-6">
        {/* Alerts Bell */}
        <button 
          className="relative p-1.5 text-gray-400 hover:text-gray-600 rounded hover:bg-gray-50 cursor-pointer"
          title="System notifications"
        >
          <Bell className="h-4.5 w-4.5" />
          <span className="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-600 ring-2 ring-white" />
        </button>

        {/* User Card */}
        {user && (
          <div className="flex items-center gap-3 border-l border-gray-200 pl-6">
            <div className="flex flex-col text-right">
              <span className="text-xs font-bold text-gray-900 leading-tight">
                {user.name}
              </span>
              <span className="text-[10px] font-extrabold text-blue-700 uppercase tracking-widest leading-tight">
                {user.role}
              </span>
            </div>
            
            <div className="h-8 w-8 rounded-full bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-700">
              <UserIcon className="h-4 w-4" />
            </div>
          </div>
        )}

        {/* Log Out */}
        <button
          onClick={handleLogout}
          className="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:text-red-700 rounded hover:bg-red-50 transition-colors cursor-pointer uppercase tracking-wider"
          title="Sign out of system"
        >
          <LogOut className="h-4 w-4" />
          <span>Exit</span>
        </button>
      </div>
    </header>
  );
}
