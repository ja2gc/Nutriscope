"use client";

import React from "react";
import Link from "next/link";
import { Salad, Plus, Calendar } from "lucide-react";
import { Button } from "@/components/ui/Button";

export default function MenuCyclePage() {
  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-400">Food Service</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">Menu Cycles</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600 animate-pulse" />
            Therapeutic Menu Cycle Scheduler
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Schedule recurring multi-week menu cycles for patient ward feedings.
          </p>
        </div>
        <Button variant="primary" className="sm:w-auto px-4 py-2.5 shrink-0 flex items-center justify-center gap-2">
          <Plus className="h-4 w-4" />
          Create Menu Cycle
        </Button>
      </div>

      {/* Body */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <Calendar className="h-8 w-8 text-emerald-600" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">Menu Cycle Scaffolding</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
          The upcoming therapeutic scheduler (Milestone 9) will support custom meal-prep logging, automated macro validation against target tolerances, and ward distribution counts.
        </p>
      </div>
    </div>
  );
}
