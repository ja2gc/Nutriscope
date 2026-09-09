"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { HeartHandshake, Plus, Trash2, AlertTriangle, Lock, Paperclip, FileText, Download, Eye, X } from "lucide-react";

import {
  fetchPatientById,
  fetchPatientNcpRecords,
  createNcpRecord,
  deletePatient,
  deleteNcpRecord,
  transitionNcpRecord,
  NcpRecord,
  Patient,
} from "@/services/patientService";
import {
  AttachmentRecord,
  fetchAttachments,
  deleteAttachment,
  getAttachmentFileUrl,
} from "@/services/assessmentService";
import { getNcpStepState, type NcpStep, type NcpStepState } from "@/lib/ncpWorkflow";
import { formatPatientAge } from "@/lib/patientAge";
import { ClinicalAttribution } from "@/components/ncp/ClinicalAttribution";
import { personDisplayName } from "@/lib/personName";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { InfoHint } from "@/components/ui/InfoHint";
import { ReportPreview } from "@/components/ReportPreview";
import { prepareReport, reportDownloadUrl, reportViewUrl } from "@/services/reportService";
import { PatientAppointments } from "@/components/ncp/PatientAppointments";
import { FittedImageFrame } from "@/components/ui/ImageUploadGallery";

type TabKey = "overview" | "adime-records" | "appointments" | "attachments";
const NCP_STEPS: NcpStep[] = ["assessment", "diagnosis", "intervention", "monitoring"];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatAbsoluteDate(value?: string | null) {
  if (!value) return "Not yet completed";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not yet completed";
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

function formatRelativeDate(value?: string | null) {
  if (!value) return "Not yet completed";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Not yet completed";
  const diffDays = Math.round((date.getTime() - Date.now()) / (1000 * 60 * 60 * 24));
  if (diffDays === 0) return "Today";
  if (diffDays > 0) return diffDays === 1 ? "In 1 day" : `In ${diffDays} days`;
  const abs = Math.abs(diffDays);
  return abs === 1 ? "1 day ago" : `${abs} days ago`;
}

function formatRiskLabel(score?: number | string | null) {
  if (score === null || score === undefined || score === "") {
    return { label: "Unscored", className: "bg-warm-50 text-warm-600 border-warm-200" };
  }
  const n = Number(score);
  if (!Number.isFinite(n)) return { label: "Unscored", className: "bg-warm-50 text-warm-600 border-warm-200" };
  if (n >= 4) return { label: `High · ${n.toFixed(1)}`, className: "bg-red-50 text-red-700 border-red-100" };
  if (n >= 2) return { label: `Medium · ${n.toFixed(1)}`, className: "bg-amber-50 text-amber-700 border-amber-100" };
  return { label: `Low · ${n.toFixed(1)}`, className: "bg-emerald-50 text-emerald-700 border-emerald-100" };
}

function formatStatus(status?: string | null) {
  switch ((status || "").toLowerCase()) {
    case "active":     return { label: "Active",     className: "bg-emerald-50 text-emerald-700 border-emerald-100" };
    case "completed":  return { label: "Completed",  className: "bg-warm-100 text-warm-600 border-warm-200" };
    case "discontinued": return { label: "Discontinued", className: "bg-amber-50 text-amber-700 border-amber-100" };
    case "discharged": return { label: "Discharged", className: "bg-orange-50 text-orange-700 border-orange-100" };
    default:           return { label: "Draft",      className: "bg-warm-50 text-warm-500 border-warm-200" };
  }
}

function formatReason(value?: string | null) {
  if (!value) return null;
  return value.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
}

// A record is protected once it has Assessment + at least one Diagnosis + Intervention.
// Monitoring is not required — a cycle can be official without a follow-up visit yet.
function isDeletableRecord(record: NcpRecord) {
  if (typeof record.can_delete === "boolean") return record.can_delete;
  const hasAssessment   = !!record.assessment;
  const hasDiagnoses    = (record.diagnoses?.length ?? 0) > 0;
  const hasIntervention = !!record.intervention;
  return !(hasAssessment && hasDiagnoses && hasIntervention);
}

function isImagePath(path?: string) {
  return !!path && /\.(png|jpe?g|gif|webp)$/i.test(path);
}

function getAttachmentDisplayName(doc: AttachmentRecord, index: number, total: number) {
  const label = doc.type === "labs" ? "Biochemical Data" : doc.type === "referral" ? "Screening Form" : (doc.original_name ?? "Document");
  return `${label} ${total > 1 ? index + 1 : ""}`.trim();
}

// ─── Confirm danger banner ────────────────────────────────────────────────────

function ConfirmBanner({
  message,
  onConfirm,
  onCancel,
  loading,
}: {
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
  loading: boolean;
}) {
  return (
    <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
      <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5 sm:mt-0" />
      <p className="text-sm text-red-700 font-semibold flex-1">{message}</p>
      <div className="flex gap-2 shrink-0">
        <button
          onClick={onCancel}
          disabled={loading}
          className="px-3 py-1.5 text-xs font-bold uppercase tracking-wider rounded-lg border border-warm-200 text-warm-600 hover:bg-warm-50 transition-colors disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          onClick={onConfirm}
          disabled={loading}
          className="px-3 py-1.5 text-xs font-bold uppercase tracking-wider rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors disabled:opacity-50"
        >
          {loading ? "Deleting…" : "Delete"}
        </button>
      </div>
    </div>
  );
}

function StepAction({ state }: { state: NcpStepState }) {
  const enabledClass = "border-warm-200 text-warm-700 hover:bg-warm-50";

  if (state.available) {
    return (
      <Link
        href={state.href}
        className={`inline-flex min-h-9 items-center justify-center px-3 py-2 text-xs font-bold uppercase tracking-wider rounded-lg border transition-colors ${enabledClass}`}
      >
        {state.label}
      </Link>
    );
  }

  return (
    <div
      title={state.reason ?? undefined}
      className="min-h-9 rounded-lg border border-warm-200 bg-warm-50 px-3 py-2 text-center text-xs font-bold uppercase tracking-wider text-warm-400 cursor-not-allowed"
    >
      <span className="block">{state.label}</span>
      {state.reason && <span className="mt-1 block normal-case tracking-normal font-semibold text-xs leading-tight">{state.reason}</span>}
    </div>
  );
}

function AttachmentLightbox({
  url,
  isImage,
  name,
  onClose,
}: {
  url: string;
  isImage: boolean;
  name: string;
  onClose: () => void;
}) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => { if (event.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      style={{ backdropFilter: "blur(8px)", WebkitBackdropFilter: "blur(8px)", backgroundColor: "rgba(0,0,0,0.55)" }}
      onClick={onClose}
    >
      <div
        className="relative bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-2xl max-h-[90vh] flex flex-col"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between px-4 py-3 border-b border-warm-100">
          <p className="text-sm font-bold text-warm-700 truncate pr-4">{name}</p>
          <div className="flex items-center gap-2 shrink-0">
            <a
              href={url}
              download={name}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors"
            >
              <Download className="h-3 w-3" />
              Download
            </a>
            <button
              type="button"
              onClick={onClose}
              className="p-1.5 rounded-lg text-warm-400 hover:text-warm-700 hover:bg-warm-100 transition-colors"
              title="Close"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>
        <div className="flex-1 overflow-auto bg-warm-50 flex items-center justify-center min-h-0">
          {isImage ? (
            <FittedImageFrame src={url} alt={name} variant="viewer" />
          ) : (
            <iframe src={url} title={name} className="w-full h-[75vh] border-0" />
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Per-cycle attachments (rnd.md §3.1) ───────────────────────────────────────
// Each NCP cycle shows only its own documents — scoped by ncp_record id, no mix-up.
function CycleAttachments({ ncpId }: { ncpId: number | string }) {
  const [items, setItems] = useState<AttachmentRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [lightbox, setLightbox] = useState<{ url: string; isImage: boolean; name: string } | null>(null);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      const result = await fetchAttachments(ncpId, undefined, page);
      setItems(result.data);
      setMeta(result.meta);
    } catch {
      setItems([]);
      setError("Failed to load attachments.");
    } finally {
      setLoading(false);
    }
  }, [ncpId, page]);

  useEffect(() => { void load(); }, [load]);

  async function handleDelete(id: number) {
    setDeletingId(id);
    try {
      await deleteAttachment(id);
      await load();
    } catch {
      setError("Failed to delete attachment.");
    } finally {
      setDeletingId(null);
    }
  }

  if (loading) {
    return <div className="h-10 bg-warm-100 rounded-lg animate-pulse" />;
  }

  return (
    <>
      {lightbox && (
        <AttachmentLightbox
          url={lightbox.url}
          isImage={lightbox.isImage}
          name={lightbox.name}
          onClose={() => setLightbox(null)}
        />
      )}
      {items.length === 0 ? (
        <p className="text-xs text-warm-400 font-semibold px-1 py-2">No documents attached to this cycle.</p>
      ) : (
        <div className="space-y-2">
          {error && (
            <div className="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 font-semibold">{error}</div>
          )}
          {items.map((doc, index) => {
            const fileUrl = getAttachmentFileUrl(doc.id);
            const docName = getAttachmentDisplayName(doc, index, items.length);
            const isImg = isImagePath(doc.file_path);
            return (
              <div key={doc.id} className="flex items-center gap-3 px-3 py-2 bg-warm-50 border border-warm-200 rounded-xl">
                <FileText className="h-4 w-4 text-warm-400 shrink-0" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-warm-800 truncate">{docName}</p>
                  {doc.type && <p className="text-xs text-warm-400">{doc.type}</p>}
                </div>
                <button
                  type="button"
                  onClick={() => setLightbox({ url: fileUrl, isImage: isImg, name: docName })}
                  className="p-1.5 text-warm-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                  title="View"
                >
                  <Eye className="h-3.5 w-3.5" />
                </button>
                <a
                  href={fileUrl}
                  download={docName}
                  className="p-1.5 text-warm-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors"
                  title="Download"
                >
                  <Download className="h-3.5 w-3.5" />
                </a>
                <button
                  type="button"
                  onClick={() => handleDelete(doc.id)}
                  disabled={deletingId === doc.id}
                  className="p-1.5 text-warm-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                  title="Delete"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            );
          })}
        </div>
      )}
      <Pagination meta={meta} page={page} onPageChange={setPage} />
    </>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function PatientProfilePage({
  params,
}: {
  params: Promise<{ patientId: string }>;
}) {
  const { patientId } = use(params);
  const router = useRouter();

  const [patient, setPatient]   = useState<Patient | null>(null);
  const [records, setRecords]   = useState<NcpRecord[]>([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabKey>("overview");
  const [targetAppointmentId, setTargetAppointmentId] = useState<string | undefined>();
  const [recordsPage, setRecordsPage] = useState(1);
  const [recordsMeta, setRecordsMeta] = useState<PaginationMeta | null>(null);

  // Action states
  const [startingCycle, setStartingCycle]             = useState(false);
  const [cycleError, setCycleError]                   = useState<string | null>(null);
  const [confirmDeleteRecord, setConfirmDeleteRecord] = useState<number | string | null>(null);
  const [deletingRecordId, setDeletingRecordId]       = useState<number | string | null>(null);
  const [recordDeleteError, setRecordDeleteError]     = useState<string | null>(null);
  const [confirmDeletePatient, setConfirmDeletePatient] = useState(false);
  const [deletingPatient, setDeletingPatient]         = useState(false);
  const [patientDeleteError, setPatientDeleteError]   = useState<string | null>(null);
  const [mealPlanPreview, setMealPlanPreview] = useState<{ id: string; title: string } | null>(null);
  const [discontinueRecordId, setDiscontinueRecordId] = useState<number | string | null>(null);
  const [discontinueReason, setDiscontinueReason] = useState("lost_to_follow_up");

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [patientData, currentData, pastData] = await Promise.all([
        fetchPatientById(patientId),
        fetchPatientNcpRecords(patientId, 1, undefined, { scope: "current" }),
        fetchPatientNcpRecords(patientId, recordsPage, undefined, { scope: "past" }),
      ]);
      setPatient(patientData);
      setRecords([...currentData.data, ...pastData.data]);
      setRecordsMeta(pastData.meta);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to load patient profile.");
    } finally {
      setLoading(false);
    }
  }, [patientId, recordsPage]);

  useEffect(() => { void loadData(); }, [loadData]);

  useEffect(() => {
    const query = new URLSearchParams(window.location.search);
    if (query.get("tab") === "appointments") setActiveTab("appointments");
    setTargetAppointmentId(query.get("appointmentId") ?? undefined);
  }, []);

  // ─── Start New Cycle ───────────────────────────────────────────────────────
  async function handleStartNewCycle() {
    setCycleError(null);
    setStartingCycle(true);
    try {
      const record = await createNcpRecord(patientId);
      router.push(`/ncp/${patientId}/assessment/${record.id}`);
    } catch (err: unknown) {
      setCycleError(err instanceof Error ? err.message : "Failed to start new cycle.");
      setStartingCycle(false);
    }
  }

  // ─── Delete NCP record ─────────────────────────────────────────────────────
  async function handleDeleteRecord(id: number | string) {
    setRecordDeleteError(null);
    setDeletingRecordId(id);
    try {
      await deleteNcpRecord(id);
      setConfirmDeleteRecord(null);
      await loadData();
    } catch (err: unknown) {
      setRecordDeleteError(err instanceof Error ? err.message : "Failed to delete record.");
    } finally {
      setDeletingRecordId(null);
    }
  }

  // ─── Delete Patient ────────────────────────────────────────────────────────
  async function handleDeletePatient() {
    setPatientDeleteError(null);
    setDeletingPatient(true);
    try {
      await deletePatient(patientId);
      router.push("/ncp/patients");
    } catch (err: unknown) {
      setPatientDeleteError(err instanceof Error ? err.message : "Failed to delete patient.");
      setDeletingPatient(false);
      setConfirmDeletePatient(false);
    }
  }

  async function handleMealPlanPreview(mealPlanId: number | string, title: string) {
    setCycleError(null);
    try {
      const report = await prepareReport("patient_menu_plan", { meal_plan_id: mealPlanId }, "rnd");
      setMealPlanPreview({ id: report.id, title });
    } catch (err: unknown) {
      setCycleError(err instanceof Error ? err.message : "Failed to prepare meal plan report.");
    }
  }

  async function handleCycleTransition(id: number | string, action: "complete" | "discontinue") {
    setCycleError(null);
    try {
      await transitionNcpRecord(id, action === "complete" ? { action } : { action, reason_code: discontinueReason });
      setDiscontinueRecordId(null);
      await loadData();
    } catch (err: unknown) {
      setCycleError(err instanceof Error ? err.message : "Failed to update NCP cycle.");
    }
  }

  // ─── Derived ──────────────────────────────────────────────────────────────
  const currentRecords = records.filter((record) => ["draft", "active"].includes(record.status));
  const pastRecords = records.filter((record) => ["completed", "discontinued", "discharged"].includes(record.status));
  const currentRecord = currentRecords[0] ?? null;
  const latestRecord  = currentRecord ?? pastRecords[0] ?? null;
  const allergies     = latestRecord?.assessment?.allergies ?? [];
  const riskMeta      = formatRiskLabel(patient?.risk_score);
  const latestAssessment  = latestRecord?.assessment?.rnd_summary?.trim();
  const latestMonitoring  = patient?.next_appointment_at;

  // The server checks every cycle, including past pages not loaded in this view.
  const canDeletePatient = patient?.can_delete === true;

  // ─── Loading ──────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <div className="h-4 w-48 bg-warm-200 rounded-lg animate-pulse" />
        <div className="h-32 bg-warm-200 rounded-2xl animate-pulse" />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 h-96 bg-warm-200 rounded-2xl animate-pulse" />
          <div className="h-96 bg-warm-200 rounded-2xl animate-pulse" />
        </div>
      </div>
    );
  }

  if (error || !patient) {
    return (
      <div className="space-y-6 font-sans max-w-3xl mx-auto py-12 select-none">
        <div className="bg-red-50 border border-red-100 p-6 rounded-2xl text-center space-y-4">
          <span className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-red-200 text-lg font-black text-red-600 mx-auto">!</span>
          <h3 className="text-base font-bold text-warm-900 uppercase tracking-wider">Patient Profile Error</h3>
          <p className="text-sm text-warm-500 max-w-sm mx-auto leading-relaxed">
            {error || "The requested patient record could not be found."}
          </p>
          <Link href="/ncp/patients" className="inline-flex px-4 py-2 bg-forest-900 hover:bg-forest-800 text-white font-semibold text-sm rounded-lg transition-all">
            Return to Directory
          </Link>
        </div>
      </div>
    );
  }

  const age = formatPatientAge(patient.dob);
  const patientName = personDisplayName(patient);

  return (
    <div className="space-y-6 font-sans">

      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Patients</Link>
        <span className="text-warm-300">/</span>
        <span className="text-zinc-650 font-bold">{patientName}</span>
      </div>

      {/* ── Patient header card ────────────────────────────────────────────── */}
      <div className="bg-white border border-warm-200 rounded-2xl p-5.5 shadow-sm space-y-4">
        <div className="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2.5">
              <h2 className="text-xl font-extrabold text-warm-900 tracking-tight">{patientName}</h2>
              <span className={`px-2 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border ${formatStatus(patient.status).className}`}>
                {formatStatus(patient.status).label}
              </span>
            </div>

            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-warm-500 font-semibold">
              <span>{age !== "N/A" ? age : "Age N/A"} · {patient.sex ?? "Sex N/A"}</span>
              <span>{patient.ward ?? "Ward N/A"}</span>
              <span>{patient.physician ?? "Physician N/A"}</span>
            </div>

            <div className="text-sm text-warm-700 font-medium">
              <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block mb-1">Primary medical diagnosis</span>
              <span className="text-warm-800 leading-relaxed font-semibold">{patient.medical_diagnosis ?? "N/A"}</span>
            </div>
          </div>

          <div className="min-w-[240px] shrink-0 space-y-2">
            <div className="flex flex-wrap gap-2">
              <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border ${riskMeta.className}`}>
                {riskMeta.label}
              </span>
            </div>

            {allergies.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {allergies.map((allergy) => (
                  <span key={allergy} className="inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border bg-red-50 text-red-700 border-red-100">
                    Allergy: {allergy}
                  </span>
                ))}
              </div>
            )}

            {latestRecord ? (
              <div className="grid grid-cols-2 gap-2 pt-1">
                {NCP_STEPS.map((step) => (
                  <StepAction
                    key={step}
                    state={getNcpStepState(latestRecord, step)}
                  />
                ))}
              </div>
            ) : (
              <div className="rounded-lg border border-warm-200 bg-warm-50 px-3 py-2 text-xs font-bold uppercase tracking-wider text-warm-500">
                No active NCP workflow
              </div>
            )}
          </div>
        </div>

        {/* Delete patient */}
        <div className="border-t border-warm-100 pt-4 space-y-2">
          <InfoHint label="Patient and NCP record protection rules" title="Record protection rules">
            <div className="space-y-2">
              <p>A patient can only be deleted if none of their NCP cycles have completed all of Assessment, Diagnosis, and Intervention.</p>
              <p>Starting a new cycle does not change prior ADIME records.</p>
              <p>A cycle can be deleted as long as it has not completed all of Assessment, Diagnosis, and Intervention. Once all three are recorded, the cycle is protected.</p>
            </div>
          </InfoHint>
          {canDeletePatient ? (
            <div className="space-y-2">
              {!confirmDeletePatient ? (
                <button
                  onClick={() => { setConfirmDeletePatient(true); setPatientDeleteError(null); }}
                  className="flex items-center gap-1.5 text-xs font-bold text-red-500 hover:text-red-700 uppercase tracking-wider transition-colors"
                >
                  <Trash2 className="h-3 w-3" />
                  Delete Patient
                </button>
              ) : (
                <div className="space-y-2">
                  <ConfirmBanner
                    message={`Delete ${patientName} and all their NCP data? This cannot be undone.`}
                    onConfirm={handleDeletePatient}
                    onCancel={() => setConfirmDeletePatient(false)}
                    loading={deletingPatient}
                  />
                  {patientDeleteError && (
                    <p className="text-sm text-red-600 font-semibold">{patientDeleteError}</p>
                  )}
                </div>
              )}
            </div>
          ) : (
            <div className="flex items-center gap-2 px-3 py-2 bg-warm-50 border border-warm-200 rounded-lg w-fit">
              <Lock className="h-3 w-3 text-warm-400 shrink-0" />
              <span className="text-xs font-bold text-warm-500 uppercase tracking-wider">
                Protected — completed NCP records exist
              </span>
            </div>
          )}
        </div>
      </div>

      {/* ── Tabs ─────────────────────────────────────────────────────────────── */}
      <div className="border-b border-warm-200 select-none">
        <nav className="flex space-x-6">
          {(["overview", "adime-records", "appointments", "attachments"] as TabKey[]).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`pb-4 text-sm font-extrabold uppercase tracking-wider border-b-2 transition-all ${
                activeTab === tab
                  ? "border-emerald-600 text-emerald-800"
                  : "border-transparent text-warm-400 hover:text-warm-600"
              }`}
            >
              {tab === "overview" ? "Overview" : tab === "adime-records" ? "ADIME Records" : tab === "appointments" ? "Appointments" : "Attachments"}
            </button>
          ))}
        </nav>
      </div>

      {/* ── Overview tab ─────────────────────────────────────────────────────── */}
      {activeTab === "overview" && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          <div className="lg:col-span-2 space-y-6">
            <div className="bg-white border border-warm-200 rounded-2xl overflow-hidden">
              <div className="px-5 py-4 border-b border-warm-100 bg-warm-50">
                <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider flex items-center gap-2">
                  <HeartHandshake className="h-4.5 w-4.5 text-emerald-600" />
                  Patient Profile
                </h3>
              </div>
              <div className="p-5.5 grid grid-cols-1 sm:grid-cols-2 gap-y-4.5 gap-x-6 text-sm">
                <div className="space-y-1">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block">Date of Birth</span>
                  <span className="text-warm-800 font-semibold">{formatAbsoluteDate(patient.dob)}</span>
                </div>
                <div className="space-y-1">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block">Admission Date</span>
                  <span className="text-warm-800 font-semibold">{formatAbsoluteDate(patient.admission_date)}</span>
                </div>
                <div className="space-y-1">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block">Contact Number</span>
                  <span className={`font-mono font-semibold ${patient.contact ? "text-warm-800" : "text-warm-400"}`}>{patient.contact ?? "N/A"}</span>
                </div>
                <div className="space-y-1">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block">Religion</span>
                  <span className={`font-semibold ${patient.religion ? "text-warm-800" : "text-warm-400"}`}>{patient.religion ?? "N/A"}</span>
                </div>
                <div className="sm:col-span-2 space-y-1">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block">Home Address</span>
                  <span className={`font-semibold leading-relaxed ${patient.address ? "text-warm-700" : "text-warm-400"}`}>{patient.address ?? "N/A"}</span>
                </div>
              </div>
            </div>

            <div className="bg-white border border-warm-200 rounded-2xl overflow-hidden">
              <div className="px-5 py-4 border-b border-warm-100 bg-warm-50">
                <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider">Referral Details</h3>
              </div>
              <div className="p-5.5 space-y-4 text-sm text-warm-600 leading-relaxed">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl">
                    <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">Referring Physician</span>
                    <span className="mt-1 block font-semibold text-warm-800">{patient.physician || "Unassigned"}</span>
                  </div>
                  <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl">
                    <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">Latest Assessment</span>
                    <span className="mt-1 block font-semibold text-warm-800">
                      {latestAssessment ? formatRelativeDate(latestRecord?.updated_at) : "No assessment yet"}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white border border-warm-200 rounded-2xl p-6">
              <h3 className="text-sm font-extrabold text-warm-900 uppercase tracking-wider mb-3">Current Cycle Snapshot</h3>
              {currentRecord ? (
                <div className="space-y-3.5 pt-2">
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-semibold text-warm-500">Current NCP Cycle</span>
                    <span className="px-2 py-0.5 rounded-lg text-xs font-extrabold uppercase tracking-wider border bg-warm-50 text-warm-600 border-warm-200">
                      {formatStatus(currentRecord.status).label}
                    </span>
                  </div>
                  <div className="text-sm text-warm-600">
                    <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block mb-1">Next Appointment</span>
                    <span className="font-semibold text-warm-800">{formatAbsoluteDate(latestMonitoring)}</span>
                  </div>
                </div>
              ) : (
                <p className="text-sm text-warm-500 leading-relaxed">No current NCP cycle. Past records remain available in ADIME Records.</p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* ── ADIME Records tab ─────────────────────────────────────────────────── */}
      {activeTab === "adime-records" && (
        <div className="space-y-5">

          {/* Start New Cycle header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">
                NCP Cycles <span className="font-mono text-warm-400 normal-case ml-1">({currentRecords.length + (recordsMeta?.total ?? pastRecords.length)})</span>
              </h3>
              <p className="text-xs text-warm-400 mt-0.5">Starting a new cycle does not change prior ADIME records.</p>
              <p className="text-xs text-warm-400 mt-1 leading-relaxed max-w-md">
                A cycle can be deleted as long as it has not completed all of Assessment, Diagnosis, and Intervention. Once all three are recorded, the cycle is protected.
              </p>
            </div>
            <button
              onClick={handleStartNewCycle}
              disabled={startingCycle}
              className="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold uppercase tracking-wider rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 active:bg-emerald-800 transition-colors disabled:opacity-60 disabled:cursor-not-allowed shrink-0"
            >
              <Plus className="h-3.5 w-3.5" />
              {startingCycle ? "Starting…" : "Start New Cycle"}
            </button>
          </div>

          {cycleError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-semibold">
              {cycleError}
            </div>
          )}

          {recordDeleteError && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-semibold">
              {recordDeleteError}
            </div>
          )}

          <div className="space-y-2">
            <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Current Cycle</h3>
            {currentRecords.length === 0 && <p className="rounded-xl border border-warm-200 bg-warm-50 p-4 text-sm text-warm-500">No current cycle. Start a new cycle when care resumes.</p>}
          </div>

          {records.length === 0 ? (
            <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center select-none">
              <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
                <HeartHandshake className="h-8 w-8" />
              </div>
              <h3 className="text-base font-bold text-warm-800 mt-4">No NCP cycles initiated</h3>
              <p className="text-sm text-warm-500 mt-1 max-w-sm mx-auto leading-relaxed">
                Use the button above to start the first NCP cycle for this patient.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {[...currentRecords, ...pastRecords].map((record) => {
                const cycleStatus = formatStatus(record.status);
                const cycleId = ["draft", "active"].includes(record.status) ? "Current Cycle" : "Past Record";
                const assessmentSummary  = record.assessment?.rnd_summary?.trim() || "Not yet completed";
                const diagnosisSummary   = record.diagnoses?.[0]?.pes_statement?.trim() || "Not yet completed";
                const interventionSummary = record.intervention?.goal_type?.trim() || "Not yet completed";
                const monitoringSummary = record.monitorings?.[0]?.clinical_summary?.trim()
                  || ((record.monitorings?.length ?? 0) > 0 ? `${record.monitorings!.length} monitoring record${record.monitorings!.length === 1 ? "" : "s"}` : "Not yet completed");
                const isCurrent = ["draft", "active"].includes(record.status);
                const canDelete = isCurrent && isDeletableRecord(record);
                const isConfirming = confirmDeleteRecord === record.id;
                const isDeleting = deletingRecordId === record.id;

                return (
                  <React.Fragment key={record.id}>
                  {pastRecords[0]?.id === record.id && (
                    <div className="space-y-2 pt-2">
                      <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Past Records</h3>
                    </div>
                  )}
                  <div className="bg-white border border-warm-200 rounded-2xl overflow-hidden">
                    {/* Record header */}
                    <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4 bg-warm-50">
                      <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="text-base font-extrabold text-warm-900 tracking-tight">{cycleId}</span>
                          <span className={`px-2 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border ${cycleStatus.className}`}>
                            {cycleStatus.label}
                          </span>
                        </div>
                        <p className="text-xs text-warm-500 uppercase tracking-wider font-bold">
                          Created {formatAbsoluteDate(record.created_at)}
                        </p>
                        {formatReason(record.discontinuation_reason_code) && (
                          <p className="text-xs font-semibold text-amber-700">Reason: {formatReason(record.discontinuation_reason_code)}</p>
                        )}
                        <ClinicalAttribution
                          creator={record.created_by}
                          lastAction={record.last_clinical_action}
                          formatDate={formatAbsoluteDate}
                          className="flex flex-wrap gap-x-3 gap-y-1"
                        />
                      </div>

                      <div className="flex items-center gap-4">
                        <div className="text-right text-sm text-warm-500 hidden sm:block">
                          <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block">Referring Physician</span>
                          <span className="font-semibold text-warm-700">{patient.physician || "Unassigned"}</span>
                        </div>
                        {!canDelete && isCurrent && <span title="Protected — Assessment, Diagnosis, and Intervention are all recorded" className="p-1.5 text-warm-300 cursor-default select-none"><Lock className="h-3.5 w-3.5" /></span>}
                      </div>
                    </div>

                    {["draft", "active"].includes(record.status) && (
                      <div className="border-b border-warm-100 px-5 py-3">
                        <p className="mb-2 text-xs font-extrabold uppercase tracking-wider text-warm-500">Actions</p>
                        <div className="flex flex-wrap items-center gap-2">
                          <button type="button" onClick={() => void handleCycleTransition(record.id, "complete")} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Complete and Protect</button>
                          <button type="button" onClick={() => setDiscontinueRecordId(record.id)} className="rounded-lg border border-amber-200 px-3 py-2 text-xs font-bold text-amber-700">Discontinue</button>
                          {canDelete && <button type="button" onClick={() => { setConfirmDeleteRecord(record.id); setRecordDeleteError(null); }} className="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600">Delete</button>}
                        </div>
                        {discontinueRecordId === record.id && (
                          <div className="mt-3 flex flex-wrap items-center gap-2">
                            <label className="text-xs font-bold text-warm-600" htmlFor={`discontinue-${record.id}`}>Reason</label>
                            <select id={`discontinue-${record.id}`} value={discontinueReason} onChange={(event) => setDiscontinueReason(event.target.value)} className="rounded-lg border border-warm-200 px-3 py-2 text-sm">
                              <option value="lost_to_follow_up">Lost to follow-up</option><option value="patient_declined">Patient declined</option><option value="transferred">Transferred</option><option value="discharged_before_completion">Discharged before completion</option><option value="care_elsewhere">Care continued elsewhere</option><option value="other">Other</option>
                            </select>
                            <button type="button" onClick={() => void handleCycleTransition(record.id, "discontinue")} className="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white">Confirm Discontinue</button>
                            <button type="button" onClick={() => setDiscontinueRecordId(null)} className="rounded-lg border border-warm-200 px-3 py-2 text-xs font-bold text-warm-600">Keep Cycle</button>
                          </div>
                        )}
                      </div>
                    )}

                    {/* Delete confirmation banner */}
                    {isConfirming && (
                      <div className="px-5 pt-4">
                        <ConfirmBanner
                          message={`Delete ${cycleId} and all its clinical data? This cannot be undone.`}
                          onConfirm={() => handleDeleteRecord(record.id)}
                          onCancel={() => setConfirmDeleteRecord(null)}
                          loading={isDeleting}
                        />
                      </div>
                    )}

                    {/* ADIME summaries */}
                    <div className="p-5.5 grid grid-cols-1 lg:grid-cols-2 gap-5">
                      <div className="space-y-4">
                        <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl">
                          <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">Assessment Summary</span>
                          <p className="mt-1 text-sm text-warm-700 leading-relaxed">{assessmentSummary}</p>
                        </div>
                        <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl">
                          <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">Diagnosis Summary</span>
                          <p className="mt-1 text-sm text-warm-700 leading-relaxed">{diagnosisSummary}</p>
                        </div>
                      </div>
                      <div className="space-y-4">
                        <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl">
                          <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">Intervention Summary</span>
                          <p className="mt-1 text-sm text-warm-700 leading-relaxed">{interventionSummary}</p>
                        </div>
                        <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl">
                          <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">Monitoring Summary</span>
                          <p className="mt-1 text-sm text-warm-700 leading-relaxed">{monitoringSummary}</p>
                        </div>
                      </div>
                    </div>

                    {/* Meal Plans */}
                    {(record.intervention?.meal_plans?.length ?? 0) > 0 && (
                      <div className="px-5.5 pb-4">
                        <p className="text-xs font-bold text-warm-400 uppercase tracking-widest mb-1.5">Meal Plans</p>
                        <div className="flex flex-wrap gap-1.5">
                          {record.intervention!.meal_plans!.map((mp, index) => (
                            <button
                              key={mp.id}
                              type="button"
                              onClick={() => void handleMealPlanPreview(mp.id, `Meal Plan ${index + 1}`)}
                              className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-warm-50 border border-warm-200 text-warm-700 hover:border-emerald-300 hover:text-emerald-700"
                            >
                              Meal Plan {index + 1}
                            </button>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* Quick nav buttons */}
                    <div className="px-5.5 pb-5.5">
                      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        {NCP_STEPS.map((step) => (
                          <StepAction
                            key={step}
                            state={getNcpStepState(record, step)}
                          />
                        ))}
                      </div>
                    </div>
                  </div>
                  </React.Fragment>
                );
              })}
              {pastRecords.length === 0 && (
                <div className="space-y-2 pt-2"><h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Past Records</h3><p className="rounded-xl border border-warm-200 bg-warm-50 p-4 text-sm text-warm-500">No past ADIME records yet.</p></div>
              )}
            </div>
          )}
          {records.length === 0 && (
            <div className="space-y-2 pt-2"><h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Past Records</h3><p className="rounded-xl border border-warm-200 bg-warm-50 p-4 text-sm text-warm-500">No past ADIME records yet.</p></div>
          )}
        </div>
      )}

      {/* ── Attachments tab — supporting documents per NCP cycle ──────────────── */}
      {activeTab === "attachments" && (
        <div className="space-y-5">
          <div>
            <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider flex items-center gap-2">
              <Paperclip className="h-4 w-4 text-emerald-600" />
              Supporting Documents
            </h3>
            <p className="text-xs text-warm-400 mt-0.5">
              Referral forms, screening forms, and lab results — grouped by NCP cycle so records never mix. Upload from each cycle&apos;s assessment page.
            </p>
          </div>

          {records.length === 0 ? (
            <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center select-none">
              <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
                <Paperclip className="h-8 w-8" />
              </div>
              <h3 className="text-base font-bold text-warm-800 mt-4">No NCP cycles yet</h3>
              <p className="text-sm text-warm-500 mt-1 max-w-sm mx-auto leading-relaxed">
                Start an NCP cycle to attach supporting documents.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {records.map((record) => {
                const cycleStatus = formatStatus(record.status);
                return (
                  <div key={record.id} className="bg-white border border-warm-200 rounded-2xl overflow-hidden">
                    <div className="px-5 py-3.5 border-b border-warm-100 flex items-center justify-between gap-3 bg-warm-50">
                      <div className="flex items-center gap-2">
                        <span className="text-base font-extrabold text-warm-900 tracking-tight">{["draft", "active"].includes(record.status) ? "Current Cycle" : "Past Record"}</span>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border ${cycleStatus.className}`}>
                          {cycleStatus.label}
                        </span>
                      </div>
                      <Link
                        href={`/ncp/${patientId}/assessment/${record.id}`}
                        className="text-xs font-bold uppercase tracking-wider text-emerald-700 hover:text-emerald-800"
                      >
                        Manage →
                      </Link>
                    </div>
                    <div className="p-5">
                      <CycleAttachments ncpId={record.id} />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {activeTab === "appointments" && (
        <PatientAppointments patientId={patientId} currentNcpId={currentRecords[0] ? String(currentRecords[0].id) : undefined} targetAppointmentId={targetAppointmentId} />
      )}
      {activeTab === "adime-records" && (
        <Pagination meta={recordsMeta} page={recordsPage} onPageChange={setRecordsPage} />
      )}
      {mealPlanPreview && (
        <ReportPreview
          title={mealPlanPreview.title}
          src={reportViewUrl(mealPlanPreview.id, "rnd")}
          downloadUrl={reportDownloadUrl(mealPlanPreview.id, "rnd")}
          onClose={() => setMealPlanPreview(null)}
        />
      )}
    </div>
  );
}
