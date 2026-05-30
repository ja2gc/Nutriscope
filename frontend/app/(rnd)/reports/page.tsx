"use client";

import React from "react";
import Link from "next/link";
import { TrendingUp, FileText } from "lucide-react";

export default function ReportsPage() {
  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">Reports Center</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <TrendingUp className="h-5 w-5 text-emerald-600 animate-pulse" />
          Clinical & Operational Reports Center
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Generate aggregate clinical charts, ADIME summaries, and census reports.
        </p>
      </div>

      {/* Body */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <FileText className="h-8 w-8 text-emerald-600" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">Reports Center Scaffold</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
          The upcoming DomPDF report compilation engine (Milestone 6) will generate professional, facility-compliant print-ready clinical reports, including patient NCP summaries and menu census analytics.
        </p>
      </div>
    </div>
  );
}
