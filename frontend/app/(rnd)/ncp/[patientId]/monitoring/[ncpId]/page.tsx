"use client";

import React, { use, useEffect, useState, useCallback, useRef } from "react";
import type { EditorLeaveHandle } from "@/hooks/useUnsavedChanges";
import Link from "next/link";
import EncounterLog from "./_components/EncounterLog";
import GoalProgressTracker from "./_components/GoalProgressTracker";
import LogVisitForm from "./_components/LogVisitForm";
import VisitTrendsChart from "./_components/VisitTrendsChart";
import CarePlanHeader from "./_components/CarePlanHeader";
import {
  MonitoringEntry,
  MonitoringContext,
  MonitoringPayload,
  MonitoringPlan,
  fetchMonitorings,
  fetchMonitoringPlan,
  fetchMonitoringContext,
  createMonitoring,
  updateMonitoring,
  deleteMonitoring,
} from "@/services/monitoringService";
import { fetchAssessment, Assessment } from "@/services/assessmentService";
import { fetchIntervention, Intervention } from "@/services/interventionService";
import { fetchDiagnoses } from "@/services/diagnosisService";
import { fetchPatientById, type Patient } from "@/services/patientService";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { NcpBreadcrumb } from "@/components/ncp/NcpBreadcrumb";
import { NcpSectionTabs } from "@/components/ncp/NcpSectionTabs";
import { DeleteActionDialog } from "@/components/ui/DeleteActionDialog";
import ButtonFilterGroup from "@/components/ui/ButtonFilterGroup";
import type { IndicatorCategory } from "@/services/monitoringPlan";
import { ALL_MICROS } from "@/lib/nutritionCalculations";

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

