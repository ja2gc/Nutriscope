"use client";

import React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import {
  Compass,
  Megaphone,
  HeartHandshake,
  CookingPot,
  Users,
  TrendingUp,
  Menu,
} from "lucide-react";

interface NavItem {
  label: string;
  href: string;
  icon: React.ElementType;
  active: (pathname: string) => boolean;
}

const RND_ITEMS: NavItem[] = [
  {
    label: "Home",
    href: "/dashboard",
    icon: Compass,
    active: (p) => p === "/dashboard",
  },
  {
    label: "Posts",
    href: "/announcements",
    icon: Megaphone,
    active: (p) => p.startsWith("/announcements"),
  },
  {
    label: "Patients",
    href: "/ncp/patients",
    icon: HeartHandshake,
    active: (p) => p.startsWith("/ncp"),
  },
  {
    label: "Library",
    href: "/food-library",
    icon: CookingPot,
    active: (p) => p.startsWith("/food-library"),
  },
];

const ADMIN_ITEMS: NavItem[] = [
  {
    label: "Home",
    href: "/admin/dashboard",
    icon: Compass,
    active: (p) => p === "/admin/dashboard",
  },
  {
    label: "Posts",
    href: "/admin/announcements",
    icon: Megaphone,
    active: (p) => p.startsWith("/admin/announcements"),
  },
  {
    label: "Users",
    href: "/admin/users",
    icon: Users,
    active: (p) => p.startsWith("/admin/users"),
  },
  {
    label: "Reports",
    href: "/admin/reports",
    icon: TrendingUp,
    active: (p) => p.startsWith("/admin/reports"),
  },
];

export function MobileBottomNav({ onMenuOpen }: { onMenuOpen: () => void }) {
  const pathname = usePathname();
  const { user } = useAuth();

  const isAdmin = pathname.startsWith("/admin");
  const items = isAdmin ? ADMIN_ITEMS : RND_ITEMS;

  const baseBar = isAdmin
    ? "bg-zinc-950 border-t border-zinc-800"
    : "bg-white border-t border-zinc-200";

  const activeText = "text-emerald-600";
  const inactiveText = isAdmin ? "text-zinc-500" : "text-zinc-400";
  const activeIndicator = "bg-emerald-600";

  return (
    <nav
      className={`fixed bottom-0 inset-x-0 z-50 md:hidden ${baseBar}`}
      style={{ paddingBottom: "max(env(safe-area-inset-bottom), 0px)" }}
      aria-label="Mobile navigation"
    >
      <div className="flex items-stretch h-16">
        {/* Primary nav items */}
        {items.map((item) => {
          const isActive = item.active(pathname);
          const Icon = item.icon;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex-1 flex flex-col items-center justify-center gap-1 relative transition-colors duration-150 ${
                isActive ? activeText : inactiveText
              }`}
              aria-current={isActive ? "page" : undefined}
            >
              {/* Active indicator bar */}
              <span
                className={`absolute top-0 left-1/2 -translate-x-1/2 h-[2px] w-8 rounded-full transition-all duration-150 ${
                  isActive ? activeIndicator : "bg-transparent"
                }`}
              />
              <Icon className="h-5 w-5 shrink-0" />
              <span className="text-[10px] font-semibold tracking-wide leading-none">
                {item.label}
              </span>
            </Link>
          );
        })}

        {/* More button — opens sidebar */}
        <button
          type="button"
          onClick={onMenuOpen}
          className={`flex-1 flex flex-col items-center justify-center gap-1 transition-colors duration-150 cursor-pointer ${inactiveText}`}
          aria-label="Open full navigation"
        >
          <Menu className="h-5 w-5 shrink-0" />
          <span className="text-[10px] font-semibold tracking-wide leading-none">More</span>
        </button>
      </div>
    </nav>
  );
}
