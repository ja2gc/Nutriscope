"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Activity, User, Calendar } from "lucide-react";
import EncounterLog from "./_components/EncounterLog";
import GoalProgressTracker from "./_components/GoalProgressTracker";
import LogVisitForm from "./_components/LogVisitForm";
import VisitTrendsChart from "./_components/VisitTrendsChart";
import {
  MonitoringEntry,
  MonitoringPayload,
  fetchMonitorings,
  createMonitoring,
  deleteMonitoring,
} from "@/services/monitoringService";
import { fetchAssessment, Assessment } from "@/services/assessmentService";
import { fetchIntervention, Intervention } from "@/services/interventionService";

// ─── Types ────────────────────────────────────────────────────────────────────

interface BiochemicalData {
  albumin?: number | null;
  hba1c?: number | null;
  ldl?: number | null;
  cholesterol?: number | null;
  creatinine?: number | null;
  potassium?: number | null;
  hemoglobin?: number | null;
  glucose?: number | null;
}

type AssessmentWithLabs = Assessment & { biochemical_data?: BiochemicalData };
type Tab = "log" | "progress";

// ─── Breadcrumb ───────────────────────────────────────────────────────────────

function Breadcrumb() {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none flex-wrap">
      <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">
        Directory
      </Link>
      <span className="text-zinc-300">/</span>
      <span className="font-bold text-zinc-600">NCP Cycle</span>
      <span className="text-zinc-300">/</span>
      <span className="font-bold text-zinc-600">Monitoring & Evaluation</span>
    </div>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function NcpMonitoringPage({
  params,
}: {
  params: Promise<{ patientId: string; ncpId: string }>;
}) {
  const { patientId, ncpId } = use(params);
  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [entries, setEntries]               = useState<MonitoringEntry[]>([]);
  const [assessment, setAssessment]         = useState<AssessmentWithLabs | null>(null);
  const [intervention, setIntervention]     = useState<Intervention | null>(null);
  const [biochemicalData, setBiochemicalData] = useState<BiochemicalData | null>(null);
  const [loading, setLoading]               = useState(true);
  const [showForm, setShowForm]             = useState(false);
  const [activeTab, setActiveTab]           = useState<Tab>("log");
  const [error, setError]                   = useState<string | null>(null);

  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    setLoading(true);
    setError(null);
    try {
      const [monitoringData, assessmentData, interventionData] = await Promise.all([
        fetchMonitorings(ncpId),
        fetchAssessment(ncpId),
        fetchIntervention(ncpId),
      ]);
      setEntries(monitoringData);
      const a = assessmentData as AssessmentWithLabs;
      setAssessment(a);
      setBiochemicalData(a.biochemical_data ?? null);
      setIntervention(interventionData);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load monitoring data.");
    } finally {
      setLoading(false);
    }
  }, [ncpId, isPlaceholder]);

  useEffect(() => { loadData(); }, [loadData]);

  async function handleLogVisit(payload: MonitoringPayload) {
    await createMonitoring(ncpId, payload);
    setShowForm(false);
    await loadData();
  }

  async function handleDeleteEntry(id: number) {
    await deleteMonitoring(ncpId, id);
    await loadData();
  }

  // ─── No patient selected ───────────────────────────────────────────────────
  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <Breadcrumb />
        <div className="bg-white border border-zinc-200 rounded-2xl p-10 sm:p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
            <User className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">
            No Patient Selected
          </h3>
          <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
            Please navigate to the NCP Patients directory and select a patient to start or
            continue their Nutrition Care Process.
          </p>
          <div className="mt-6">
            <Link
              href="/ncp/patients"
              className="inline-flex px-4 py-2.5 bg-zinc-950 hover:bg-zinc-900 active:bg-black text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors select-none"
            >
              Go to Patients Directory
            </Link>
          </div>
        </div>
      </div>
    );
  }

  // Latest next follow-up date (from most recent visit)
  const latestNextDate = [...entries]
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())[0]
    ?.next_monitoring_date ?? null;

  // Tab button class helper
  const tabCls = (tab: Tab) =>
    `px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider border-b-2 whitespace-nowrap transition-colors cursor-pointer ${
      activeTab === tab
        ? "border-emerald-600 text-emerald-700"
        : "border-transparent text-zinc-400 hover:text-zinc-600"
    }`;

  return (
    <div className="space-y-6 font-sans">
      <Breadcrumb />

      {/* ── Page header ─────────────────────────────────────────────────────── */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Activity className="h-5 w-5 text-emerald-600 shrink-0" />
          Step 4: Nutrition Monitoring & Evaluation
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Track clinical indices trends, gauge patient goal achievement, and schedule follow-up dates.
        </p>
      </div>

      {/* ── Error banner ────────────────────────────────────────────────────── */}
      {error && (
        <div className="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl px-4 py-3">
          {error}
        </div>
      )}

      {/* ── Loading skeleton ─────────────────────────────────────────────────── */}
      {loading ? (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <div className="h-10 bg-zinc-100 rounded-xl animate-pulse" />
            <div className="h-48 bg-zinc-100 rounded-2xl animate-pulse" />
            <div className="h-64 bg-zinc-100 rounded-2xl animate-pulse" />
          </div>
          <div className="space-y-5">
            <div className="h-40 bg-zinc-100 rounded-2xl animate-pulse" />
            <div className="h-24 bg-zinc-100 rounded-2xl animate-pulse" />
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

          {/* ── Main column (2/3 on lg+) ───────────────────────────────────── */}
          <div className="lg:col-span-2 space-y-0">

            {/* ── Sticky tab bar ─────────────────────────────────────────── */}
            <div className="flex border-b border-zinc-200 sticky top-0 z-20 bg-white -mx-6 px-6 lg:-mx-8 lg:px-8 mb-6">
              <button className={tabCls("log")} onClick={() => setActiveTab("log")}>
                Visit Log
              </button>
              <button className={tabCls("progress")} onClick={() => setActiveTab("progress")}>
                Progress Trends
              </button>
            </div>

            {/* ── Visit Log tab ─────────────────────────────────────────── */}
            {activeTab === "log" && (
              <div className="space-y-6">
                <EncounterLog
                  entries={entries}
                  onLogNew={() => setShowForm(true)}
                  onDelete={handleDeleteEntry}
                />

                {showForm && (
                  <LogVisitForm
                    heightCm={assessment?.height ? Number(assessment.height) : null}
                    intervention={intervention}
                    onSubmit={handleLogVisit}
                    onCancel={() => setShowForm(false)}
                  />
                )}
              </div>
            )}

            {/* ── Progress Trends tab ───────────────────────────────────── */}
            {activeTab === "progress" && (
              <div className="space-y-6">
                <GoalProgressTracker
                  entries={entries}
                  baselineWeight={assessment?.weight ? Number(assessment.weight) : null}
                  baselineBmi={assessment?.bmi ? Number(assessment.bmi) : null}
                  baselineLabs={biochemicalData}
                  nutritionalStatus={assessment?.nutritional_status ?? null}
                />
                <VisitTrendsChart entries={entries} intervention={intervention} />
              </div>
            )}
          </div>

          {/* ── Sidebar (1/3 on lg+, full-width below lg) ─────────────────── */}
          <div className="space-y-5">
            {/* Encounter details card */}
            <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
              <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">
                Encounter Details
              </h3>
              <div className="space-y-0 text-xs divide-y divide-zinc-100">
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-zinc-400">Patient ID</span>
                  <span className="font-mono font-bold text-zinc-900">{patientId}</span>
                </div>
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-zinc-400">NCP Cycle</span>
                  <span className="font-mono font-bold text-zinc-900">{ncpId}</span>
                </div>
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-zinc-400">Total Visits</span>
                  <span className="font-mono font-bold text-zinc-900">{entries.length}</span>
                </div>
                {intervention?.goal_type && (
                  <div className="flex justify-between py-2.5 gap-3">
                    <span className="font-semibold text-zinc-400 shrink-0">Goal</span>
                    <span className="font-semibold text-zinc-700 text-right capitalize leading-tight">
                      {intervention.goal_type.replace(/_/g, " ")}
                    </span>
                  </div>
                )}
                {assessment?.nutritional_status && (
                  <div className="flex justify-between py-2.5 gap-3">
                    <span className="font-semibold text-zinc-400 shrink-0">Baseline Status</span>
                    <span className="font-semibold text-zinc-700 text-right leading-tight">
                      {assessment.nutritional_status}
                    </span>
                  </div>
                )}
                {assessment?.weight && (
                  <div className="flex justify-between py-2.5">
                    <span className="font-semibold text-zinc-400">Baseline Weight</span>
                    <span className="font-mono font-bold text-zinc-900">
                      {assessment.weight} kg
                    </span>
                  </div>
                )}
                {assessment?.bmi && (
                  <div className="flex justify-between py-2.5">
                    <span className="font-semibold text-zinc-400">Baseline BMI</span>
                    <span className="font-mono font-bold text-zinc-900">{assessment.bmi}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Next follow-up card — only shown when a next date has been set */}
            {latestNextDate && (
              <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                  <Calendar className="h-3.5 w-3.5 text-emerald-600" />
                  Next Follow-up
                </h3>
                <p className="text-sm font-mono font-bold text-zinc-900">
                  {new Date(latestNextDate).toLocaleDateString("en-PH", {
                    weekday: "short",
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                  })}
                </p>
                <p className="text-[10px] text-zinc-400 mt-1">Scheduled from last visit log.</p>
              </div>
            )}

            {/* Prescription summary card — only shown if intervention exists */}
            {intervention && (intervention.energy_kcal || intervention.protein_g) && (
              <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">
                  Prescription Targets
                </h3>
                <div className="space-y-0 text-xs divide-y divide-zinc-100">
                  {intervention.energy_kcal && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-zinc-400">Energy</span>
                      <span className="font-mono font-bold text-zinc-900">
                        {intervention.energy_kcal} kcal
                      </span>
                    </div>
                  )}
                  {intervention.protein_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-zinc-400">Protein</span>
                      <span className="font-mono font-bold text-zinc-900">
                        {intervention.protein_g} g
                      </span>
                    </div>
                  )}
                  {intervention.carbs_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-zinc-400">Carbs</span>
                      <span className="font-mono font-bold text-zinc-900">
                        {intervention.carbs_g} g
                      </span>
                    </div>
                  )}
                  {intervention.fat_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-zinc-400">Fat</span>
                      <span className="font-mono font-bold text-zinc-900">
                        {intervention.fat_g} g
                      </span>
                    </div>
                  )}
                  {intervention.fluid_ml && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-zinc-400">Fluid</span>
                      <span className="font-mono font-bold text-zinc-900">
                        {intervention.fluid_ml} mL
                      </span>
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
