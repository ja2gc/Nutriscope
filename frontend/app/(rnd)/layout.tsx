"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/contexts/AuthContext";
import { Sidebar } from "@/components/layout/Sidebar";
import { TopBar } from "@/components/layout/TopBar";
import { applyPreferences } from "@/lib/preferences";

export default function RndLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { user, initializing } = useAuth();
  const router = useRouter();
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Apply saved local UX preferences (density, reduced motion) on load.
  useEffect(() => { applyPreferences(); }, []);

  // Runs on every mount and whenever auth state changes
  useEffect(() => {
    if (!initializing && !user) {
      router.replace("/login");
    }
  }, [user, initializing, router]);

  // Fallback: if still initializing after 6 s (e.g. hung fetch), force redirect
  useEffect(() => {
    if (!initializing) return;
    const t = setTimeout(() => router.replace("/login"), 6000);
    return () => clearTimeout(t);
  }, [initializing, router]);

  // Handle bfcache restore — redirect immediately if user is null
  useEffect(() => {
    const handlePageShow = (e: PageTransitionEvent) => {
      if (e.persisted && !user) {
        router.replace("/login");
      }
    };
    window.addEventListener("pageshow", handlePageShow);
    return () => window.removeEventListener("pageshow", handlePageShow);
  }, [user, router]);

  if (initializing) {
    return (
      <div className="flex h-screen w-screen items-center justify-center bg-gray-50 font-sans select-none">
        <div className="text-center space-y-3">
          <svg 
            className="animate-spin h-8 w-8 text-blue-600 mx-auto" 
            fill="none" 
            viewBox="0 0 24 24"
          >
            <circle 
              className="opacity-25" 
              cx="12" 
              cy="12" 
              r="10" 
              stroke="currentColor" 
              strokeWidth="4"
            />
            <path 
              className="opacity-75" 
              fill="currentColor" 
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          <div className="text-xs font-semibold text-gray-500 uppercase tracking-widest">
            Loading System Context...
          </div>
        </div>
      </div>
    );
  }

  if (!user) {
    return null;
  }

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50 font-sans">
      {/* Persistent Left Sidebar */}
      <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      {/* Main Layout Area */}
      <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
        {/* Global Context Header */}
        <TopBar onMenuClick={() => setSidebarOpen(true)} />

        {/* Scrollable Content Canvas */}
        <main className="flex-1 overflow-y-auto overflow-x-hidden p-6 lg:p-8">
          <div className="max-w-7xl mx-auto w-full min-w-0">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
