"use client";

import React, { useState, useEffect } from "react";
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
  BellDot,
  Sliders,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  Users,
  FileText,
  History,
  Megaphone
} from "lucide-react";

export function Sidebar({ open = false, onClose }: { open?: boolean; onClose?: () => void }) {
  const pathname = usePathname();
  const { user } = useAuth();
  const [collapsed, setCollapsed] = useState(false);

  // States for collapsible groups
  const [ncpExpanded, setNcpExpanded] = useState(false);
  const [foodServiceExpanded, setFoodServiceExpanded] = useState(false);

  // Parse patientId and ncpId from current URL if on an NCP child step
  const ncpMatch = pathname.match(/^\/ncp\/([^\/]+)\/(assessment|diagnosis|intervention|monitoring)\/([^\/]+)/);
  const activePatientId = ncpMatch ? ncpMatch[1] : null;
  const activeNcpId = ncpMatch ? ncpMatch[3] : null;

  // NCP step links only resolve to a real step page when we're already inside an
  // NCP cycle; otherwise they'd point at /ncp/select-patient/... (a dead end), so
  // send the user to the patient picker to choose a patient + cycle first.
  const inNcp = Boolean(activePatientId && activeNcpId);
  const stepHref = (step: string) => inNcp ? `/ncp/${activePatientId}/${step}/${activeNcpId}` : "/ncp/patients";

  // Automatically expand relevant dropdown groups on mount if we are on their routes
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

  // Expand parent sidebar automatically if user opens a dropdown group
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

  // Check active states
  const isNcpRouteActive = pathname.startsWith("/ncp");
  const isFoodServiceRouteActive = pathname.startsWith("/food-service");
  const isNcpGroupActive = isNcpRouteActive;
  const isFoodServiceGroupActive = isFoodServiceRouteActive;
  const isNcpMenuOpen = ncpExpanded || isNcpRouteActive;
  const isFoodServiceMenuOpen = foodServiceExpanded || isFoodServiceRouteActive;

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
      className={`bg-zinc-950 border-r border-zinc-900 flex flex-col transition-all duration-200 ease-in-out select-none
        fixed inset-y-0 left-0 z-50 w-64
        md:relative md:inset-auto md:z-auto md:min-h-screen md:shrink-0
        ${collapsed ? "md:w-16" : "md:w-64"}
        ${open ? "translate-x-0" : "-translate-x-full md:translate-x-0"}
      `}
    >
      {/* Brand Header */}
      <div className="h-14 border-b border-zinc-900 flex items-center justify-between px-4.5">
        <Logo variant="dark" collapsed={collapsed} />
        
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="hidden md:block p-1 hover:bg-zinc-900 rounded text-zinc-500 hover:text-zinc-300 cursor-pointer"
          title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
        >
          {collapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
        </button>
      </div>

      {/* Nav List */}
      <nav className="flex-1 py-4.5 space-y-1.5 px-3 overflow-y-auto">
        {user?.role === "Admin" ? (
          /* ================= ADMIN SIDENAV ================= */
          <>
            <Link
              href="/admin/dashboard"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname === "/admin/dashboard"
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Admin Dashboard" : undefined}
            >
              <Compass className={`h-4.5 w-4.5 shrink-0 ${pathname === "/admin/dashboard" ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Admin Dashboard</span>}
            </Link>

            <Link
              href="/admin/users"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/admin/users")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Manage Users" : undefined}
            >
              <Users className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/admin/users") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Manage Users</span>}
            </Link>

            <Link
              href="/admin/reports"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/admin/reports")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Reports" : undefined}
            >
              <TrendingUp className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/admin/reports") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Reports</span>}
            </Link>

            <Link
              href="/admin/audit-logs"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/admin/audit-logs")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "System Audit Logs" : undefined}
            >
              <History className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/admin/audit-logs") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Audit Logs</span>}
            </Link>

            <Link
              href="/admin/announcements"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/admin/announcements")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Announcements" : undefined}
            >
              <Megaphone className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/admin/announcements") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Announcements</span>}
            </Link>

            <Link
              href="/admin/settings"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/admin/settings")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "System Settings" : undefined}
            >
              <Sliders className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/admin/settings") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>System Settings</span>}
            </Link>
          </>
        ) : (
          /* ================= RND CLINICAL SIDENAV ================= */
          <>
            <Link
              href="/dashboard"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname === "/dashboard"
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Dashboard" : undefined}
            >
              <Compass className={`h-4.5 w-4.5 shrink-0 ${pathname === "/dashboard" ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Dashboard</span>}
            </Link>

            <Link
              href="/food-library"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/food-library")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Food Library" : undefined}
            >
              <CookingPot className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/food-library") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Food Library</span>}
            </Link>

            {/* NCP Collapsible Accordion Dropdown Group */}
            <div className="space-y-1">
              <button
                onClick={toggleNcp}
                className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 cursor-pointer ${
                  isNcpGroupActive
                    ? "bg-zinc-900/40 text-zinc-200 font-bold border-l-2 border-emerald-600"
                    : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
                }`}
                title={collapsed ? "Nutrition Care Process" : undefined}
              >
                <div className="flex items-center gap-3">
                  <HeartHandshake className={`h-4.5 w-4.5 shrink-0 ${isNcpGroupActive ? "text-emerald-500" : "text-zinc-400"}`} />
                  {!collapsed && <span>Nutrition Care</span>}
                </div>
                {!collapsed && (
                  <ChevronDown 
                    className={`h-3.5 w-3.5 text-zinc-500 transition-transform duration-250 ease-in-out shrink-0 ${
                      isNcpMenuOpen ? "transform rotate-180" : ""
                    }`} 
                  />
                )}
              </button>

              {/* Submenu container with micro-animations */}
              <div 
                className={`transition-all duration-200 ease-in-out overflow-hidden pl-7.5 space-y-1 ${
                  isNcpMenuOpen && !collapsed ? "max-h-60 opacity-100 py-1" : "max-h-0 opacity-0 pointer-events-none"
                }`}
              >
                <Link
                  href="/ncp/patients"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname === "/ncp/patients" || pathname.startsWith("/ncp/patients/")
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname.startsWith("/ncp/patients") ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Patients
                </Link>
                <Link
                  href={stepHref("assessment")}
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname.includes("/assessment/")
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname.includes("/assessment/") ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Assessment
                </Link>
                <Link
                  href={stepHref("diagnosis")}
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname.includes("/diagnosis/")
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname.includes("/diagnosis/") ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Diagnosis
                </Link>
                <Link
                  href={stepHref("intervention")}
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname.includes("/intervention/")
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname.includes("/intervention/") ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Intervention
                </Link>
                <Link
                  href={stepHref("monitoring")}
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname.includes("/monitoring/")
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname.includes("/monitoring/") ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Monitoring
                </Link>
              </div>
            </div>

            {/* Food Service Collapsible Accordion Dropdown Group */}
            <div className="space-y-1">
              <button
                onClick={toggleFoodService}
                className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 cursor-pointer ${
                  isFoodServiceGroupActive
                    ? "bg-zinc-900/40 text-zinc-200 font-bold border-l-2 border-emerald-600"
                    : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
                }`}
                title={collapsed ? "Food Service" : undefined}
              >
                <div className="flex items-center gap-3">
                  <Salad className={`h-4.5 w-4.5 shrink-0 ${isFoodServiceGroupActive ? "text-emerald-500" : "text-zinc-400"}`} />
                  {!collapsed && <span>Food Service</span>}
                </div>
                {!collapsed && (
                  <ChevronDown 
                    className={`h-3.5 w-3.5 text-zinc-500 transition-transform duration-250 ease-in-out shrink-0 ${
                      isFoodServiceMenuOpen ? "transform rotate-180" : ""
                    }`} 
                  />
                )}
              </button>

              {/* Submenu container */}
              <div
                className={`transition-all duration-200 ease-in-out overflow-hidden pl-7.5 space-y-1 ${
                  isFoodServiceMenuOpen && !collapsed ? "max-h-96 opacity-100 py-1" : "max-h-0 opacity-0 pointer-events-none"
                }`}
              >
                <Link
                  href="/food-service/inventory"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname === "/food-service/inventory"
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname === "/food-service/inventory" ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Inventory
                </Link>
                <Link
                  href="/food-service/menu-cycle"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname === "/food-service/menu-cycle"
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname === "/food-service/menu-cycle" ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Menu Cycle
                </Link>
                <Link
                  href="/food-service/budget"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname === "/food-service/budget"
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname === "/food-service/budget" ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Budget
                </Link>
                <Link
                  href="/food-service/procurement"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname === "/food-service/procurement"
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname === "/food-service/procurement" ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Procurement
                </Link>
                <Link
                  href="/food-service/foods"
                  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
                    pathname.startsWith("/food-service/foods") || pathname.startsWith("/food-service/recipes")
                      ? "text-emerald-500 font-extrabold"
                      : "text-zinc-500 hover:text-zinc-300"
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname.startsWith("/food-service/foods") || pathname.startsWith("/food-service/recipes") ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Foods
                </Link>
              </div>
            </div>

            <Link
              href="/reports"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/reports")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Reports" : undefined}
            >
              <TrendingUp className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/reports") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Reports</span>}
            </Link>

            <Link
              href="/announcements"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/announcements")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Announcements" : undefined}
            >
              <Megaphone className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/announcements") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Announcements</span>}
            </Link>

            <Link
              href="/notifications"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/notifications")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "Notifications" : undefined}
            >
              <BellDot className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/notifications") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>Notifications</span>}
            </Link>

            <Link
              href="/settings"
              className={`flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-semibold uppercase tracking-wider transition-all duration-150 ${
                pathname.startsWith("/settings")
                  ? "bg-zinc-900 text-zinc-100 border-l-2 border-emerald-600"
                  : "text-zinc-400 hover:bg-zinc-900/60 hover:text-zinc-200"
              }`}
              title={collapsed ? "System Settings" : undefined}
            >
              <Sliders className={`h-4.5 w-4.5 shrink-0 ${pathname.startsWith("/settings") ? "text-emerald-500" : "text-zinc-400"}`} />
              {!collapsed && <span>System Settings</span>}
            </Link>
          </>
        )}
      </nav>

      {/* Active User Session Footer */}
      {!collapsed && user && (
        <div className="p-4 border-t border-zinc-900 bg-zinc-900/20 text-center shrink-0">
          <span className="text-[10px] text-zinc-500 font-bold uppercase tracking-wider block">
            Active Session
          </span>
          <span className="text-xs font-semibold text-zinc-300 mt-1 block truncate" title={user.name}>
            {user.name}
          </span>
          <span className="text-[9px] font-extrabold text-orange-500 uppercase tracking-widest block mt-0.5">
            {user.role}
          </span>
        </div>
      )}
    </aside>
    </>
  );
}

