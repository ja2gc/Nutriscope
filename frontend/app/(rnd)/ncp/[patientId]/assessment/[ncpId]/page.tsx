"use client";

import React, { use } from "react";
import Link from "next/link";
import { ClipboardCheck, Sparkles, User, FileImage } from "lucide-react";
import { Button } from "@/components/ui/Button";

export default function NcpAssessmentPage({
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
        <span className="text-zinc-650 font-bold">Nutrition Assessment</span>
      </div>

      {/* Header */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <ClipboardCheck className="h-5 w-5 text-emerald-600 animate-pulse" />
          Step 1: Nutrition Assessment
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Document patient history, dietary intake, anthropometric metrics, and biochemical values.
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
                <ClipboardCheck className="h-4.5 w-4.5 text-emerald-600" />
                Assessment Data Entry Portal
              </h3>
              <div className="space-y-4 text-xs text-zinc-500 leading-relaxed border-t border-zinc-100 pt-4">
                <p>
                  This portal handles full anthropometric inputs (weight, height, BMI calculations), dietary history logs, and social/referral contexts.
                </p>
                <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl space-y-2 text-zinc-700">
                  <div className="flex justify-between border-b border-zinc-200/60 pb-1.5 font-semibold">
                    <span>Patient Identifier:</span>
                    <span className="font-mono text-zinc-900">{patientId}</span>
                  </div>
                  <div className="flex justify-between font-semibold">
                    <span>NCP Cycle Identifier:</span>
                    <span className="font-mono text-zinc-900">{ncpId}</span>
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm text-center">
              <div className="p-3 bg-emerald-50 text-emerald-700 rounded-2xl w-fit mx-auto mb-4 border border-emerald-100">
                <FileImage className="h-6 w-6" />
              </div>
              <h4 className="text-xs font-extrabold text-zinc-900 uppercase tracking-wider">OCR Intake Screening Upload</h4>
              <p className="text-[11px] text-zinc-500 mt-2 max-w-md mx-auto leading-relaxed">
                PaddleOCR document extraction integration is slated for Milestone 4. You will be able to upload PDF or image scans to automatically extract screening checks and populate active diagnostic indices.
              </p>
              <Button variant="secondary" className="mt-4 text-[10px] uppercase font-bold tracking-wider cursor-not-allowed" disabled>
                Upload Screening PDF (Coming in M4)
              </Button>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
              <h3 className="text-xs font-extrabold text-zinc-900 uppercase tracking-wider mb-3">Workflow Lifecycle</h3>
              <div className="space-y-3.5 pt-2">
                {[
                  { label: "Assessment", active: true, step: "Step 1" },
                  { label: "Diagnosis (PES)", active: false, step: "Step 2" },
                  { label: "Intervention", active: false, step: "Step 3" },
                  { label: "Monitoring & Eval", active: false, step: "Step 4" }
                ].map((item, idx) => (
                  <div key={idx} className="flex items-center justify-between text-xs">
                    <span className={`font-semibold ${item.active ? "text-emerald-700 font-extrabold" : "text-zinc-400"}`}>
                      {item.label}
                    </span>
                    <span className={`px-2 py-0.5 rounded-lg text-[9px] font-extrabold ${item.active ? "bg-emerald-50 text-emerald-750 border border-emerald-100" : "bg-zinc-100 text-zinc-400"}`}>
                      {item.step}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-6 text-zinc-100 space-y-4">
              <div className="flex items-center gap-2 text-orange-500 font-bold text-xs uppercase tracking-wider">
                <Sparkles className="h-4 w-4 animate-spin shrink-0" />
                OCR Extraction Context
              </div>
              <p className="text-[11px] text-zinc-400 leading-relaxed">
                Active screening documents parsed by the OCR pipeline will display high-confidence suggestions alongside manual override fields inside this column.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
