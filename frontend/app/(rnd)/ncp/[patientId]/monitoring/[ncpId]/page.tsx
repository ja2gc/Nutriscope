"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Activity, User, Calendar, Lock } from "lucide-react";
import EncounterLog from "./_components/EncounterLog";
import GoalProgressTracker from "./_components/GoalProgressTracker";
import LogVisitForm from "./_components/LogVisitForm";
import VisitTrendsChart from "./_components/VisitTrendsChart";
import MonitoringSummaryCard from "./_components/MonitoringSummaryCard";
import CarePlanHeader from "./_components/CarePlanHeader";
import {
  MonitoringEntry,
  MonitoringPayload,
  MonitoringPlan,
  fetchMonitorings,
  fetchMonitoringPlan,
  createMonitoring,
  deleteMonitoring,
} from "@/services/monitoringService";
import { fetchAssessment, Assessment } from "@/services/assessmentService";
import { fetchIntervention, Intervention } from "@/services/interventionService";
import { fetchDiagnoses } from "@/services/diagnosisService";
import { fetchPatientById, type Patient } from "@/services/patientService";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";

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
    <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none flex-wrap">
      <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">
        Directory
      </Link>
      <span className="text-warm-300">/</span>
      <span className="font-bold text-warm-600">NCP Cycle</span>
      <span className="text-warm-300">/</span>
      <span className="font-bold text-warm-600">Monitoring & Evaluation</span>
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
  const [patient, setPatient]               = useState<Patient | null>(null);
  const [biochemicalData, setBiochemicalData] = useState<BiochemicalData | null>(null);
  const [plan, setPlan]                     = useState<MonitoringPlan | null>(null);
  const [loading, setLoading]               = useState(true);
  const [showForm, setShowForm]             = useState(false);
  const [activeTab, setActiveTab]           = useState<Tab>("log");
  const [error, setError]                   = useState<string | null>(null);
  const [workflowBlock, setWorkflowBlock]   = useState<string | null>(null);
  const [historyPage, setHistoryPage]       = useState(1);
  const [historyMeta, setHistoryMeta]       = useState<PaginationMeta | null>(null);

  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    setLoading(true);
    setError(null);
    try {
      const [patientData, monitoringData, assessmentData, diagnosisData, interventionData, planData] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchMonitorings(ncpId, historyPage),
        fetchAssessment(ncpId),
        fetchDiagnoses(ncpId),
        fetchIntervention(ncpId),
        fetchMonitoringPlan(ncpId).catch(() => null),
      ]);
      if (patientData.status === "fulfilled") setPatient(patientData.value);
      if (monitoringData.status === "fulfilled") {
        setEntries(monitoringData.value.data);
        setHistoryMeta(monitoringData.value.meta);
      }
      if (assessmentData.status === "fulfilled") {
        const a = assessmentData.value as AssessmentWithLabs;
        setAssessment(a);
        setBiochemicalData(a.biochemical_data ?? null);
      }
      const hasAssessment = assessmentData.status === "fulfilled";
      const hasDiagnosis = diagnosisData.status === "fulfilled" && diagnosisData.value.length > 0;
      const loadedIntervention = interventionData.status === "fulfilled" ? interventionData.value : null;
      setIntervention(loadedIntervention);
      setPlan(planData.status === "fulfilled" ? planData.value : null);

      if (!hasAssessment) {
        setWorkflowBlock("Save the assessment before monitoring can begin.");
      } else if (!hasDiagnosis) {
        setWorkflowBlock("Save at least one diagnosis before monitoring can begin.");
      } else if (!loadedIntervention) {
        setWorkflowBlock("Monitoring starts on follow-up or second visit after the care plan is saved.");
      } else {
        setWorkflowBlock(null);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load monitoring data.");
    } finally {
      setLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder, historyPage]);

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
        <div className="bg-white border border-warm-200 rounded-2xl p-10 sm:p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
            <User className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-800 mt-4 uppercase tracking-wider">
            No Patient Selected
          </h3>
          <p className="text-sm text-warm-500 mt-2 leading-relaxed">
            Please navigate to the NCP Patients directory and select a patient to start or
            continue their Nutrition Care Process.
          </p>
          <div className="mt-6">
            <Link
              href="/ncp/patients"
              className="inline-flex px-4 py-2.5 bg-forest-900 hover:bg-forest-900 active:bg-black text-white text-sm font-bold uppercase tracking-wider rounded-lg transition-colors select-none"
            >
              Go to Patients Directory
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (!loading && workflowBlock) {
    const nextHref = !assessment
      ? `/ncp/${patientId}/assessment/${ncpId}`
      : workflowBlock.includes("diagnosis")
        ? `/ncp/${patientId}/diagnosis/${ncpId}`
        : `/ncp/${patientId}/intervention/${ncpId}`;

    return (
      <div className="space-y-6 font-sans">
        <Breadcrumb />
        <NcpPatientHeader
          patient={patient}
          patientId={patientId}
          ncpId={ncpId}
          stepLabel="Monitoring"
        />
        <div className="bg-white border border-warm-200 rounded-2xl p-10 sm:p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
            <Lock className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-800 mt-4 uppercase tracking-wider">
            Monitoring Begins On Follow-up
          </h3>
          <p className="text-sm text-warm-500 mt-2 leading-relaxed">
            {workflowBlock} Initial visits build the Assessment, Diagnosis, and Intervention care plan; monitoring records the next visit response.
          </p>
          <Link
            href={nextHref}
            className="inline-flex mt-6 px-4 py-2.5 bg-forest-900 hover:bg-forest-900 active:bg-black text-white text-sm font-bold uppercase tracking-wider rounded-lg transition-colors select-none"
          >
            Continue Care Plan
          </Link>
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
    `px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 whitespace-nowrap transition-colors cursor-pointer ${
      activeTab === tab
        ? "border-emerald-600 text-emerald-700"
        : "border-transparent text-warm-400 hover:text-warm-600"
    }`;

  return (
    <div className="space-y-6 font-sans">
      <Breadcrumb />
      <NcpPatientHeader
        patient={patient}
        patientId={patientId}
        ncpId={ncpId}
        stepLabel="Monitoring"
      />

      {/* ── Page header ─────────────────────────────────────────────────────── */}
      <div className="border-b border-warm-200 pb-5">
        <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
          <Activity className="h-5 w-5 text-emerald-600 shrink-0" />
          Step 4: Nutrition Monitoring & Evaluation
        </h2>
        <p className="text-sm text-warm-500 mt-1 select-none">
          Track clinical indices trends, gauge patient goal achievement, and schedule follow-up dates.
        </p>
      </div>

      {/* ── Error banner ────────────────────────────────────────────────────── */}
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
          {error}
        </div>
      )}

      {/* ── Loading skeleton ─────────────────────────────────────────────────── */}
      {loading ? (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <div className="h-10 bg-warm-100 rounded-xl animate-pulse" />
            <div className="h-48 bg-warm-100 rounded-2xl animate-pulse" />
            <div className="h-64 bg-warm-100 rounded-2xl animate-pulse" />
          </div>
          <div className="space-y-5">
            <div className="h-40 bg-warm-100 rounded-2xl animate-pulse" />
            <div className="h-24 bg-warm-100 rounded-2xl animate-pulse" />
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

          {/* ── Main column (2/3 on lg+) ───────────────────────────────────── */}
          <div className="lg:col-span-2 space-y-0">

            {/* ── Sticky tab bar ─────────────────────────────────────────── */}
            <div className="flex border-b border-warm-200 sticky top-0 z-20 bg-white -mx-6 px-6 lg:-mx-8 lg:px-8 mb-6">
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
                <Pagination meta={historyMeta} page={historyPage} onPageChange={setHistoryPage} />

                {showForm && (
                  <LogVisitForm
                    plan={plan}
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
                <CarePlanHeader plan={plan} />
                <MonitoringSummaryCard ncpId={ncpId} visitCount={historyMeta?.total ?? entries.length} />
                <GoalProgressTracker
                  plan={plan}
                  entries={entries}
                  baselineWeight={assessment?.weight ? Number(assessment.weight) : null}
                  baselineBmi={assessment?.bmi ? Number(assessment.bmi) : null}
                  baselineLabs={biochemicalData}
                  nutritionalStatus={assessment?.nutritional_status ?? null}
                />
                <VisitTrendsChart plan={plan} entries={entries} intervention={intervention} />
              </div>
            )}
          </div>

          {/* ── Sidebar (1/3 on lg+, full-width below lg) ─────────────────── */}
          <div className="space-y-5">
            {/* Encounter details card */}
            <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
              <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider mb-4">
                Encounter Details
              </h3>
              <div className="space-y-0 text-sm divide-y divide-zinc-100">
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-warm-400">Patient ID</span>
                  <span className="font-mono font-bold text-warm-900">{patientId}</span>
                </div>
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-warm-400">NCP Cycle</span>
                  <span className="font-mono font-bold text-warm-900">{ncpId}</span>
                </div>
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-warm-400">Total Visits</span>
                  <span className="font-mono font-bold text-warm-900">{entries.length}</span>
                </div>
                {intervention?.goal_type && (
                  <div className="flex justify-between py-2.5 gap-3">
                    <span className="font-semibold text-warm-400 shrink-0">Goal</span>
                    <span className="font-semibold text-warm-700 text-right capitalize leading-tight">
                      {intervention.goal_type.replace(/_/g, " ")}
                    </span>
                  </div>
                )}
                {assessment?.nutritional_status && (
                  <div className="flex justify-between py-2.5 gap-3">
                    <span className="font-semibold text-warm-400 shrink-0">Baseline Status</span>
                    <span className="font-semibold text-warm-700 text-right leading-tight">
                      {assessment.nutritional_status}
                    </span>
                  </div>
                )}
                {assessment?.weight && (
                  <div className="flex justify-between py-2.5">
                    <span className="font-semibold text-warm-400">Baseline Weight</span>
                    <span className="font-mono font-bold text-warm-900">
                      {assessment.weight} kg
                    </span>
                  </div>
                )}
                {assessment?.bmi && (
                  <div className="flex justify-between py-2.5">
                    <span className="font-semibold text-warm-400">Baseline BMI</span>
                    <span className="font-mono font-bold text-warm-900">{assessment.bmi}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Next follow-up card — only shown when a next date has been set */}
            {latestNextDate && (
              <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                  <Calendar className="h-3.5 w-3.5 text-emerald-600" />
                  Next Follow-up
                </h3>
                <p className="text-base font-mono font-bold text-warm-900">
                  {new Date(latestNextDate).toLocaleDateString("en-PH", {
                    weekday: "short",
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                  })}
                </p>
                <p className="text-xs text-warm-400 mt-1">Scheduled from last visit log.</p>
              </div>
            )}

            {/* Prescription summary card — only shown if intervention exists */}
            {intervention && (intervention.energy_kcal || intervention.protein_g) && (
              <div className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider mb-4">
                  Prescription Targets
                </h3>
                <div className="space-y-0 text-sm divide-y divide-zinc-100">
                  {intervention.energy_kcal && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Energy</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.energy_kcal} kcal
                      </span>
                    </div>
                  )}
                  {intervention.protein_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Protein</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.protein_g} g
                      </span>
                    </div>
                  )}
                  {intervention.carbs_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Carbs</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.carbs_g} g
                      </span>
                    </div>
                  )}
                  {intervention.fat_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Fat</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.fat_g} g
                      </span>
                    </div>
                  )}
                  {intervention.fluid_ml && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Fluid</span>
                      <span className="font-mono font-bold text-warm-900">
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
