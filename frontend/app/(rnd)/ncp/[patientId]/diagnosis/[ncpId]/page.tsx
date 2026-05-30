"use client";

import React, { use } from "react";
import Link from "next/link";
import { Stethoscope, Sparkles, User, AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/Button";

export default function NcpDiagnosisPage({
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
        <span className="text-zinc-650 font-bold">Nutrition Diagnosis</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Stethoscope className="h-5 w-5 text-emerald-600 animate-pulse" />
          Step 2: Nutrition Diagnosis (PES)
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Identify and prioritize nutrition diagnoses using standard G-NCP domain categorizations (PES statement builder).
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
                <Stethoscope className="h-4.5 w-4.5 text-emerald-600" />
                PES Statement Builder
              </h3>
              <div className="space-y-4 text-xs text-zinc-500 leading-relaxed border-t border-zinc-100 pt-4">
                <p>
                  Allows full structured construction of G-NCP standardized nutrition diagnoses mapping:
                </p>
                <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl space-y-3.5 text-zinc-700">
                  <div>
                    <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">Standard Problem (P)</span>
                    <div className="px-3 py-2 bg-white border border-zinc-250 rounded-lg text-zinc-800 font-medium">Select standardized diagnosis...</div>
                  </div>
                  <div>
                    <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">Attributed Etiology (E)</span>
                    <div className="px-3 py-2 bg-white border border-zinc-250 rounded-lg text-zinc-800 font-medium">Enter etiology factor (related to)...</div>
                  </div>
                  <div>
                    <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">Signs & Symptoms (S)</span>
                    <div className="px-3 py-2 bg-white border border-zinc-250 rounded-lg text-zinc-800 font-medium">Enter signs and symptoms (as evidenced by)...</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
              <h3 className="text-xs font-extrabold text-zinc-900 uppercase tracking-wider mb-3">Diagnostic Domains</h3>
              <div className="space-y-2.5 pt-2">
                {[
                  { label: "Intake (NI)", desc: "Actual intake compared to needs" },
                  { label: "Clinical (NC)", desc: "Medical or physical conditions" },
                  { label: "Behavioral-Environmental (NB)", desc: "Knowledge, attitudes, and environment" }
                ].map((item, idx) => (
                  <div key={idx} className="text-xs border-b border-zinc-100 pb-2 last:border-0 last:pb-0">
                    <span className="font-bold text-emerald-800 block">{item.label}</span>
                    <span className="text-[10px] text-zinc-500">{item.desc}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 text-zinc-100 space-y-4">
              <div className="flex items-center gap-2 text-orange-500 font-bold text-xs uppercase tracking-wider">
                <Sparkles className="h-4 w-4 animate-spin shrink-0" />
                AI Suggestion Engine
              </div>
              <p className="text-[11px] text-zinc-400 leading-relaxed">
                The integrated clinical reasoning engine (Milestone 5) will analyze active biochemical flags, anthropometrics, and medical diagnoses to generate high-confidence draft PES statements.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
