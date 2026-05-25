"use client";

import React, { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Logo } from "@/components/ui/Logo";
import { 
  Compass, 
  CookingPot, 
  HeartHandshake, 
  Salad, 
  TrendingUp, 
  CalendarDays, 
  BellDot, 
  Sliders,
  ChevronLeft,
  ChevronRight
} from "lucide-react";

interface SidebarItem {
  name: string;
  href: string;
  icon: React.ComponentType<any>;
}

const navItems: SidebarItem[] = [
  { name: "Dashboard", href: "/dashboard", icon: Compass },
  { name: "Recipes Database", href: "/recipes", icon: CookingPot },
  { name: "NCP Patients", href: "/ncp", icon: HeartHandshake },
  { name: "Food Service", href: "/food-service", icon: Salad },
  { name: "Reports Center", href: "/reports", icon: TrendingUp },
  { name: "Calendar", href: "/calendar", icon: CalendarDays },
  { name: "Notifications", href: "/notifications", icon: BellDot },
  { name: "System Settings", href: "/settings", icon: Sliders },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user } = useAuth();
  const [collapsed, setCollapsed] = useState(false);

  return (
    <aside 
      className={`bg-sidebar-bg border-r border-sidebar-border min-h-screen flex flex-col transition-all duration-200 ease-in-out shrink-0 select-none ${
        collapsed ? "w-16" : "w-64"
      }`}
    >
      {/* Brand Header */}
      <div className="h-14 border-b border-sidebar-border flex items-center justify-between px-4.5">
        <Logo variant="dark" collapsed={collapsed} />
        
        <button 
          onClick={() => setCollapsed(!collapsed)}
          className="p-1 hover:bg-sidebar-active rounded text-zinc-500 hover:text-zinc-300 cursor-pointer"
          title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
        </button>
      </div>

      {/* Nav List */}
      <nav className="flex-1 py-4.5 space-y-1.5 px-3">
        {navItems.map((item) => {
          // Check if active: exact match or starting with href
          const isActive = pathname === item.href || (item.href !== "/dashboard" && pathname.startsWith(item.href));
          const Icon = item.icon;

          return (
            <Link
              key={item.name}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                isActive
                  ? "bg-sidebar-active text-zinc-100 border-l-2 border-brand-green-600"
                  : "text-zinc-400 hover:bg-sidebar-active/60 hover:text-zinc-200"
              }`}
              title={collapsed ? item.name : undefined}
            >
              <Icon className={`h-4.5 w-4.5 shrink-0 ${isActive ? "text-brand-green-500" : "text-zinc-400"}`} />
              {!collapsed && <span>{item.name}</span>}
            </Link>
          );
        })}
      </nav>

      {/* Active User Session Footer */}
      {!collapsed && user && (
        <div className="p-4 border-t border-sidebar-border bg-sidebar-active/20 text-center shrink-0">
          <span className="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">
            Active Session
          </span>
          <span className="text-xs font-semibold text-zinc-300 mt-1 block truncate" title={user.name}>
            {user.name}
          </span>
          <span className="text-[9px] font-extrabold text-brand-orange-500 uppercase tracking-widest block mt-0.5">
            {user.role}
          </span>
        </div>
      )}
    </aside>
  );
}

