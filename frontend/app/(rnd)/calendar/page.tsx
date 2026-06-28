"use client";

import React from "react";
import Link from "next/link";
import { CalendarDays, Calendar as CalendarIcon } from "lucide-react";

export default function CalendarPage() {
  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-warm-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-warm-300">/</span>
        <span className="text-zinc-650 font-bold">Calendar</span>
      </div>

      {/* Header */}
      <div className="border-b border-warm-200 pb-5">
        <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
          <CalendarDays className="h-5 w-5 text-emerald-600 animate-pulse" />
          RND Scheduling Calendar
        </h2>
        <p className="text-xs text-warm-500 mt-1 select-none">
          Track upcoming patient follow-ups, recheck cycles, and nutritional rounds.
        </p>
      </div>

      {/* Body */}
      <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
          <CalendarIcon className="h-8 w-8 text-emerald-600" />
        </div>
        <h3 className="text-sm font-bold text-warm-800 mt-4 uppercase tracking-wider">Clinical Calendar Scaffold</h3>
        <p className="text-xs text-warm-500 mt-2 leading-relaxed">
          The upcoming calendar dashboard will display chronologically sorted ward visitations and automate task assignments for RND teams.
        </p>
      </div>
    </div>
  );
}
