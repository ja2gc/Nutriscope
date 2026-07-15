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
import { AuditTrail } from "@/components/audit/AuditTrail";
import { ClinicalAttribution } from "@/components/ncp/ClinicalAttribution";
import { personDisplayName } from "@/lib/personName";

type TabKey = "overview" | "adime-records" | "attachments";
const NCP_STEPS: NcpStep[] = ["assessment", "diagnosis", "intervention", "monitoring"];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatSystemId(id: number) {
  return `NS-${String(id).padStart(5, "0")}`;
}

function formatCycleId(id: number) {
  return `NCP-${String(id).padStart(5, "0")}`;
}

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
    case "discharged": return { label: "Discharged", className: "bg-orange-50 text-orange-700 border-orange-100" };
    default:           return { label: "Draft",      className: "bg-warm-50 text-warm-500 border-warm-200" };
  }
}

// A record is protected once it has Assessment + at least one Diagnosis + Intervention.
// Monitoring is not required — a cycle can be official without a follow-up visit yet.
function isDeletableRecord(record: NcpRecord) {
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
            // eslint-disable-next-line @next/next/no-img-element
            <img src={url} alt={name} className="max-w-full max-h-[75vh] object-contain p-2" />
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
function CycleAttachments({ ncpId }: { ncpId: number }) {
  const [items, setItems] = useState<AttachmentRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [lightbox, setLightbox] = useState<{ url: string; isImage: boolean; name: string } | null>(null);

  const load = useCallback(async () => {
    try {
      setError(null);
      setItems(await fetchAttachments(ncpId));
    } catch {
      setItems([]);
      setError("Failed to load attachments.");
    } finally {
      setLoading(false);
    }
  }, [ncpId]);

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

  if (items.length === 0) {
    return (
      <p className="text-xs text-warm-400 font-semibold px-1 py-2">No documents attached to this cycle.</p>
    );
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

  // Action states
  const [startingCycle, setStartingCycle]             = useState(false);
  const [cycleError, setCycleError]                   = useState<string | null>(null);
  const [confirmDeleteRecord, setConfirmDeleteRecord] = useState<number | null>(null);
  const [deletingRecordId, setDeletingRecordId]       = useState<number | null>(null);
  const [recordDeleteError, setRecordDeleteError]     = useState<string | null>(null);
  const [confirmDeletePatient, setConfirmDeletePatient] = useState(false);
  const [deletingPatient, setDeletingPatient]         = useState(false);
  const [patientDeleteError, setPatientDeleteError]   = useState<string | null>(null);

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [patientData, recordsData] = await Promise.all([
        fetchPatientById(patientId),
        fetchPatientNcpRecords(patientId),
      ]);
      setPatient(patientData);
      setRecords(recordsData);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to load patient profile.");
    } finally {
      setLoading(false);
    }
  }, [patientId]);

  useEffect(() => { void loadData(); }, [loadData]);

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
  async function handleDeleteRecord(id: number) {
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

  // ─── Derived ──────────────────────────────────────────────────────────────
  const latestRecord  = records[0] ?? null;
  const allergies     = latestRecord?.assessment?.allergies ?? [];
  const riskMeta      = formatRiskLabel(patient?.risk_score);
  const latestAssessment  = latestRecord?.assessment?.rnd_summary?.trim();
  const latestMonitoring  = latestRecord?.intervention?.next_followup_date;

  // Patient can be deleted only if no record has gone through A→D→I
  const canDeletePatient = records.every(isDeletableRecord);

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

  const systemId = formatSystemId(patient.id);
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
              <span className="text-sm font-mono px-2 py-0.5 bg-warm-100 text-warm-600 border border-warm-200 rounded-lg">{systemId}</span>
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
              {patient.risk_score !== null && patient.risk_score !== undefined && patient.risk_score !== "" && (
                <span className="inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border bg-warm-50 text-warm-500 border-warm-200">
                  System {Number(patient.risk_score).toFixed(1)}
                </span>
              )}
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
          <p className="text-xs text-warm-400 leading-relaxed">
            A patient can only be deleted if none of their NCP cycles have completed all of Assessment, Diagnosis, and Intervention.
          </p>
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
                Protected — {records.filter(r => !isDeletableRecord(r)).length} completed NCP cycle{records.filter(r => !isDeletableRecord(r)).length !== 1 ? "s" : ""}
              </span>
            </div>
          )}
        </div>
      </div>

      {/* ── Tabs ─────────────────────────────────────────────────────────────── */}
      <div className="border-b border-warm-200 select-none">
        <nav className="flex space-x-6">
          {(["overview", "adime-records", "attachments"] as TabKey[]).map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`pb-4 text-sm font-extrabold uppercase tracking-wider border-b-2 transition-all ${
                activeTab === tab
                  ? "border-emerald-600 text-emerald-800"
                  : "border-transparent text-warm-400 hover:text-warm-600"
              }`}
            >
              {tab === "overview" ? "Overview" : tab === "adime-records" ? "ADIME Records" : "Attachments"}
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
                <h3 className="text-sm font-bold text-warm-900 uppercase tracking-wider">NCP Entry Notes</h3>
              </div>
              <div className="p-5.5 space-y-4 text-sm text-warm-600 leading-relaxed">
                <p>This profile is the entry portal for the Nutrition Care Process. Review the demographics above, then start or continue a cycle from the header button.</p>
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
              {latestRecord ? (
                <div className="space-y-3.5 pt-2">
                  <div className="flex items-center justify-between text-sm">
                    <span className="font-semibold text-warm-500">Latest NCP Cycle</span>
                    <span className="px-2 py-0.5 rounded-lg text-xs font-extrabold uppercase tracking-wider border bg-warm-50 text-warm-600 border-warm-200">
                      {formatStatus(latestRecord.status).label}
                    </span>
                  </div>
                  <div className="text-sm text-warm-600">
                    <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block mb-1">Cycle ID</span>
                    <span className="font-mono font-semibold text-warm-800">{formatCycleId(latestRecord.id)}</span>
                  </div>
                  <div className="text-sm text-warm-600">
                    <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block mb-1">Next Follow-up</span>
                    <span className="font-semibold text-warm-800">{formatAbsoluteDate(latestMonitoring)}</span>
                  </div>
                </div>
              ) : (
                <p className="text-sm text-warm-500 leading-relaxed">No NCP cycles have been started for this patient yet.</p>
              )}
            </div>
          </div>
          <div className="lg:col-span-3">
            <AuditTrail path={`/api/rnd/patients/${patientId}/activity`} title="Patient and NCP activity" />
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
                NCP Cycles <span className="font-mono text-warm-400 normal-case ml-1">({records.length})</span>
              </h3>
              <p className="text-xs text-warm-400 mt-0.5">Each cycle is an independent ADIME workflow for this patient.</p>
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
              {records.map((record) => {
                const cycleStatus = formatStatus(record.status);
                const cycleId = formatCycleId(record.id);
                const assessmentSummary  = record.assessment?.rnd_summary?.trim() || "Not yet completed";
                const diagnosisSummary   = record.diagnoses?.[0]?.pes_statement?.trim() || "Not yet completed";
                const interventionSummary = record.intervention?.goal_type?.trim() || "Not yet completed";
                const monitoringSummary  = record.intervention?.next_followup_date
                  ? formatAbsoluteDate(record.intervention.next_followup_date)
                  : "Not yet completed";
                const canDelete = isDeletableRecord(record);
                const isConfirming = confirmDeleteRecord === record.id;
                const isDeleting = deletingRecordId === record.id;

                return (
                  <div key={record.id} className="bg-white border border-warm-200 rounded-2xl overflow-hidden">
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
                        {canDelete && !isConfirming ? (
                          <button
                            onClick={() => { setConfirmDeleteRecord(record.id); setRecordDeleteError(null); }}
                            className="p-1.5 text-warm-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                            title="Delete this cycle"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        ) : !isConfirming ? (
                          <span
                            title="Protected — Assessment, Diagnosis, and Intervention are all recorded"
                            className="p-1.5 text-warm-300 cursor-default select-none"
                          >
                            <Lock className="h-3.5 w-3.5" />
                          </span>
                        ) : null}
                      </div>
                    </div>

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
                          {record.intervention!.meal_plans!.map((mp) => (
                            <span key={mp.id} className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-warm-50 border border-warm-200 text-warm-700">
                              Week of {mp.week_start_date}
                              {mp.generation_type === "auto" && (
                                <span className="ml-1.5 text-xs font-bold text-warm-400 uppercase">AI</span>
                              )}
                            </span>
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
                    <div className="px-5.5 pb-5.5">
                      <AuditTrail path={`/api/rnd/ncp-records/${record.id}/activity`} title={`${cycleId} activity`} />
                    </div>
                  </div>
                );
              })}
            </div>
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
                        <span className="text-base font-extrabold text-warm-900 tracking-tight">{formatCycleId(record.id)}</span>
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
    </div>
  );
}
