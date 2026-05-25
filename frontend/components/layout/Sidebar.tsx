"use client";

import React, { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { 
  LayoutDashboard, 
  BookOpen, 
  HeartPulse, 
  Utensils, 
  FileBarChart, 
  Calendar as CalendarIcon, 
  Bell, 
  Settings as SettingsIcon,
  ChevronLeft,
  ChevronRight,
  ShieldCheck
} from "lucide-react";

interface SidebarItem {
  name: string;
  href: string;
  icon: React.ComponentType<any>;
}

const navItems: SidebarItem[] = [
  { name: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { name: "Recipes Database", href: "/recipes", icon: BookOpen },
  { name: "NCP Patients", href: "/ncp", icon: HeartPulse },
  { name: "Food Service", href: "/food-service", icon: Utensils },
  { name: "Reports Center", href: "/reports", icon: FileBarChart },
  { name: "Calendar", href: "/calendar", icon: CalendarIcon },
  { name: "Notifications", href: "/notifications", icon: Bell },
  { name: "System Settings", href: "/settings", icon: SettingsIcon },
];

export function Sidebar() {
  const pathname = usePathname();
  const [collapsed, setCollapsed] = useState(false);

  return (
    <aside 
      className={`bg-white border-r border-gray-200 min-h-screen flex flex-col transition-all duration-200 ease-in-out shrink-0 select-none ${
        collapsed ? "w-16" : "w-64"
      }`}
    >
      {/* Brand Header */}
      <div className="h-14 border-b border-gray-200 flex items-center justify-between px-4">
        {!collapsed && (
          <div className="flex items-center gap-2">
            <ShieldCheck className="h-5 w-5 text-blue-600" />
            <span className="font-extrabold text-sm tracking-wider text-gray-900 uppercase">
              NutriScope
            </span>
          </div>
        )}
        {collapsed && (
          <div className="mx-auto">
            <ShieldCheck className="h-5 w-5 text-blue-600" />
          </div>
        )}
        <button 
          onClick={() => setCollapsed(!collapsed)}
          className="p-1 hover:bg-gray-100 rounded text-gray-400 hover:text-gray-700 cursor-pointer"
          title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
        </button>
      </div>

      {/* Nav List */}
      <nav className="flex-1 py-4 space-y-1 px-3">
        {navItems.map((item) => {
          // Check if active: exact match or starting with href
          const isActive = pathname === item.href || (item.href !== "/dashboard" && pathname.startsWith(item.href));
          const Icon = item.icon;

          return (
            <Link
              key={item.name}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2 rounded text-xs font-semibold uppercase tracking-wider transition-colors ${
                isActive
                  ? "bg-blue-50 text-blue-900 border-l-2 border-blue-600"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
              }`}
              title={collapsed ? item.name : undefined}
            >
              <Icon className={`h-4.5 w-4.5 shrink-0 ${isActive ? "text-blue-600" : "text-gray-400"}`} />
              {!collapsed && <span>{item.name}</span>}
            </Link>
          );
        })}
      </nav>

      {/* Mini Info Footer */}
      {!collapsed && (
        <div className="p-4 border-t border-gray-150 bg-gray-50/50">
          <div className="text-[10px] text-gray-400 font-bold uppercase tracking-wider text-center">
            Role: Registered Dietitian
          </div>
        </div>
      )}
    </aside>
  );
}
