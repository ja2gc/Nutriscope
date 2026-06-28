"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Logo } from "@/components/ui/Logo";
import { getCycleStepHref, getPlaceholderStepHref, type NcpStep } from "@/lib/ncpWorkflow";
import {
  Compass,
  CookingPot,
  HeartHandshake,
  Salad,
  TrendingUp,
  BellDot,
  Sliders,
  ChevronLeft,
  ChevronDown,
  Users,
  History,
  Megaphone,
  WalletCards
} from "lucide-react";

export function Sidebar({ open = false, onClose }: { open?: boolean; onClose?: () => void }) {
  const pathname = usePathname();
  const { user } = useAuth();
  const [collapsed, setCollapsed] = useState(false);

  const [ncpExpanded, setNcpExpanded] = useState(false);
  const [foodServiceExpanded, setFoodServiceExpanded] = useState(false);

  const ncpMatch = pathname.match(/^\/ncp\/([^\/]+)\/(assessment|diagnosis|intervention|monitoring)\/([^\/]+)/);
  const activePatientId = ncpMatch ? ncpMatch[1] : null;
  const activeNcpId = ncpMatch ? ncpMatch[3] : null;

  const hasRealNcpCycle = Boolean(
    activePatientId &&
    activeNcpId &&
    activePatientId !== "select-patient" &&
    activeNcpId !== "select-ncp"
  );
  const stepHref = (step: NcpStep) => hasRealNcpCycle
    ? getCycleStepHref(activePatientId!, step, activeNcpId!)
    : getPlaceholderStepHref(step);

  useEffect(() => {
    if (pathname.startsWith("/ncp")) {
      setNcpExpanded(true);
      setCollapsed(false);
    }
    if (pathname.startsWith("/food-service")) {
      setFoodServiceExpanded(true);
      setCollapsed(false);
    }
  }, [pathname]);

  const toggleNcp = () => {
    if (collapsed) {
      setCollapsed(false);
      setNcpExpanded(true);
    } else {
      setNcpExpanded(!ncpExpanded);
    }
  };

  const toggleFoodService = () => {
    if (collapsed) {
      setCollapsed(false);
      setFoodServiceExpanded(true);
    } else {
      setFoodServiceExpanded(!foodServiceExpanded);
    }
  };

  const isNcpRouteActive = pathname.startsWith("/ncp");
  const isFoodServiceRouteActive = pathname.startsWith("/food-service");
  const isNcpGroupActive = isNcpRouteActive;
  const isFoodServiceGroupActive = isFoodServiceRouteActive;
  const isNcpMenuOpen = ncpExpanded || isNcpRouteActive;
  const isFoodServiceMenuOpen = foodServiceExpanded || isFoodServiceRouteActive;

  const navLink = (href: string, exact: boolean, label: string, Icon: React.ElementType) => {
    const active = exact ? pathname === href : pathname.startsWith(href);
    return (
      <Link
        href={href}
        className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
          active
            ? "bg-white/10 text-white border-l-2 border-brand-green-400"
            : "text-warm-400 hover:bg-white/5 hover:text-white"
        }`}
        title={collapsed ? label : undefined}
      >
        <Icon className={`h-4.5 w-4.5 shrink-0 ${active ? "text-brand-green-400" : "text-warm-500"}`} />
        {!collapsed && <span>{label}</span>}
      </Link>
    );
  };

  return (
    <>
      {/* Mobile backdrop */}
      <div
        className={`md:hidden fixed inset-0 z-40 bg-black/50 transition-opacity duration-200 ${
          open ? "opacity-100 pointer-events-auto" : "opacity-0 pointer-events-none"
        }`}
        onClick={onClose}
      />
      <aside
        className={`bg-forest-900 border-r border-forest-line flex flex-col transition-all duration-200 ease-in-out select-none
          fixed inset-y-0 left-0 z-50 w-64
          md:relative md:inset-auto md:z-auto md:min-h-screen md:shrink-0
          ${collapsed ? "md:w-16" : "md:w-64"}
          ${open ? "translate-x-0" : "-translate-x-full md:translate-x-0"}
        `}
      >
        {/* Brand Header */}
        <div className={`h-14 border-b border-forest-line flex items-center px-4.5 ${collapsed ? "justify-center" : "justify-between"}`}>
          {collapsed ? (
            <>
              <button
                onClick={() => setCollapsed(false)}
                className="hidden md:flex items-center justify-center p-1.5 hover:bg-white/10 rounded-lg cursor-pointer transition-colors"
                title="Expand sidebar"
              >
                <Logo variant="dark" collapsed={true} />
              </button>
              <div className="md:hidden">
                <Logo variant="dark" collapsed={false} />
              </div>
            </>
          ) : (
            <>
              <Logo variant="dark" collapsed={false} />
              <button
                onClick={() => setCollapsed(true)}
                className="hidden md:block p-1 hover:bg-white/10 rounded text-white/30 hover:text-white/70 cursor-pointer"
                title="Collapse sidebar"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
            </>
          )}
        </div>

        {/* Nav List */}
        <nav className="flex-1 py-4.5 space-y-1.5 px-3 overflow-y-auto">
          {user?.role === "Admin" ? (
            /* ================= ADMIN SIDENAV ================= */
            <>
              {navLink("/admin/dashboard", true,  "Admin Dashboard",  Compass)}
              {navLink("/admin/announcements", false, "Announcements", Megaphone)}
              {navLink("/admin/users",         false, "Manage Users",  Users)}
              {navLink("/admin/reports",       false, "Reports",       TrendingUp)}
              {navLink("/admin/budget",        false, "Budget",        WalletCards)}
              {navLink("/admin/audit-logs",    false, "Audit Logs",    History)}
              {navLink("/admin/settings",      false, "System Settings", Sliders)}
            </>
          ) : (
            /* ================= RND CLINICAL SIDENAV ================= */
            <>
              {navLink("/dashboard",     true,  "Dashboard",    Compass)}
              {navLink("/announcements", false, "Announcements", Megaphone)}
              {navLink("/food-library",  false, "Food Library",  CookingPot)}

              {/* NCP Collapsible Accordion */}
              <div className="space-y-1">
                <button
                  onClick={toggleNcp}
                  className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 cursor-pointer ${
                    isNcpGroupActive
                      ? "bg-white/8 text-white font-bold border-l-2 border-brand-green-400"
                      : "text-warm-400 hover:bg-white/5 hover:text-white"
                  }`}
                  title={collapsed ? "Nutrition Care Process" : undefined}
                >
                  <div className="flex items-center gap-3">
                    <HeartHandshake className={`h-4.5 w-4.5 shrink-0 ${isNcpGroupActive ? "text-brand-green-400" : "text-warm-500"}`} />
                    {!collapsed && <span>Nutrition Care</span>}
                  </div>
                  {!collapsed && (
                    <ChevronDown
                      className={`h-3.5 w-3.5 text-warm-500 transition-transform duration-250 ease-in-out shrink-0 ${
                        isNcpMenuOpen ? "transform rotate-180" : ""
                      }`}
                    />
                  )}
                </button>

                <div
                  className={`transition-all duration-200 ease-in-out overflow-hidden pl-7.5 space-y-1 ${
                    isNcpMenuOpen && !collapsed ? "max-h-60 opacity-100 py-1" : "max-h-0 opacity-0 pointer-events-none"
                  }`}
                >
                  {[
                    { href: "/ncp/patients", label: "Patients", match: (p: string) => p === "/ncp/patients" || p.startsWith("/ncp/patients/") },
                    { href: stepHref("assessment"),   label: "Assessment",   match: (p: string) => p.includes("/assessment/") },
                    { href: stepHref("diagnosis"),    label: "Diagnosis",    match: (p: string) => p.includes("/diagnosis/") },
                    { href: stepHref("intervention"), label: "Intervention", match: (p: string) => p.includes("/intervention/") },
                    { href: stepHref("monitoring"),   label: "Monitoring",   match: (p: string) => p.includes("/monitoring/") },
                  ].map(({ href, label, match }) => {
                    const active = match(pathname);
                    return (
                      <Link
                        key={label}
                        href={href}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                          active ? "text-brand-green-300 font-extrabold" : "text-warm-500 hover:text-warm-300"
                        }`}
                      >
                        <span className={`h-1.5 w-1.5 rounded-full ${active ? "bg-brand-green-400" : "bg-forest-700"}`} />
                        {label}
                      </Link>
                    );
                  })}
                </div>
              </div>

              {/* Food Service Collapsible Accordion */}
              <div className="space-y-1">
                <button
                  onClick={toggleFoodService}
                  className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 cursor-pointer ${
                    isFoodServiceGroupActive
                      ? "bg-white/8 text-white font-bold border-l-2 border-brand-green-400"
                      : "text-warm-400 hover:bg-white/5 hover:text-white"
                  }`}
                  title={collapsed ? "Food Service" : undefined}
                >
                  <div className="flex items-center gap-3">
                    <Salad className={`h-4.5 w-4.5 shrink-0 ${isFoodServiceGroupActive ? "text-brand-green-400" : "text-warm-500"}`} />
                    {!collapsed && <span>Food Service</span>}
                  </div>
                  {!collapsed && (
                    <ChevronDown
                      className={`h-3.5 w-3.5 text-warm-500 transition-transform duration-250 ease-in-out shrink-0 ${
                        isFoodServiceMenuOpen ? "transform rotate-180" : ""
                      }`}
                    />
                  )}
                </button>

                <div
                  className={`transition-all duration-200 ease-in-out overflow-hidden pl-7.5 space-y-1 ${
                    isFoodServiceMenuOpen && !collapsed ? "max-h-96 opacity-100 py-1" : "max-h-0 opacity-0 pointer-events-none"
                  }`}
                >
                  {/* Inventory is a reference catalog — not listed in food-service nav */}
                  {[
                    { href: "/food-service/menu-cycle",   label: "Menu Cycle",   match: (p: string) => p === "/food-service/menu-cycle" },
                    { href: "/food-service/budget",       label: "Budget",       match: (p: string) => p === "/food-service/budget" },
                    { href: "/food-service/procurement",  label: "Procurement",  match: (p: string) => p === "/food-service/procurement" },
                    { href: "/food-service/foods",        label: "Foods",        match: (p: string) => p.startsWith("/food-service/foods") || p.startsWith("/food-service/recipes") },
                  ].map(({ href, label, match }) => {
                    const active = match(pathname);
                    return (
                      <Link
                        key={label}
                        href={href}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                          active ? "text-brand-green-300 font-extrabold" : "text-warm-500 hover:text-warm-300"
                        }`}
                      >
                        <span className={`h-1.5 w-1.5 rounded-full ${active ? "bg-brand-green-400" : "bg-forest-700"}`} />
                        {label}
                      </Link>
                    );
                  })}
                </div>
              </div>

              {navLink("/reports",       false, "Reports",          TrendingUp)}
              {navLink("/notifications", false, "Notifications",    BellDot)}
              {navLink("/settings",      false, "System Settings",  Sliders)}
            </>
          )}
        </nav>

        {/* Active User Session Footer */}
        {!collapsed && user && (
          <div className="p-4 border-t border-forest-line bg-white/5 text-center shrink-0">
            <span className="text-[10px] text-white/40 font-bold uppercase tracking-wider block">
              Active Session
            </span>
            <span className="text-xs font-semibold text-white/80 mt-1 block truncate" title={user.name}>
              {user.name}
            </span>
            <span className="text-[9px] font-extrabold text-orange-400 uppercase tracking-widest block mt-0.5">
              {user.role}
            </span>
          </div>
        )}
      </aside>
    </>
  );
}
