"use client";

import React from "react";
import Link from "next/link";
import { Salad, Plus, TrendingUp } from "lucide-react";
import { Button } from "@/components/ui/Button";

export default function BudgetPage() {
  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700 transition-colors">Home</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-400">Food Service</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">Budget Operations</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600 animate-pulse" />
            Food Service Budget Control & Planning
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Monitor nutritional cost-per-person indexes and daily food logs compared to planned thresholds.
          </p>
        </div>
      </div>

      {/* Body */}
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <TrendingUp className="h-8 w-8 text-emerald-600" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">Budget Operations Scaffold</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
          The upcoming budget planner (Milestone 9) will analyze procurement daily log variances and show active over-budget alarms dynamically based on patient intake volume.
        </p>
      </div>
    </div>
  );
}