function formatCycleDate(value: string | null | undefined): string {
  if (!value) return "Not available";
  return new Date(value).toLocaleDateString("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatPrescriptionLimit(limit: { target?: number; min?: number; max?: number }): string | null {
  if (limit.target != null) return String(limit.target);
  if (limit.min != null && limit.max != null) return `${limit.min}–${limit.max}`;
  if (limit.max != null) return `≤${limit.max}`;
  if (limit.min != null) return `≥${limit.min}`;
  return null;
}

// ─── Breadcrumb ───────────────────────────────────────────────────────────────

function Breadcrumb() {
  return <NcpBreadcrumb step="Monitoring & Evaluation" />;
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
  const [context, setContext]               = useState<MonitoringContext | null>(null);
  const [loading, setLoading]               = useState(true);
  const [showForm, setShowForm]             = useState(false);
  const [selectedEntry, setSelectedEntry] = useState<MonitoringEntry | null>(null);
  const [deleteEntry, setDeleteEntry] = useState<MonitoringEntry | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const visitLeave = useRef<EditorLeaveHandle>(null);
  function leaveVisit(next: () => void) {
    if (visitLeave.current) visitLeave.current.requestLeave(next);
    else next();
  }
  const [activeTab, setActiveTab]           = useState<Tab>("log");
  const [category, setCategory] = useState<IndicatorCategory | "all">("all");
  const [error, setError]                   = useState<string | null>(null);
  const [workflowBlock, setWorkflowBlock]   = useState<string | null>(null);
  const [historyPage, setHistoryPage]       = useState(1);
  const [historyMeta, setHistoryMeta]       = useState<PaginationMeta | null>(null);

  const micronutrientTargets = intervention
    ? ALL_MICROS.flatMap((micro) => {
        const limit = intervention.micronutrient_limits?.[micro.key];
        const amount = limit ? formatPrescriptionLimit(limit) : null;
        return amount === null ? [] : [{ ...micro, amount, unit: limit?.unit || micro.unit }];
      })
    : [];

  const historyRequest = useRef(0);
  const contextRequest = useRef(0);
  const [loadFailed, setLoadFailed] = useState(false);
  const [historyError, setHistoryError] = useState<string | null>(null);
  const [historyLoading, setHistoryLoading] = useState(false);
  const loadData = useCallback(async (quiet = false) => {
    if (isPlaceholder) { setLoading(false); return; }
    if (!quiet) setLoading(true);
    setError(null);
    const request = ++contextRequest.current;
    const historyGeneration = ++historyRequest.current;
    setHistoryError(null);
    setLoadFailed(false);
    try {
      const [patientData, monitoringData, assessmentData, diagnosisData, interventionData, planData, contextData] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchMonitorings(ncpId, 1),
        fetchAssessment(ncpId),
        fetchDiagnoses(ncpId),
        fetchIntervention(ncpId),
        fetchMonitoringPlan(ncpId),
        fetchMonitoringContext(ncpId),
      ]);
      if (request !== contextRequest.current) return;
      if (quiet && [patientData, monitoringData, assessmentData, diagnosisData, interventionData, planData, contextData].some(result => result.status === "rejected")) {
        setError("Could not refresh monitoring. Your change was saved; reload to get the latest data.");
        return;
      }
      const requiredReadFailed = patientData.status === "rejected"
        || monitoringData.status === "rejected" || diagnosisData.status === "rejected"
        || interventionData.status === "rejected" || planData.status === "rejected" || contextData.status === "rejected"
        || (assessmentData.status === "rejected" && assessmentData.reason?.status !== 404);
      if (requiredReadFailed) {
        setLoadFailed(true);
        setError("Could not load monitoring and care context. Try again before logging a visit.");
        return;
      }
      if (patientData.status === "fulfilled") setPatient(patientData.value);
      if (monitoringData.status === "fulfilled" && historyGeneration === historyRequest.current) {
        setEntries(monitoringData.value.data);
        setHistoryMeta(monitoringData.value.meta);
        setHistoryPage(1);
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
      setContext(contextData.status === "fulfilled" ? contextData.value : null);

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
      if (request === contextRequest.current) setLoading(false);
      if (historyGeneration === historyRequest.current) setHistoryLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder]);

  useEffect(() => {
    const historyCounter = historyRequest;
    const contextCounter = contextRequest;
    void loadData();
    return () => { historyCounter.current++; contextCounter.current++; };
  }, [loadData]);

  async function changeHistoryPage(page: number) {
    const request = ++historyRequest.current;
    setHistoryLoading(true);
    setHistoryError(null);
    try {
      const result = await fetchMonitorings(ncpId, page);
      if (request !== historyRequest.current) return;
      setEntries(result.data); setHistoryMeta(result.meta); setHistoryPage(page);
    } catch {
      if (request === historyRequest.current) setHistoryError("Could not load visit history. Try the page again.");
    } finally {
      if (request === historyRequest.current) setHistoryLoading(false);
    }
  }

  async function handleLogVisit(payload: MonitoringPayload) {
    if (selectedEntry) await updateMonitoring(ncpId, selectedEntry.id, payload);
    else await createMonitoring(ncpId, payload);
    setSelectedEntry(null);
    setShowForm(false);
    await loadData(true);
  }

  async function handleDeleteVisit() {
    if (!deleteEntry || deleting) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await deleteMonitoring(ncpId, deleteEntry.id);
      setDeleteEntry(null);
      await loadData(true);
    } catch (err) {
      setDeleteError(err instanceof Error ? err.message : 'Failed to delete visit.');
    } finally { setDeleting(false); }
  }

  // ─── No patient selected ───────────────────────────────────────────────────
  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <Breadcrumb />
        <div className="bg-white border border-warm-200 rounded-2xl p-10 sm:p-12 text-center max-w-2xl mx-auto shadow-sm">
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

  if (!loading && loadFailed) {
    return <div className="p-6 space-y-3"><p role="alert" className="text-sm text-red-700">{error}</p><button type="button" className="min-h-11 underline" onClick={() => { void loadData(); }}>Try again</button></div>;
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
          ncpId={ncpId}
          physician={patient?.physician}
          riskScore={assessment?.risk_score ?? assessment?.computed_risk_score}
          foodDetails={[
            ...(assessment?.allergies ?? []),
            ...(assessment?.food_dislikes ?? []),
            assessment?.dietary_restrictions,
          ]}
          interventionGoal={intervention?.goal_type}
          medicalDiagnosis={patient?.medical_diagnosis}
        />
        <div className="bg-white border border-warm-200 rounded-2xl p-10 sm:p-12 text-center max-w-2xl mx-auto shadow-sm">
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

  // Tab button class helper
  return (
    <div className="space-y-6 font-sans">
      <Breadcrumb />
      <NcpPatientHeader
        patient={patient}
        ncpId={ncpId}
        physician={patient?.physician}
        riskScore={assessment?.risk_score ?? assessment?.computed_risk_score}
        foodDetails={[
          ...(assessment?.allergies ?? []),
          ...(assessment?.food_dislikes ?? []),
          assessment?.dietary_restrictions,
        ]}
        interventionGoal={intervention?.goal_type}
        medicalDiagnosis={patient?.medical_diagnosis}
        onBeforeUnselectPatient={leaveVisit}
      />

      {/* ── Page header ─────────────────────────────────────────────────────── */}
      <div className="border-b border-warm-200 pb-5">
        <h2 className="text-xl font-extrabold text-warm-900 tracking-tight">
          Nutrition Monitoring & Evaluation
        </h2>
      </div>

      {/* ── Error banner ────────────────────────────────────────────────────── */}
      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
          {error}
        </div>
      )}

      {/* ── Loading skeleton ─────────────────────────────────────────────────── */}
      {loading ? (
        <div role="status" aria-busy="true" aria-label="Loading monitoring…" className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="lg:col-span-2 space-y-6">
            <div className="h-10 bg-warm-100 rounded-xl motion-safe:animate-pulse" />
            <div className="h-48 bg-warm-100 rounded-2xl motion-safe:animate-pulse" />
            <div className="h-64 bg-warm-100 rounded-2xl motion-safe:animate-pulse" />
          </div>
          <div className="space-y-5">
            <div className="h-40 bg-warm-100 rounded-2xl motion-safe:animate-pulse" />
            <div className="h-24 bg-warm-100 rounded-2xl motion-safe:animate-pulse" />
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

          {/* ── Main column (2/3 on lg+) ───────────────────────────────────── */}
          <div className="order-2 min-w-0 space-y-0 lg:order-1 lg:col-span-2">

            <NcpSectionTabs
              items={[{ key: "log", label: "Visit Log" }, { key: "progress", label: "Progress Trends" }]}
              value={activeTab}
              onChange={(tab) => { if (tab !== activeTab) leaveVisit(() => { setShowForm(false); setSelectedEntry(null); setActiveTab(tab); }); }}
              ariaLabel="Monitoring views"
              idPrefix="monitoring"
              className="mb-6 w-full max-w-sm sm:w-fit"
            />

            {/* ── Visit Log tab ─────────────────────────────────────────── */}
            {activeTab === "log" && (
              <div id="monitoring-panel-log" role="tabpanel" aria-labelledby="monitoring-tab-log" className="space-y-6">
                {!showForm && !selectedEntry && <>
                  <EncounterLog
                    entries={entries}
                    onDelete={(entry) => { setDeleteError(null); setDeleteEntry(entry); }}
                    onLogNew={() => setShowForm(true)}
                    onView={setSelectedEntry}
                  />
                  {historyLoading && <p role="status" className="text-sm text-warm-500">Loading visit history…</p>}
                  {historyError && <p role="alert" className="text-sm text-red-700">{historyError}</p>}
                  <Pagination meta={historyMeta} page={historyPage} onPageChange={changeHistoryPage} />
                  {deleteEntry && <DeleteActionDialog title="Delete monitoring visit" message="Delete this saved monitoring entry?" busy={deleting} error={deleteError} onClose={() => setDeleteEntry(null)} onConfirm={handleDeleteVisit} />}
                </>}

                {(showForm || selectedEntry) && context && plan && (
                  <LogVisitForm
                    key={selectedEntry?.id ?? "new"}
                    entry={selectedEntry}
                    leaveRef={visitLeave}
                    ncpId={ncpId}
                    plan={plan}
                    context={context}
                    intervention={intervention}
                    onSubmit={handleLogVisit}
                    onCancel={() => { setShowForm(false); setSelectedEntry(null); }}
                  />
                )}
              </div>
            )}

            {/* ── Progress Trends tab ───────────────────────────────────── */}
            {activeTab === "progress" && (
              <div id="monitoring-panel-progress" role="tabpanel" aria-labelledby="monitoring-tab-progress" className="space-y-6">
                <CarePlanHeader plan={plan} />
                <div className="[&_button]:min-h-11"><ButtonFilterGroup label="Category" value={category} onChange={(value) => setCategory(value as IndicatorCategory | "all")} options={[
                  { value: "all", label: "All" }, { value: "anthro", label: "Anthropometric" },
                  { value: "lab", label: "Clinical Labs" }, { value: "intake", label: "Intake" },
                ]} /></div>
                <GoalProgressTracker
                  category={category}
                  plan={plan}
                  entries={entries}
                  baselineWeight={assessment?.weight ? Number(assessment.weight) : null}
                  baselineBmi={assessment?.bmi ? Number(assessment.bmi) : null}
                  baselineLabs={biochemicalData}
                  nutritionalStatus={assessment?.bmi_classification ?? null}
                />
                <VisitTrendsChart plan={plan} entries={entries} intervention={intervention} category={category} />
              </div>
            )}
          </div>

          {/* ── Sidebar (1/3 on lg+, full-width below lg) ─────────────────── */}
          <div className="order-1 min-w-0 space-y-5 lg:order-2 lg:pt-[69px]">
            {/* Monitoring record card */}
            <div className="rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-5 sm:shadow-sm">
              <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider mb-4">
                Monitoring Record
              </h3>
              <div className="space-y-0 text-sm divide-y divide-zinc-100">
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-warm-400">Patient code</span>
                  <span className="font-mono font-bold text-warm-900">{patient?.patient_code ?? "Not available"}</span>
                </div>
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-warm-400">Cycle started</span>
                  <span className="font-semibold text-warm-900 text-right">
                    {formatCycleDate(plan?.visits.find((visit) => visit.type === "assessment")?.date)}
                  </span>
                </div>
                <div className="flex justify-between py-2.5">
                  <span className="font-semibold text-warm-400">Total Visits</span>
                  <span className="font-mono font-bold text-warm-900">{historyMeta?.total ?? entries.length}</span>
                </div>
                {intervention?.goal_type && (
                  <div className="flex justify-between py-2.5 gap-3">
                    <span className="font-semibold text-warm-400 shrink-0">Goal</span>
                    <span className="font-semibold text-warm-700 text-right capitalize leading-tight">
                      {intervention.goal_type.replace(/_/g, " ")}
                    </span>
                  </div>
                )}
                {assessment?.bmi_classification && (
                  <div className="flex justify-between py-2.5 gap-3">
                    <span className="font-semibold text-warm-400 shrink-0">Starting BMI class</span>
                    <span className="font-semibold text-warm-700 text-right leading-tight">
                      {assessment.bmi_classification}
                    </span>
                  </div>
                )}
                {assessment?.weight && (
                  <div className="flex justify-between py-2.5">
                    <span className="font-semibold text-warm-400">Starting Weight</span>
                    <span className="font-mono font-bold text-warm-900">
                      {assessment.weight} kg
                    </span>
                  </div>
                )}
                {assessment?.bmi && (
                  <div className="flex justify-between py-2.5">
                    <span className="font-semibold text-warm-400">Starting BMI</span>
                    <span className="font-mono font-bold text-warm-900">{assessment.bmi}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Prescription summary card — only shown if intervention exists */}
            {intervention && (intervention.energy_kcal || intervention.protein_g || intervention.carbs_g || intervention.fat_g || intervention.fluid_ml || micronutrientTargets.length > 0) && (
              <div className="rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-5 sm:shadow-sm">
                <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider mb-4">
                  Prescription Targets / Limits
                </h3>
                <div className="space-y-0 text-sm divide-y divide-zinc-100">
                  {intervention.energy_kcal && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Energy</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.nutrient_operators?.energy_kcal === 'max' ? '≤' : intervention.nutrient_operators?.energy_kcal === 'min' ? '≥' : ''}{intervention.energy_kcal} kcal
                      </span>
                    </div>
                  )}
                  {intervention.protein_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Protein</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.nutrient_operators?.protein_g === 'max' ? '≤' : intervention.nutrient_operators?.protein_g === 'min' ? '≥' : ''}{intervention.protein_g} g
                      </span>
                    </div>
                  )}
                  {intervention.carbs_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Carbs</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.nutrient_operators?.carbs_g === 'max' ? '≤' : intervention.nutrient_operators?.carbs_g === 'min' ? '≥' : ''}{intervention.carbs_g} g
                      </span>
                    </div>
                  )}
                  {intervention.fat_g && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Fat</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.nutrient_operators?.fat_g === 'max' ? '≤' : intervention.nutrient_operators?.fat_g === 'min' ? '≥' : ''}{intervention.fat_g} g
                      </span>
                    </div>
                  )}
                  {intervention.fluid_ml && (
                    <div className="flex justify-between py-2.5">
                      <span className="font-semibold text-warm-400">Fluid</span>
                      <span className="font-mono font-bold text-warm-900">
                        {intervention.nutrient_operators?.fluid_ml === 'max' ? '≤' : intervention.nutrient_operators?.fluid_ml === 'min' ? '≥' : ''}{intervention.fluid_ml} mL
                      </span>
                    </div>
                  )}
                  {micronutrientTargets.map(({ key, label, amount, unit }) => (
                    <div key={key} className="flex justify-between gap-3 py-2.5">
                      <span className="font-semibold text-warm-400">{label}</span>
                      <span className="font-mono font-bold text-warm-900">{amount} {unit}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
