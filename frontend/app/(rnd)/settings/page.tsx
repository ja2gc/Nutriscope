"use client";

import React from "react";
import Link from "next/link";
import { Sliders, Settings } from "lucide-react";

export default function SettingsPage() {
  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">System Settings</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Sliders className="h-5 w-5 text-emerald-600 animate-pulse" />
          RND Preferences & System Settings
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Configure profile details, default clinical calculators, and regional metrics.
        </p>
      </div>

      {/* Body */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <Settings className="h-8 w-8 text-emerald-600" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">Settings Scaffold</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
          The upcoming settings panel will support configuring customized hospital constants and default units of measure.
        </p>
      </div>
    </div>
  );
}
