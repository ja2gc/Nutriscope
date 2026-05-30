"use client";

import React, { use } from "react";
import Link from "next/link";
import { Sparkles, User, Salad, Activity } from "lucide-react";
import { Button } from "@/components/ui/Button";

export default function NcpInterventionPage({
  params,
}: {
  params: Promise<{ patientId: string; ncpId: string }>;
}) {
  const resolvedParams = use(params);
  const { patientId, ncpId } = resolvedParams;

  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">NCP Cycle</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">Nutrition Intervention</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600 animate-pulse" />
          Step 3: Nutrition Intervention
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Formulate patient macro goals, prescribe therapeutic diet plans, and define nutrition education counseling sessions.
        </p>
      </div>

      {/* Body */}
      {isPlaceholder ? (
        <div className="bg-white border border-zinc-250 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
            <User className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
          <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
            Please navigate to the NCP Patients directory and select a patient to start or continue their Nutrition Care Process.
          </p>
          <div className="mt-6">
            <Link
              href="/ncp/patients"
              className="inline-flex px-4 py-2.5 bg-zinc-950 hover:bg-zinc-900 active:bg-black text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer select-none"
            >
              Go to Patients Directory
            </Link>
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
              <h3 className="text-sm font-bold text-zinc-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                <Salad className="h-4.5 w-4.5 text-emerald-600" />
                Intervention Formulation & Targets
              </h3>
              <div className="space-y-4 text-xs text-zinc-500 leading-relaxed border-t border-zinc-100 pt-4">
                <p>
                  Establishes the patient's daily macro and energy limits, customized micronutrient restrictions, and encounter sessions:
                </p>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-center">
                  <div className="bg-white border border-zinc-200 p-2.5 rounded-lg">
                    <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">Energy Target</span>
                    <span className="text-sm font-extrabold text-zinc-800">-- kcal</span>
                  </div>
                  <div className="bg-white border border-zinc-200 p-2.5 rounded-lg">
                    <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">Protein</span>
                    <span className="text-sm font-extrabold text-zinc-800">-- g</span>
                  </div>
                  <div className="bg-white border border-zinc-200 p-2.5 rounded-lg">
                    <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">Carbs</span>
                    <span className="text-sm font-extrabold text-zinc-800">-- g</span>
                  </div>
                  <div className="bg-white border border-zinc-200 p-2.5 rounded-lg">
                    <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">Fat</span>
                    <span className="text-sm font-extrabold text-zinc-800">-- g</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
              <h3 className="text-xs font-extrabold text-zinc-900 uppercase tracking-wider mb-3">Encounter Details</h3>
              <div className="space-y-2.5 pt-2 text-xs text-zinc-650">
                <div className="flex justify-between py-1 border-b border-zinc-100">
                  <span className="font-semibold">Patient:</span>
                  <span className="font-mono text-zinc-900 font-bold">{patientId}</span>
                </div>
                <div className="flex justify-between py-1">
                  <span className="font-semibold">NCP Cycle:</span>
                  <span className="font-mono text-zinc-900 font-bold">{ncpId}</span>
                </div>
              </div>
            </div>

            <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 text-zinc-100 space-y-4">
              <div className="flex items-center gap-2 text-orange-500 font-bold text-xs uppercase tracking-wider">
                <Sparkles className="h-4 w-4 animate-spin shrink-0" />
                Diet Recommendation
              </div>
              <p className="text-[11px] text-zinc-400 leading-relaxed">
                The automatic 7-day meal planning and menu scheduling engine (Milestone 6) will automatically cross-reference items with active disease rules and patient allergens.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
