"use client";

import { NUTRITION_SUPPORT_OPTIONS } from "@/lib/nutritionSupport";

import React, { use, useCallback, useEffect, useState, useRef } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/Button";
import layout from './assessmentLayout.module.css';
import { useEditorChanges } from '@/hooks/useUnsavedChanges';
import { FoodRecallCalculator, type FoodRecallHandle } from '@/components/ncp/FoodRecallCalculator';
import { FoodRecallNutrientFields } from '@/components/ncp/FoodRecallNutrientFields';
import type { FoodRecallPlan } from '@/lib/foodRecall';
import {
  ClipboardCheck, Utensils, Ruler, UserRound, FlaskConical,
  FileText, Sparkles, Save, Upload, AlertTriangle,
  Activity, Paperclip, Trash2, Download, Eye, X,
  Shield, Scale, RotateCcw,
} from "lucide-react";
import { fetchPatientById, Patient, updatePatient, PatientUpdateData } from "@/services/patientService";
import {
  calcIBW, calcPercentIBW, calcBMR, calcTEE, calcBmrWeight,
  classifyBmi, ACTIVITY_FACTORS,
} from "@/lib/nutritionCalculations";
import { CALCULATION_INPUT_HELPERS } from "@/lib/assessmentCalculationInputs";
import { CIRCUMFERENCE_BOUNDS, getAnthropometricSafetyWarning, getWaistHipAssessment, getMuacScreening } from "@/lib/anthropometricSafety";
import { formatDateInputValue, formatPatientAge, getPatientAgeParts } from "@/lib/patientAge";
import { changedPersonNameFields } from "@/lib/personName";
import {
  Assessment, AssessmentValidationError, fetchAssessment, saveAssessment,
  AttachmentRecord, uploadAttachment, fetchAttachments, deleteAttachment,
  getAttachmentFileUrl, PRIMARY_DIAGNOSIS_CATEGORIES,
} from "@/services/assessmentService";
import { coerceBiochemicalValue } from "@/services/biochemical";
import { deriveRiskScore, RISK_FACTORS, scoreRiskFactors } from "@/lib/assessmentRiskScoring";
import { buildAssessmentSummary, type AssessmentSummaryInput } from "@/lib/assessmentSummary";
import { fetchIntervention } from "@/services/interventionService";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { DatePicker, DateTimePicker } from "@/components/ui/DatePicker";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import { CharacterCountListInput } from "@/components/ui/CharacterCountListInput";
import { FittedImageFrame } from "@/components/ui/ImageUploadGallery";
import { InfoHint } from "@/components/ui/InfoHint";
import { NcpBreadcrumb } from "@/components/ncp/NcpBreadcrumb";
import { validateUploadFile } from "@/lib/uploadValidation";
import { ASSESSMENT_TEXT_LIMITS, FOOD_DISLIKE_LIMIT, FOOD_DISLIKE_MAX_ENTRIES, MEDICATION_LIMIT, ABG_LIMIT, assessmentTextError } from "@/lib/assessmentValidation";
import { NcpSectionTabs } from "@/components/ncp/NcpSectionTabs";

// ─── Constants ───────────────────────────────────────────────────────────
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];
const ASSESSMENT_FIELD_LABELS: Record<string, string> = {
  weight: "Weight",
  usual_weight: "Usual weight",
  dry_weight_kg: "Dry weight",
  height: "Height",
  physical_activity_level: "Physical activity level",
  primary_diagnosis_category: "Nutrition care category",
  primary_diagnosis_other: "Specified nutrition care category",
};

function formatAssessmentValidationError(error: AssessmentValidationError): string {
  return Object.entries(error.errors).map(([field, messages]) =>
    `${field.startsWith('food_recall') ? 'Food Recall' : ASSESSMENT_FIELD_LABELS[field] ?? field.replace(/_/g, " ")}: ${messages.join(" ")}`,
  ).join(" ") || error.message;
}

export type ScreeningDraft = {
  firstName: string;
  lastName: string;
  dob: string;
  age: string;
  sex: string;
  address: string;
  height: string;
  weight: string;
  diagnosis: string;
  dietPrescription: string;
  referredBy: string;
  referralDatetime: string;
  screeningType: "adult" | "pediatric";
};

function getLabRange(field: typeof LAB_FIELDS[number], sex: "Male" | "Female") {
  if (field.sexDiff) {
    return sex === "Male"
      ? { low: field.lowM ?? null, high: field.highM ?? null }
      : { low: field.lowF ?? null, high: field.highF ?? null };
  }
  return { low: field.low ?? null, high: field.high ?? null };
}

function getLabStatus(value: number, field: typeof LAB_FIELDS[number], sex: "Male" | "Female"): "low" | "high" | "normal" {
  const { low, high } = getLabRange(field, sex);
  if (low !== null && value < low) return "low";
  if (high !== null && value > high) return "high";
  return "normal";
}

const LAB_FIELDS = [
  { key: "albumin", label: "Albumin", unit: "g/dL", sexDiff: false, low: 3.5, high: 5.5, note: "< 3.5 hypoalbuminemia; < 2.5 severe. Elderly may trend lower." },
  { key: "hemoglobin", label: "Hemoglobin", unit: "g/dL", sexDiff: true, lowM: 13.5, highM: 17.5, lowF: 12.0, highF: 15.5, note: "Below low = anemia. Elderly may have mild physiologic decline." },
  { key: "hematocrit", label: "Hematocrit", unit: "%", sexDiff: true, lowM: 41, highM: 53, lowF: 36, highF: 46, note: null },
  { key: "glucose", label: "Fasting Blood Sugar", unit: "mg/dL", sexDiff: false, low: 70, high: 99, note: "100–125 pre-DM; ≥ 126 DM" },
  { key: "hba1c", label: "HbA1c", unit: "%", sexDiff: false, low: null, high: 5.6, note: "5.7–6.4 pre-DM; ≥ 6.5 DM" },
  { key: "bun", label: "BUN", unit: "mg/dL", sexDiff: false, low: 7, high: 18, note: "7–18 adults; up to 23 normal in elderly (70+). High-normal in elderly may still signal renal decline." },
  { key: "creatinine", label: "Creatinine", unit: "mg/dL", sexDiff: true, lowM: 0.7, highM: 1.2, lowF: 0.5, highF: 0.9, note: "Elderly (70+): M 0.9–1.3, F 0.7–1.1. High-normal with low muscle mass may mask renal dysfunction." },
  { key: "sodium", label: "Sodium", unit: "mEq/L", sexDiff: false, low: 136, high: 145, note: "< 136 hyponatremia; > 145 hypernatremia" },
  { key: "potassium", label: "Potassium", unit: "mEq/L", sexDiff: false, low: 3.5, high: 5.1, note: "< 3.0 critical low; > 6.0 critical high" },
  { key: "calcium", label: "Calcium", unit: "mg/dL", sexDiff: false, low: 8.7, high: 10.3, note: "< 8.7 hypocalcemia; > 10.3 hypercalcemia" },
  { key: "phosphate", label: "Phosphate", unit: "mg/dL", sexDiff: false, low: 2.5, high: 4.5, note: "< 2.5 hypophosphatemia" },
  { key: "cholesterol", label: "Total Cholesterol", unit: "mg/dL", sexDiff: false, low: null, high: 200, note: "< 200 desirable; 200–239 borderline; ≥ 240 high" },
  { key: "ldl", label: "LDL", unit: "mg/dL", sexDiff: false, low: null, high: 100, note: "< 100 optimal; 100–129 near optimal; ≥ 130 borderline high" },
  { key: "hdl", label: "HDL", unit: "mg/dL", sexDiff: true, lowM: 40, highM: null, lowF: 50, highF: null, note: "< 40 (M) or < 50 (F) = risk factor; > 60 protective" },
  { key: "triglycerides", label: "Triglycerides", unit: "mg/dL", sexDiff: false, low: null, high: 150, note: "150–199 borderline; 200–499 high; ≥ 500 very high" },
  { key: "urr", label: "URR", unit: "%", sexDiff: false, low: 65, high: null, note: "≥ 65% indicates adequate dialysis" },
  { key: "abg", label: "ABG (pH)", unit: "", sexDiff: false, low: 7.35, high: 7.45, note: "< 7.35 acidosis; > 7.45 alkalosis" },
];

const ADULT_CLINICAL_CONDITIONS = [
  "Admission to ICU",
  "Anorexia Nervosa / Bulimia Nervosa",
  "Cachexia (temporal wasting, muscle wasting, cancer, cardiac)",
  "Cerebrovascular accident",
  "Coma",
  "Diabetes Mellitus / Gestational Diabetes Mellitus",
  "Gastrointestinal disease or complication",
  "Liver disease",
  "Malabsorption (celiac sprue, ulcerative colitis, Crohn's disease, short bowel syndrome)",
  "Multiple trauma (closed head injury, pressure injury)",
  "Non-healing wounds",
  "On tube feeding / parenteral nutrition",
  "Renal disease (acute, chronic, undergoing dialysis)",
  "Sepsis",
  "Serum albumin <3.5 gm/L",
];

const PEDIATRIC_CLINICAL_CONDITIONS = [
  "Admission to ICU",
  "Anorexia Nervosa / Bulimia Nervosa",
  "Cachexia (temporal wasting, muscle wasting, cancer, cardiac)",
  "Cerebrovascular accident",
  "Coma",
  "Congenital anomalies (e.g. Down's Syndrome, Craniofacial anomalies, Spina bifida, Hydrocephalus, Chiari Malformation)",
  "Diabetes Mellitus / Gestational Diabetes Mellitus",
  "Gastrointestinal disease or complication / impending GI surgery (e.g. Pancreatitis, Inflammatory Bowel Disease, GERD, Malabsorption conditions, Crohn's Disease)",
  "Inborn errors of metabolism",
  "Inflammatory diseases (e.g. Sepsis, Encephalitis, Meningitis, Kawasaki Disease, Enterocolitis, Community-acquired pneumonia, Upper/Lower Respiratory Tract Infection)",
  "Liver disease",
  "Malabsorption (celiac sprue, ulcerative colitis, Crohn's disease, short bowel syndrome)",
  "Multiple trauma (closed head injury, penetrating trauma, multiple fractures)",
  "Neurologically challenged (e.g. ADHD, Cerebral palsy, seizure disorders, Infantile spasms)",
  "On tube feeding / parenteral nutrition",
  "Renal disease (acute, chronic, undergoing dialysis)",
  "Sepsis",
  "Serum albumin <3.5 gm/L",
];

const ADULT_INTAKE_WEIGHT_HISTORY = [
  "Recent unintentional weight loss",
  "Reduced dietary intake in the past week",
  "BMI below 18.5 and above 30 (to be computed by the RND)",
  "Others",
  "Pregnant patient is aged 18 years old or 35 years old",
  "Pregnancy with Hyperemesis Gravidarum / Pregnancy-induced Hypertension",
  "Multiple Pregnancy",
  "Lactating Mother",
];

const PEDIATRIC_INTAKE_WEIGHT_HISTORY = [
  "Recent unintentional weight loss",
  "Patient on breastmilk feeding",
  "Reduced dietary intake in the past week",
  "Reduction of dietary intake in the past week/s and/or during the hospital stay",
  "For patients ages >5 years old to <18 years old, 364 days: BMI z-scores above +2 and below -2 (c/o RND)",
  "For patients ages >2 to 5 years old: Weight for Height z-scores above +2 and below -2 (c/o RND)",
  "For patients ages 1 month to 2 years old: Weight for Length z-scores above +2 and below -2 (c/o RND)",
  "Others",
];



const TABS = [
  { key: "dietary", label: "A: Dietary", icon: Utensils },
  { key: "anthropometric", label: "B: Anthropometrics", icon: Ruler },
  { key: "client", label: "C: Client", icon: UserRound },
  { key: "biochemical", label: "D: Biochemical", icon: FlaskConical },
  { key: "referral", label: "E: Referral", icon: FileText },
  { key: "summary", label: "F: Summary", icon: Sparkles },
] as const;

type TabKey = (typeof TABS)[number]["key"];
type ReferralSection = "details" | "conditions" | "intake";

const REFERRAL_SECTIONS: Array<{ key: ReferralSection; label: string }> = [
  { key: "details", label: "Referral details" },
  { key: "conditions", label: "Clinical conditions" },
  { key: "intake", label: "Intake / weight history" },
];

const ENERGY_INTAKE_OPTIONS = [
  "No change", "Mostly liquids", "Sub-optimal", "Starvation", "Poor intake prior to admission"
];
const FUNCTIONAL_OPTIONS = ["Independent", "Needs assistance", "Bedridden"];

// ─── Helpers ─────────────────────────────────────────────────────────────
function riskBadge(score: number) {
  if (score > 3) return { label: "High Risk", color: "bg-red-50 text-red-700 border-red-200" };
  if (score >= 2) return { label: "Moderate", color: "bg-amber-50 text-amber-700 border-amber-200" };
  return { label: "Low Risk", color: "bg-emerald-50 text-emerald-700 border-emerald-200" };
}

function calcBmi(w: number | null, h: number | null): number | null {
  if (!w || !h || h <= 0) return null;
  const hm = h / 100;
  return Math.round((w / (hm * hm)) * 100) / 100;
}

function formatDate(value?: string) {
  if (!value) return "";
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? "" : d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
}

function getScreeningConditions(type: "adult" | "pediatric") {
  return type === "pediatric" ? PEDIATRIC_CLINICAL_CONDITIONS : ADULT_CLINICAL_CONDITIONS;
}

function getScreeningIntakeHistory(type: "adult" | "pediatric") {
  return type === "pediatric" ? PEDIATRIC_INTAKE_WEIGHT_HISTORY : ADULT_INTAKE_WEIGHT_HISTORY;
}

function isImagePath(path?: string) {
  return !!path && /\.(png|jpe?g|gif|webp)$/i.test(path);
}

// ─── Field Components ────────────────────────────────────────────────────
function Field({ label, children, span, required, htmlFor, className = "" }: {
  label: string;
  children: React.ReactNode;
  span?: number;
  required?: boolean;
  htmlFor?: string;
  className?: string;
}) {
  return (
    <div data-assessment-field className={`${span ? `col-span-${span}` : ""} ${className}`} style={span ? { gridColumn: `span ${span}` } : undefined}>
      <label htmlFor={htmlFor} className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1.5">
        {label}
        {required && <span className="ml-0.5 text-red-500" aria-hidden="true">*</span>}
      </label>
      {children}
    </div>
  );
}
function AssessmentSection({ legend, children, className = "" }: {
  legend: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <fieldset className={`min-w-0 space-y-3 ${className}`}>
      <legend className="text-xs font-extrabold uppercase tracking-wider text-warm-500">
        {legend}
      </legend>
      {children}
    </fieldset>
  );
}

function TextInput({ value, onChange, type, disabled, min, max, id, className, ariaLabel, maxLength, step }: {
  value: string; onChange: (v: string) => void; type?: string; disabled?: boolean;
  min?: number; max?: number; id?: string; className?: string; ariaLabel?: string; maxLength?: number; step?: number;
}) {
  return (
    <input
      type={type ?? "text"}
      id={id}
      value={value}
      onChange={e => onChange(e.target.value)}
      disabled={disabled}
      min={min}
      max={max}
      step={step}
      aria-label={ariaLabel}
      maxLength={maxLength}
      className={`min-h-11 w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all disabled:bg-warm-50 disabled:cursor-not-allowed ${className ?? ""}`}
    />
  );
}

function TextArea({ value, onChange, rows, maxLength = 250, ariaLabel, id }: {
  value: string; onChange: (v: string) => void; rows?: number; maxLength?: number; ariaLabel?: string; id?: string;
}) {
  return (
    <CharacterCountTextarea
      id={id}
      value={value}
      onChange={onChange}
      rows={rows ?? 3}
      maxLength={maxLength}
      aria-label={ariaLabel}
      autoGrow
      className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm text-warm-900 transition-colors placeholder:text-warm-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
    />
  );
}

function SelectInput({ value, onChange, options }: {
  value: string; onChange: (v: string) => void; options: { value: string; label: string }[];
}) {
  return (
    <select
      value={value}
      onChange={e => onChange(e.target.value)}
      className="min-h-11 w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer"
    >
      <option value="" />
      {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  );
}


function DropZone({ label, onUpload, uploading }: {
  label: string; onUpload: (file: File) => void; uploading: boolean;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  const [validationError, setValidationError] = useState<string | null>(null);

  const handleFile = (file: File) => {
    const validation = validateUploadFile(file, {
      maxBytes: 10 * 1024 * 1024,
      allowedTypes: ["application/pdf", "image/jpeg", "image/png"],
    });
    if (!validation.valid) {
      setValidationError(validation.error);
      return;
    }
    setValidationError(null);
    onUpload(file);
  };

  return (
    <div
      className={`relative border-2 border-dashed rounded-xl p-6 text-center transition-all cursor-pointer select-none ${dragOver ? "border-emerald-400 bg-emerald-50/50" : "border-warm-300 hover:border-zinc-400 bg-warm-50/30"
        }`}
      onClick={() => inputRef.current?.click()}
      onDragOver={e => { e.preventDefault(); setDragOver(true); }}
      onDragLeave={() => setDragOver(false)}
      onDrop={e => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); }}
    >
      <input ref={inputRef} type="file" accept=".pdf,.png,.jpg,.jpeg" className="hidden" onChange={e => { if (e.target.files?.[0]) handleFile(e.target.files[0]); }} />
      {uploading ? (
        <div className="space-y-2">
          <div className="h-1 w-48 mx-auto bg-warm-200 rounded-full overflow-hidden">
            <div className="h-full bg-emerald-500 rounded-full animate-pulse" style={{ width: "70%" }} />
          </div>
          <p className="text-xs font-bold text-emerald-700 uppercase tracking-wider">Uploading...</p>
        </div>
      ) : (
        <>
          <Upload className="h-5 w-5 text-warm-400 mx-auto mb-2" />
          <p className="text-xs font-bold text-warm-600 uppercase tracking-wider">{label}</p>
          <p className="text-xs text-warm-400 mt-1">Drag and drop or click to upload — PDF, JPEG, PNG (max 10MB)</p>
        </>
      )}
      {validationError && <p role="alert" className="mt-2 text-xs font-semibold text-red-700">{validationError}</p>}
    </div>
  );
}

// ─── Kind → display name map ───────────────────────────────────────────────
const KIND_LABELS: Record<string, string> = {
  labs: "Biochemical Data",
  referral: "Screening Form",
};

function getKindLabel(kind: string) {
  return KIND_LABELS[kind] ?? kind;
}

// ─── Attachment Lightbox ────────────────────────────────────────────────────
function AttachmentLightbox({
  url, isImage, name, onClose, onDelete,
}: {
  url: string; isImage: boolean; name: string; onClose: () => void; onDelete: () => void;
}) {
  const [confirming, setConfirming] = useState(false);

  // Close on Escape
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  function handleDeleteClick() {
    if (!confirming) { setConfirming(true); return; }
    onDelete();
    onClose();
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      style={{ backdropFilter: "blur(8px)", WebkitBackdropFilter: "blur(8px)", backgroundColor: "rgba(0,0,0,0.55)" }}
      onClick={onClose}
    >
      {/* Modal card — stop propagation so clicking inside doesn't close */}
      <div
        className="relative bg-white rounded-2xl shadow-2xl overflow-hidden w-full max-w-2xl max-h-[90vh] flex flex-col"
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-warm-100">
          <p className="text-sm font-bold text-warm-700 truncate pr-4">{name}</p>
          <div className="flex items-center gap-2 shrink-0">
            <a
              href={url}
              download
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors"
            >
              <Download className="h-3 w-3" />
              Download
            </a>
            <button
              type="button"
              onClick={handleDeleteClick}
              onBlur={() => setConfirming(false)}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors ${
                confirming
                  ? "bg-red-600 hover:bg-red-700 text-white"
                  : "bg-warm-100 hover:bg-red-50 text-warm-500 hover:text-red-600"
              }`}
              title="Remove attachment"
            >
              <Trash2 className="h-3 w-3" />
              {confirming ? "Confirm?" : "Remove"}
            </button>
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
        {/* Content */}
        <div className="flex-1 overflow-auto bg-warm-50 flex items-center justify-center min-h-0">
          {isImage ? (
            <FittedImageFrame src={url} alt={name} variant="viewer" />
          ) : (
            <iframe
              src={url}
              title={name}
              className="w-full h-[75vh] border-0"
            />
          )}
        </div>
      </div>
    </div>
  );
}

// ─── Attachments Panel ─────────────────────────────────────────────────────
// Plain supporting-document storage for this NCP cycle (rnd.md §3.1). No OCR.
// Reused on the Labs and Referral/Screening tabs; both list the cycle's files.
function AttachmentsPanel({ ncpId, uploadLabel, kind, blurb }: {
  ncpId: string; uploadLabel: string; kind: string; blurb: string;
}) {
  const [items, setItems] = useState<AttachmentRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [lightbox, setLightbox] = useState<{ url: string; isImage: boolean; name: string; docId: number } | null>(null);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);

  const displayName = getKindLabel(kind);

  const load = useCallback(async () => {
    try {
      setError(null);
      const result = await fetchAttachments(ncpId, kind, page);
      setItems(result.data);
      setMeta(result.meta);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load attachments.");
    } finally {
      setLoading(false);
    }
  }, [ncpId, kind, page]);

  useEffect(() => { void load(); }, [load]);

  async function handleUpload(file: File) {
    setUploading(true);
    setError(null);
    try {
      await uploadAttachment(ncpId, file, kind);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to upload attachment.");
    } finally {
      setUploading(false);
    }
  }

  async function handleDelete(id: number) {
    setDeletingId(id);
    try {
      await deleteAttachment(id);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to delete attachment.");
    } finally {
      setDeletingId(null);
    }
  }

  return (
    <>
      {/* Lightbox portal */}
      {lightbox && (
        <AttachmentLightbox
          url={lightbox.url}
          isImage={lightbox.isImage}
          name={lightbox.name}
          onClose={() => setLightbox(null)}
          onDelete={() => handleDelete(lightbox.docId)}
        />
      )}

      <details className="group rounded-xl border border-warm-200 bg-warm-50/60">
        <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 text-xs font-extrabold uppercase tracking-wider text-warm-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500">
          <span className="flex items-center gap-2">
            <Paperclip className="h-4 w-4 text-emerald-600" />
            Supporting documents
          </span>
          <span className="font-semibold normal-case tracking-normal text-warm-500">
            {loading ? "Loading…" : `${items.length} attached`}
          </span>
        </summary>
        <div className="space-y-3 border-t border-warm-200 p-4">
          <p className="text-xs text-warm-500 leading-relaxed">{blurb}</p>
          <DropZone label={uploadLabel} onUpload={handleUpload} uploading={uploading} />

          {error && (
            <div className="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700 font-semibold">{error}</div>
          )}

          {loading ? (
            <div className="space-y-2">
              {[0, 1].map(i => <div key={i} className="h-12 bg-warm-100 rounded-xl animate-pulse" />)}
            </div>
          ) : items.length === 0 ? (
            <div className="rounded-xl border border-dashed border-warm-200 py-4 text-center text-xs font-semibold text-warm-400">
              No documents attached to this cycle yet.
            </div>
          ) : (
            <div className="space-y-3">
              {items.map((doc, idx) => {
              const isImg = isImagePath(doc.file_path);
              const fileUrl = getAttachmentFileUrl(doc.id);
              const docName = `${displayName} ${items.length > 1 ? idx + 1 : ""}`.trim();
              return (
                <div key={doc.id} className="bg-white border border-warm-200 rounded-xl overflow-hidden">
                  <div className="flex items-center gap-3 px-3 py-2.5">
                    <div className="h-9 w-9 shrink-0 rounded-lg bg-warm-50 border border-warm-200 flex items-center justify-center text-warm-400">
                      {isImg ? <Eye className="h-4 w-4" /> : <FileText className="h-4 w-4" />}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-semibold text-warm-800 truncate">{docName}</p>
                      <p className="text-xs text-warm-400">
                        {doc.type ? `${doc.type} · ` : ""}{formatDate(doc.created_at)}
                      </p>
                    </div>
                    {/* Preview / View button */}
                    <button
                      type="button"
                      onClick={() => setLightbox({ url: fileUrl, isImage: isImg, name: docName, docId: doc.id })}
                      className="p-1.5 text-warm-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                      title="Preview"
                    >
                      <Eye className="h-3.5 w-3.5" />
                    </button>
                    {/* Download button */}
                    <a
                      href={fileUrl}
                      download={docName}
                      className="p-1.5 text-warm-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors"
                      title="Download"
                    >
                      <Download className="h-3.5 w-3.5" />
                    </a>
                    {/* Delete button */}
                    <button
                      type="button"
                      onClick={() => handleDelete(doc.id)}
                      disabled={deletingId === doc.id}
                      className="p-1.5 text-warm-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors disabled:opacity-50"
                      title="Remove"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              );
              })}
            </div>
          )}
          <Pagination meta={meta} page={page} onPageChange={setPage} />
        </div>
      </details>
    </>
  );
}

// ─── Default Assessment State ────────────────────────────────────────────
function defaultAssessment(): Assessment {
  return {
    dietary_intake: null, appetite_changes: null, dietary_restrictions: null,
    supplements: null, knowledge_notes: null, weight: null, height: null,
    bmi: null, body_composition: null, medical_history: null, social_history: null,
    lifestyle: null, allergies: null, food_dislikes: null, medications: null,
    rnd_summary: null, usual_weight: null, nutritional_status: null,
    weight_loss_percentage: null, weight_change_period_value: null, weight_change_period_unit: null,
    primary_diagnosis_category: null, primary_diagnosis_other: null,
    functional_assessment: null, energy_intake_status: null, ibw_percentage: null,
    present_diet: null, physical_assessment: null,
    chewing_swallowing_difficulties: null, chewing_swallowing_details: null,
    constipation: null, constipation_details: null,
    diarrhea_notes: null, diarrhea_details: null,
    food_intolerance: null, nutrient_drug_interaction: null,
    dietary_intake_method: null, dietary_record_file: null,
    physical_activity_level: null, muac_mm: null, waist_cm: null, hip_cm: null,
    edema_present: false, dry_weight_kg: null, pregnancy_lactation_status: "none",
    risk_score_manual_override: false, risk_score_manual_factors: null,
    religion: null,
  };
}

// ─── Main Component ──────────────────────────────────────────────────────
export default function NcpAssessmentPage({
  params,
}: {
  params: Promise<{ patientId: string; ncpId: string }>;
}) {
  const resolvedParams = use(params);
  const { patientId, ncpId } = resolvedParams;

  const [activeTab, setActiveTab] = useState<TabKey>("dietary");
  const [patient, setPatient] = useState<Patient | null>(null);
  const [assessment, setAssessment] = useState<Assessment>(defaultAssessment());
  const recallEditor = useRef<FoodRecallHandle>(null);
  const acceptedRecall = useRef<FoodRecallPlan | null>(null);
  const [recallDirty, setRecallDirty] = useState(false);
  const markAssessmentSaved = useRef<(snapshot: string) => void>(() => {});
  const [interventionGoal, setInterventionGoal] = useState<string | null>(null);
  const [assessmentExists, setAssessmentExists] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [screeningDraft, setScreeningDraft] = useState<ScreeningDraft | null>(null);
  const [sectionAChecks, setSectionAChecks] = useState<boolean[]>([]);
  const [sectionBChecks, setSectionBChecks] = useState<boolean[]>([]);
  const [referralSection, setReferralSection] = useState<ReferralSection>("details");
  const [summaryBaseline, setSummaryBaseline] = useState<string | null>(null);
  const [lastGeneratedSummary, setLastGeneratedSummary] = useState<string | null>(null);
  const [summaryUndo, setSummaryUndo] = useState<string | null>(null);
  const [summaryNotice, setSummaryNotice] = useState<string | null>(null);
  const summaryBaselineNcpId = useRef<string | null>(null);

  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const buildScreeningDraft = useCallback((basePatient: Patient, baseAssessment?: Assessment | null): ScreeningDraft => {
    return {
      firstName: basePatient.first_name ?? "",
      lastName: basePatient.last_name ?? "",
      dob: formatDateInputValue(basePatient.dob),
      age: formatPatientAge(basePatient.dob),
      sex: basePatient.sex,
      address: basePatient.address ?? "",
      height: String(baseAssessment?.height ?? ""),
      weight: String(baseAssessment?.weight ?? ""),
      diagnosis: basePatient.medical_diagnosis ?? "",
      dietPrescription: "",
      referredBy: basePatient.physician ?? "",
      referralDatetime: "",
      screeningType: basePatient.screening_type === "pediatric" ? "pediatric" : "adult",
    };
  }, []);

  const updateScreeningDraftField = (key: keyof ScreeningDraft, value: string) => {
    setScreeningDraft(current => {
      if (!current) return null;
      if (key === "dob") {
        return { ...current, dob: value, age: formatPatientAge(value) };
      }
      return { ...current, [key]: value };
    });
  };

  // ─── Load Data ──────────────────────────────────────────────────────
  const [loadError, setLoadError] = useState<string | null>(null);
  const loadRequest = useRef(0);
  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    const request = ++loadRequest.current;
    try {
      setLoading(true);
      setLoadError(null);
      const [p, a, intervention] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchAssessment(ncpId),
        fetchIntervention(ncpId),
      ]);
      if (request !== loadRequest.current) return;
      const missingAssessment = a.status === "rejected" && a.reason?.status === 404;
      if (p.status === "rejected" || (a.status === "rejected" && !missingAssessment) || intervention.status === "rejected") {
        setLoadError("Could not load assessment and care context. Try again before editing.");
        return;
      }
      let loadedPatient: Patient | null = null;
      let loadedAssessment: Assessment | null = null;

      if (p.status === "fulfilled") {
        setPatient(p.value);
        loadedPatient = p.value;
      }
      if (a.status === "fulfilled") {
        setAssessment(a.value);
        acceptedRecall.current = a.value.food_recall ?? null;
        setAssessmentExists(true);
        loadedAssessment = a.value;
      } else if (missingAssessment) {
        setAssessment(defaultAssessment());
        setAssessmentExists(false);
        acceptedRecall.current = null;
      }
      if (intervention.status === "fulfilled") {
        setInterventionGoal(intervention.value?.goal_type ?? null);
      }

      if (loadedPatient) {
        const draft = buildScreeningDraft(loadedPatient, loadedAssessment);
        setScreeningDraft(draft);
        setSectionAChecks(new Array(getScreeningConditions(draft.screeningType).length).fill(false));
        setSectionBChecks(new Array(getScreeningIntakeHistory(draft.screeningType).length).fill(false));
      }
    } catch {
      if (request === loadRequest.current) setLoadError("Could not load assessment. Try again before editing.");
    } finally {
      if (request === loadRequest.current) setLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder, buildScreeningDraft]);

  useEffect(() => { const counter = loadRequest; void loadData(); return () => { counter.current++; }; }, [loadData]);

  // ─── BMI Auto-Calc ──────────────────────────────────────────────────
  const weight = Number(assessment.weight) || 0;
  const height = Number(assessment.height) || 0;

  // ─── Auto-Calc Panel Derived Values ────────────────────────────────
  const patientSex = (patient?.sex as "Male" | "Female") ?? "Male";
  const patientDobStr = screeningDraft?.dob || patient?.dob || null;
  const assessmentAge = getPatientAgeParts(patientDobStr, assessment.bmi_reference_date ? new Date(`${assessment.bmi_reference_date}T00:00:00`) : new Date());
  const patientAgeYears = assessmentAge?.years ?? 0;
  const adultEstimatesSupported = assessmentAge !== null && assessmentAge.totalMonths > 228 && (patient?.sex === 'Male' || patient?.sex === 'Female');
  const computedBmi = assessmentAge !== null && assessmentAge.totalMonths >= 60 ? calcBmi(weight || null, height || null) : null;

  const computedIBW = adultEstimatesSupported && weight > 0 && height > 0 ? calcIBW(height, patientSex) : null;
  const computedPercentIBW = computedIBW !== null && weight > 0 ? calcPercentIBW(weight, computedIBW) : null;
  const palKey = assessment.physical_activity_level ?? "sedentary";
  const palFactor = ACTIVITY_FACTORS[palKey]?.factor ?? 1.2;
  const computedBmrWt = computedIBW !== null && weight > 0 ? calcBmrWeight(weight, computedIBW) : null;
  const computedBMR = computedBmrWt !== null
    ? calcBMR(computedBmrWt, height, patientAgeYears, patientSex)
    : null;
  const computedTEE = computedBMR !== null ? Math.round(calcTEE(computedBMR, palFactor)) : null;
  const computedBmiClassification = computedBmi !== null
    ? classifyBmi(computedBmi, assessmentAge?.years ?? null, assessment.edema_present ?? false, assessment.pregnancy_lactation_status ?? 'none', patient?.sex, assessmentAge?.totalMonths) : null;
  const { ratio: computedWHR, risk: whrRisk, error: whrError } = getWaistHipAssessment({
    waistCm: assessment.waist_cm, hipCm: assessment.hip_cm,
    sex: patient?.sex ?? null, ageYears: patientDobStr ? patientAgeYears : null,
    pregnancyLactationStatus: assessment.pregnancy_lactation_status,
  });

  // ─── Weight Loss % Auto-Calc ────────────────────────────────────────
  const usualWeight = Number(assessment.usual_weight) || 0;
  const computedWeightChangePct = usualWeight > 0 && weight > 0
    ? Math.round((Math.abs(usualWeight - weight) / usualWeight) * 1000) / 10
    : null;
  const weightChangeDirection = computedWeightChangePct !== null
    ? weight < usualWeight
      ? "loss"
      : weight > usualWeight
        ? "gain"
        : "stable"
    : null;
  const computedWeightLossPct = weightChangeDirection === "loss" ? computedWeightChangePct : null;
  const displayWeightChangePct = computedWeightChangePct ?? assessment.weight_loss_percentage ?? null;
  const displayWeightChangeLabel = weightChangeDirection === "loss"
    ? "Weight loss"
    : weightChangeDirection === "gain"
      ? "Weight gain"
      : weightChangeDirection === "stable"
        ? "Stable weight"
        : "Weight change";
  const riskWeightLossPct = computedWeightLossPct ?? (usualWeight > 0 && weight > 0 ? null : assessment.weight_loss_percentage ?? null);

  // ─── MUAC Classification ────────────────────────────────────────────
  const muacValue = Number(assessment.muac_mm) || 0;
  const muacClassification = getMuacScreening(muacValue, getPatientAgeParts(patientDobStr, assessment.bmi_reference_date ? new Date(`${assessment.bmi_reference_date}T00:00:00`) : new Date())?.totalMonths ?? null);

  // ─── Risk Score (live, derived from current form state) ────────────
  const riskResult = deriveRiskScore({
    screeningType: patient?.screening_type ?? null,
    ibwPercentage: computedPercentIBW ?? assessment.ibw_percentage ?? null,
    weightLossPercentage: riskWeightLossPct,
    chewingSwallowingDifficulties: assessment.chewing_swallowing_difficulties,
    constipation: assessment.constipation,
    diarrheaNotes: assessment.diarrhea_notes,
    foodIntolerance: assessment.food_intolerance,
    nutrientDrugInteraction: assessment.nutrient_drug_interaction,
    lifestyle: assessment.lifestyle,
    biochemicalData: assessment.biochemical_data ?? null,
    sex: patientSex,
  });
  const riskManualOverride = assessment.risk_score_manual_override === true;
  const manualRiskFactors = assessment.risk_score_manual_factors ?? [];
  const effectiveRiskFactors = riskManualOverride ? manualRiskFactors : riskResult.checkedFactors;
  const riskChecks = RISK_FACTORS.map((f) => effectiveRiskFactors.includes(f.key));
  const riskScore = riskManualOverride ? scoreRiskFactors(manualRiskFactors) : riskResult.score;
  const riskInfo = riskBadge(riskScore);
  const computedRiskInfo = riskBadge(riskResult.score);

  const summaryInput: AssessmentSummaryInput = {
    assessment,
    referral: {
      diagnosis: screeningDraft?.diagnosis ?? patient?.medical_diagnosis,
      dietPrescription: screeningDraft?.dietPrescription,
    },
    anthropometrics: {
      bmi: computedBmi,
      nutritionalStatus: computedBmiClassification?.severity === 'unknown' ? null : computedBmiClassification?.label,
    },
    risk: riskScore > 0 || effectiveRiskFactors.length > 0 ? { label: riskInfo.label } : null,
  };
  const summaryCandidate = buildAssessmentSummary(summaryInput);

  useEffect(() => {
    if (loading || isPlaceholder || summaryBaselineNcpId.current === ncpId) return;
    summaryBaselineNcpId.current = ncpId;
    setSummaryBaseline(summaryCandidate);
    setLastGeneratedSummary(null);
    setSummaryUndo(null);
    setSummaryNotice(null);
  }, [isPlaceholder, loading, ncpId, summaryCandidate]);

  // ─── Field Updater ──────────────────────────────────────────────────
  const updateField = (key: keyof Assessment, value: unknown) => {
    setAssessment(prev => ({ ...prev, [key]: value }));
  };

  const enableManualRiskScore = () => {
    setAssessment(prev => ({
      ...prev,
      risk_score_manual_override: true,
      risk_score_manual_factors: prev.risk_score_manual_factors ?? riskResult.checkedFactors,
    }));
  };

  const resetAutomaticRiskScore = () => {
    setAssessment(prev => ({
      ...prev,
      risk_score_manual_override: false,
      risk_score_manual_factors: null,
    }));
  };

  const toggleManualRiskFactor = (factorKey: string, checked: boolean) => {
    setAssessment(prev => {
      const baseFactors = prev.risk_score_manual_override
        ? prev.risk_score_manual_factors ?? []
        : riskResult.checkedFactors;
      const nextFactors = checked
        ? Array.from(new Set([...baseFactors, factorKey]))
        : baseFactors.filter((key) => key !== factorKey);

      return {
        ...prev,
        risk_score_manual_override: true,
        risk_score_manual_factors: nextFactors,
      };
    });
  };

  const s = (key: keyof Assessment): string => (assessment[key] as string) ?? "";

  const summaryIsStale = summaryBaseline !== null
    && summaryCandidate !== summaryBaseline
    && s("rnd_summary").trim().length > 0;
  const summaryWasEdited = lastGeneratedSummary !== null && s("rnd_summary") !== lastGeneratedSummary;

  const handleGenerateSummary = () => {
    if (!summaryCandidate) {
      setSummaryNotice("Add assessment details before generating a summary.");
      return;
    }
    const previousSummary = s("rnd_summary");
    setSummaryUndo(previousSummary.trim() ? previousSummary : null);
    updateField("rnd_summary", summaryCandidate);
    setSummaryBaseline(summaryCandidate);
    setLastGeneratedSummary(summaryCandidate);
    setSummaryNotice("Summary draft generated. Review before saving.");
  };

  const handleSummaryChange = (value: string) => {
    updateField("rnd_summary", value);
    setSummaryUndo(null);
    setSummaryNotice(null);
  };

  const handleUndoSummary = () => {
    if (summaryUndo === null) return;
    updateField("rnd_summary", summaryUndo);
    setLastGeneratedSummary(null);
    setSummaryUndo(null);
    setSummaryNotice("Previous summary restored.");
  };

  // ─── Save ───────────────────────────────────────────────────────────
  const handleSave = async (): Promise<boolean> => {
    if (recallEditor.current?.isDirty() && !await recallEditor.current.savePlan()) {
      setActiveTab('dietary');
      setError('Review the Food Recall calculation before saving.');
      return false;
    }
    if (!assessment.nutrition_support) { setError("Select Nutrition support before saving."); setActiveTab("dietary"); return false; }
    const validationError = assessmentTextError(assessment);
    if (validationError) {
      setError(validationError);
      setSuccess(null);
      return false;
    }
    try {
      setSaving(true);
      setError(null);
      setSuccess(null);

      const safetyWarning = getAnthropometricSafetyWarning({
        dob: screeningDraft?.dob ?? patient?.dob,
        weightKg: assessment.weight,
        heightCm: assessment.height,
      });
      if (safetyWarning && !window.confirm(safetyWarning)) {
        return false;
      }
      const nameFields = patient && screeningDraft
        ? changedPersonNameFields(patient, screeningDraft.firstName, screeningDraft.lastName)
        : null;

      const toSave: Partial<Assessment> = {
        ...assessment,
        food_recall: acceptedRecall.current,
        medications: assessment.medications?.length ? [assessment.medications.join(", ")] : [],
        food_dislikes: (assessment.food_dislikes ?? []).map(value => value.trim()).filter(Boolean),
        // Auto-computed fields — override stored value with live computation when available
        bmi: computedBmi,
        ibw_percentage: computedPercentIBW ?? assessment.ibw_percentage,
        weight_loss_percentage: computedWeightChangePct ?? assessment.weight_loss_percentage,
        nutritional_status: riskScore > 3 ? "Severe Malnutrition" : riskScore >= 2 ? "Moderate Malnutrition" : "Normal",
      };
      // Remove read-only fields
      delete toSave.id;
      delete toSave.ncp_record_id;
      delete toSave.created_at;
      delete toSave.updated_at;

      const saved = await saveAssessment(ncpId, toSave, assessmentExists);
      setAssessment(saved);
      acceptedRecall.current = saved.food_recall ?? null;
      setAssessmentExists(true);

      // Save patient demographics if updated via screening form
      if (patient && screeningDraft) {
        const patientData: PatientUpdateData = {
          dob: screeningDraft.dob,
          sex: screeningDraft.sex as "Male" | "Female",
          address: screeningDraft.address,
          physician: screeningDraft.referredBy,
          medical_diagnosis: screeningDraft.diagnosis,
          screening_type: screeningDraft.screeningType,
          ...(nameFields ?? {}),
        };
        const updated = await updatePatient(patient.id, patientData);
        setPatient(updated);
      }

      setSuccess("Assessment saved successfully.");
      markAssessmentSaved.current(JSON.stringify({ assessment: saved, screeningDraft, recallDirty: false }));
      setTimeout(() => setSuccess(null), 3000);
      return true;
    } catch (err: unknown) {
      setError(err instanceof AssessmentValidationError
        ? formatAssessmentValidationError(err)
        : err instanceof Error ? err.message : "Failed to save assessment.");
      return false;
    } finally {
      setSaving(false);
    }
  };

  const assessmentGuard = useEditorChanges(JSON.stringify({ assessment, screeningDraft, recallDirty }), !loading && !isPlaceholder, handleSave);
  useEffect(() => { markAssessmentSaved.current = assessmentGuard.markSaved; }, [assessmentGuard.markSaved]);

  // ─── Loading / Placeholder ──────────────────────────────────────────
  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Assessment" />
        <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
          <ClipboardCheck className="h-8 w-8 text-warm-300 mx-auto mb-4" />
          <h3 className="text-base font-bold text-warm-800">No Patient Selected</h3>
          <p className="text-sm text-warm-500 mt-2">Return to the directory to select or create a patient.</p>
          <Link href="/ncp/patients" className="inline-flex mt-4 px-4 py-2 bg-forest-900 text-white text-xs font-bold uppercase tracking-wider rounded-lg hover:bg-forest-800 transition-colors">
            Go to Patients
          </Link>
        </div>
      </div>
    );
  }

  if (loadError && !loading) {
    return <div className="p-6 space-y-3"><p role="alert" className="text-sm text-red-700">{loadError}</p><Button onClick={() => { void loadData(); }}>Try again</Button></div>;
  }

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Assessment" />
        <div className="space-y-4">
          {[1, 2, 3].map(i => <div key={i} className="h-16 bg-warm-100 rounded-xl animate-pulse" />)}
        </div>
      </div>
    );
  }

  const allergies = assessment.allergies ?? [];

  // ─── Tab Renderers ──────────────────────────────────────────────────
  const renderDietaryTab = () => (
    <div className={`${layout.tab} space-y-5`}>
      <AssessmentSection legend="Diet and intake">
        <Field label="Nutrition support" htmlFor="nutrition_support" required>
          <select id="nutrition_support" aria-label="Nutrition support" required value={assessment.nutrition_support ?? ""} onChange={event => updateField("nutrition_support", event.target.value as Assessment["nutrition_support"])} className="min-h-11 w-full rounded-lg border border-warm-300 bg-white px-3 text-base">
            <option value="">Select nutrition support</option>
            {NUTRITION_SUPPORT_OPTIONS.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
          </select>
        </Field>
        <div className={layout.grid}>
      <Field label="Present Diet" htmlFor="present_diet">
        <TextArea id="present_diet" value={s("present_diet")} onChange={v => updateField("present_diet", v)} rows={2} maxLength={ASSESSMENT_TEXT_LIMITS.present_diet} />
      </Field>
      <Field label="Dietary Restrictions" htmlFor="dietary_restrictions">
        <TextArea id="dietary_restrictions" value={s("dietary_restrictions")} onChange={v => updateField("dietary_restrictions", v)} rows={2} maxLength={ASSESSMENT_TEXT_LIMITS.dietary_restrictions} />
      </Field>
      <Field label="Energy Intake Status">
        <SelectInput
          value={s("energy_intake_status")}
          onChange={v => updateField("energy_intake_status", v || null)}
          options={ENERGY_INTAKE_OPTIONS.map(o => ({ value: o, label: o }))}
        />
      </Field>
      <Field label="Appetite Changes">
        <SelectInput
          value={s("appetite_changes") ?? ""}
          onChange={v => updateField("appetite_changes", v || null)}
          options={[
            { value: "normal", label: "Normal appetite" },
            { value: "decreased", label: "Decreased — eating less than usual" },
            { value: "increased", label: "Increased — eating more than usual" },
            { value: "variable", label: "Variable / Inconsistent" },
            { value: "absent", label: "Absent / Anorexia" },
            { value: "early_satiety", label: "Early satiety" },
            { value: "nausea_vomiting", label: "Nausea / Vomiting affecting intake" },
          ]}
        />
      </Field>
        </div>
      </AssessmentSection>

      <AssessmentSection legend="Dietary modifiers">
        <div className={layout.grid}>
      <Field label="Supplements" htmlFor="supplements">
        <TextArea id="supplements" value={s("supplements")} onChange={v => updateField("supplements", v)} rows={2} maxLength={ASSESSMENT_TEXT_LIMITS.supplements} />
      </Field>
      <Field label="Nutrient-Drug Interaction" htmlFor="nutrient_drug_interaction">
        <TextArea id="nutrient_drug_interaction" value={s("nutrient_drug_interaction")} onChange={v => updateField("nutrient_drug_interaction", v)} rows={2} maxLength={ASSESSMENT_TEXT_LIMITS.nutrient_drug_interaction} />
      </Field>
      <Field label="Knowledge / Beliefs Notes" htmlFor="knowledge_notes" className={layout.fullWidth}>
        <TextArea id="knowledge_notes" value={s("knowledge_notes")} onChange={v => updateField("knowledge_notes", v)} rows={2} maxLength={ASSESSMENT_TEXT_LIMITS.knowledge_notes} />
      </Field>
        </div>
      </AssessmentSection>
      {/* GI / Tolerance — moved from Anthropometrics tab */}
      <AssessmentSection legend="GI / tolerance">
        <div className={layout.grid}>
          <Field label="Chewing / Swallowing Difficulties">
            <SelectInput
              value={["Yes", "No"].includes(s("chewing_swallowing_difficulties")) ? s("chewing_swallowing_difficulties") : ""}
              onChange={v => {
                const previous = s("chewing_swallowing_difficulties");
                updateField("chewing_swallowing_difficulties", v || null);
                updateField("chewing_swallowing_details", v === "Yes"
                  ? s("chewing_swallowing_details") || (!["Yes", "No"].includes(previous) ? previous : null)
                  : null);
              }}
              options={[{ value: "Yes", label: "Yes" }, { value: "No", label: "No" }]}
            />
            {(s("chewing_swallowing_difficulties") === "Yes" || (!s("chewing_swallowing_details") && !!s("chewing_swallowing_difficulties") && !["Yes", "No"].includes(s("chewing_swallowing_difficulties")))) && (
              <Field label="Details (optional)" htmlFor="chewing_swallowing_details">
                <TextArea id="chewing_swallowing_details" ariaLabel="Chewing or swallowing details (optional)" rows={1} maxLength={ASSESSMENT_TEXT_LIMITS.chewing_swallowing_details} value={s("chewing_swallowing_details") || (!["Yes", "No"].includes(s("chewing_swallowing_difficulties")) ? s("chewing_swallowing_difficulties") : "")} onChange={v => updateField("chewing_swallowing_details", v)} />
              </Field>
            )}
          </Field>
          <Field label="Constipation">
            <SelectInput
              value={["Yes", "No"].includes(s("constipation")) ? s("constipation") : ""}
              onChange={v => {
                const previous = s("constipation");
                updateField("constipation", v || null);
                updateField("constipation_details", v === "Yes"
                  ? s("constipation_details") || (!["Yes", "No"].includes(previous) ? previous : null)
                  : null);
              }}
              options={[{ value: "Yes", label: "Yes" }, { value: "No", label: "No" }]}
            />
            {(s("constipation") === "Yes" || (!s("constipation_details") && !!s("constipation") && !["Yes", "No"].includes(s("constipation")))) && (
              <Field label="Details (optional)" htmlFor="constipation_details">
                <TextArea id="constipation_details" ariaLabel="Constipation details (optional)" rows={1} maxLength={ASSESSMENT_TEXT_LIMITS.constipation_details} value={s("constipation_details") || (!["Yes", "No"].includes(s("constipation")) ? s("constipation") : "")} onChange={v => updateField("constipation_details", v)} />
              </Field>
            )}
          </Field>
          <Field label="Diarrhea">
            <SelectInput
              value={["Yes", "No"].includes(s("diarrhea_notes")) ? s("diarrhea_notes") : ""}
              onChange={v => {
                const previous = s("diarrhea_notes");
                updateField("diarrhea_notes", v || null);
                updateField("diarrhea_details", v === "Yes"
                  ? s("diarrhea_details") || (!["Yes", "No"].includes(previous) ? previous : null)
                  : null);
              }}
              options={[{ value: "Yes", label: "Yes" }, { value: "No", label: "No" }]}
            />
            {(s("diarrhea_notes") === "Yes" || (!s("diarrhea_details") && !!s("diarrhea_notes") && !["Yes", "No"].includes(s("diarrhea_notes")))) && (
              <Field label="Details (optional)" htmlFor="diarrhea_details">
                <TextArea id="diarrhea_details" ariaLabel="Diarrhea details (optional)" rows={1} maxLength={ASSESSMENT_TEXT_LIMITS.diarrhea_details} value={s("diarrhea_details") || (!["Yes", "No"].includes(s("diarrhea_notes")) ? s("diarrhea_notes") : "")} onChange={v => updateField("diarrhea_details", v)} />
              </Field>
            )}
          </Field>
          <Field label="Food Intolerance" htmlFor="food_intolerance">
            <TextArea id="food_intolerance" value={s("food_intolerance")} onChange={v => updateField("food_intolerance", v)} rows={1} maxLength={ASSESSMENT_TEXT_LIMITS.food_intolerance} />
          </Field>
        </div>
      </AssessmentSection>
    </div>
  );

  const renderAnthropometricTab = () => (
    <div className="space-y-4">

      {/* ── Always-visible calculated summary strip ───────────────────── */}
      <div className="grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
        {/* IBW — from weight + height (Hamwi) */}
        <div className={`rounded-xl border p-2.5 ${computedIBW !== null ? "bg-white border-warm-200" : "bg-warm-50 border-warm-100"}`}>
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">IBW</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedIBW !== null ? "text-warm-900" : "text-warm-300"}`}>
            {computedIBW !== null ? `${computedIBW.toFixed(1)} kg` : "—"}
          </p>
          <p className="text-xs text-warm-400 mt-0.5">
            {computedIBW !== null
              ? computedPercentIBW !== null
                ? `${computedPercentIBW.toFixed(0)}% IBW · Hamwi`
                : "Hamwi formula"
              : "Enter weight & height"}
          </p>
        </div>
        {/* BMI — from weight + height */}
        <div className={`rounded-xl border p-2.5 ${computedBmi !== null ? "bg-white border-warm-200" : "bg-warm-50 border-warm-100"}`}>
          <div className="flex h-4 items-center gap-1.5"><p className="text-xs font-bold text-warm-400 uppercase tracking-wider">BMI</p><InfoHint label="BMI classification reference" title="BMI reference">WHO 2007: sex-specific bands, 61–228 months (5–19 years). Asia-Pacific: older ages. Under 5: growth calculator not implemented; measurements retained. Month 60: BMI only. Unknown age or pediatric sex, edema or pregnancy/lactation: no class. BMI class is not a malnutrition diagnosis.</InfoHint></div>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedBmi !== null ? "text-warm-900" : "text-warm-300"}`}>
            {computedBmi !== null ? computedBmi.toFixed(2) : "—"}
          </p>
          <p className={`mt-0.5 text-xs font-bold ${computedBmiClassification?.colorClass.split(" ").find((token) => token.startsWith("text-")) ?? "text-warm-400"}`}>
            {computedBmiClassification ? computedBmiClassification.label : assessmentAge !== null && assessmentAge.totalMonths < 60 ? "Not calculated under age 5" : "Enter weight & height"}
          </p>
        </div>
        {/* BMR — from weight + height + age + sex (+ PAL for TEE) */}
        <div className={`rounded-xl border p-2.5 ${computedBMR !== null ? "bg-white border-warm-200" : "bg-warm-50 border-warm-100"}`}>
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">BMR / TEE</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedBMR !== null ? "text-warm-900" : "text-warm-300"}`}>
            {computedBMR !== null ? Math.round(computedBMR) : "—"}
          </p>
          <p className="text-xs text-warm-400 mt-0.5">
            {computedBMR !== null
              ? `BMR · TEE ${computedTEE ?? "—"} kcal (×${palFactor})`
              : "Enter wt, ht · set age & sex in profile"}
          </p>
        </div>
        {/* Weight Loss/Gain % - from weight + usual weight */}
        <div className={`rounded-xl border p-2.5 ${computedWeightChangePct !== null ? "bg-white border-warm-200" : "bg-warm-50 border-warm-100"}`}>
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">Weight Loss/Gain</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedWeightChangePct !== null
              ? weightChangeDirection === "gain"
                ? "text-emerald-700"
                : weightChangeDirection === "loss"
                  ? "text-amber-700"
                  : "text-warm-700"
              : "text-warm-300"
            }`}>
            {computedWeightChangePct !== null ? `${computedWeightChangePct}%` : "—"}
          </p>
          <p className="text-xs text-warm-400 mt-0.5">
            {computedWeightChangePct !== null ? `${displayWeightChangeLabel} from usual weight` : "Enter weight & usual weight"}
          </p>
        </div>
        {/* MUAC — from muac_mm */}
        <div className={`rounded-xl border p-2.5 ${muacValue > 0 ? "bg-white border-warm-200" : "bg-warm-50 border-warm-100"}`}>
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">MUAC</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${muacValue > 0 ? "text-warm-900" : "text-warm-300"}`}>
            {muacValue > 0 ? `${muacValue} mm` : "—"}
          </p>
          {muacClassification !== null
            ? <p className={`text-xs font-bold mt-0.5 ${muacClassification.color}`}>{muacClassification.label}</p>
            : <p className="text-xs text-warm-400 mt-0.5">{muacValue > 0 ? 'Age-specific assessment needed' : 'Enter MUAC (mm)'}</p>
          }
        </div>
        {/* WHR — from waist_cm ÷ hip_cm */}
        <div className={`rounded-xl border p-2.5 ${computedWHR !== null ? (whrRisk === "High Risk" ? "bg-white border-red-200" : "bg-white border-warm-200") : "bg-warm-50 border-warm-100"}`}>
          <div className="flex items-center gap-1.5"><p className="text-xs font-bold text-warm-400 uppercase tracking-wider">WHR</p><InfoHint label="How WHR is calculated" title="Waist-to-hip ratio">Waist circumference ÷ hip circumference. Adult risk classification uses the unrounded ratio and is not applied during pregnancy.</InfoHint></div>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedWHR !== null ? "text-warm-900" : "text-warm-300"}`}>
            {computedWHR !== null ? computedWHR.toFixed(3) : "—"}
          </p>
          {whrRisk !== null
            ? <p className={`text-xs font-bold mt-0.5 ${whrRisk === "High Risk" ? "text-red-500" : "text-emerald-600"}`}>
              {whrRisk} · {patientSex === "Female" ? "cut-off 0.85" : "cut-off 0.90"}
            </p>
            : <p className={`text-xs mt-0.5 ${whrError ? "text-red-600" : "text-warm-400"}`} role={whrError ? "alert" : undefined}>{whrError ?? (computedWHR !== null ? "Adult risk classification unavailable." : "Enter waist & hip cm")}</p>
          }
        </div>
      </div>

      {/* ── Measurement inputs ───────────────────────────────────────── */}
      <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-3 xl:grid-cols-4">
        {/* Bounds mirror backend config/clinical.php assessment_input_bounds — reject typo'd
            weight/height that would otherwise blow up the prescription engine. */}
        <Field label="Weight (kg)" required={CALCULATION_INPUT_HELPERS.weight.required}>
          <TextInput type="number" min={1} max={400} value={String(assessment.weight ?? "")} onChange={v => updateField("weight", v ? Number(v) : null)} />
        </Field>
        <Field label="Usual Weight (kg)" required={CALCULATION_INPUT_HELPERS.usual_weight.required}>
          <TextInput type="number" min={1} max={400} value={String(assessment.usual_weight ?? "")} onChange={v => updateField("usual_weight", v ? Number(v) : null)} />
        </Field>
        <Field label="Height (cm)" required={CALCULATION_INPUT_HELPERS.height.required}>
          <TextInput type="number" min={30} max={250} value={String(assessment.height ?? "")} onChange={v => updateField("height", v ? Number(v) : null)} />
        </Field>
        <Field label="MUAC (mm)" required={CALCULATION_INPUT_HELPERS.muac_mm.required}>
          <TextInput type="number" value={String(assessment.muac_mm ?? "")} onChange={v => updateField("muac_mm", v ? Number(v) : null)} />
        </Field>
        <Field label="Waist Circumference (cm)" htmlFor="waist-cm" required={CALCULATION_INPUT_HELPERS.waist_cm.required}>
          <TextInput id="waist-cm" type="number" min={CIRCUMFERENCE_BOUNDS.min} max={CIRCUMFERENCE_BOUNDS.max} value={String(assessment.waist_cm ?? "")} onChange={v => updateField("waist_cm", v ? Number(v) : null)} />
        </Field>
        <Field label="Hip Circumference (cm)" htmlFor="hip-cm" required={CALCULATION_INPUT_HELPERS.hip_cm.required}>
          <TextInput id="hip-cm" type="number" min={CIRCUMFERENCE_BOUNDS.min} max={CIRCUMFERENCE_BOUNDS.max} value={String(assessment.hip_cm ?? "")} onChange={v => updateField("hip_cm", v ? Number(v) : null)} />
        </Field>
        <Field label="Weight Change Duration" required={CALCULATION_INPUT_HELPERS.weight_change_period_value.required || CALCULATION_INPUT_HELPERS.weight_change_period_unit.required}>
          <div className="grid grid-cols-[minmax(0,1fr)_minmax(8rem,0.65fr)] gap-2">
            <TextInput
              type="number"
              min={0.1}
              value={String(assessment.weight_change_period_value ?? "")}
              onChange={v => updateField("weight_change_period_value", v ? Number(v) : null)}
            />
            <SelectInput
              value={assessment.weight_change_period_unit ?? ""}
              onChange={v => updateField("weight_change_period_unit", v || null)}
              options={[
                { value: "weeks", label: "Weeks" },
                { value: "months", label: "Months" },
              ]}
            />
          </div>
        </Field>
        <Field label="Functional Assessment">
          <SelectInput
            value={s("functional_assessment")}
            onChange={v => updateField("functional_assessment", v || null)}
            options={FUNCTIONAL_OPTIONS.map(o => ({ value: o, label: o }))}
          />
        </Field>
      </div>

    </div>
  );

  const renderClientTab = () => (
    <div className={`${layout.tab} space-y-5`}>
      <AssessmentSection legend="Clinical and social context">
        <div className={layout.grid}>
      <Field label="Nutrition care category" required className={assessment.primary_diagnosis_category === 'Other' ? '' : layout.fullWidth}>
        <SelectInput
          value={assessment.primary_diagnosis_category ?? ""}
          onChange={v => {
            updateField("primary_diagnosis_category", v || null);
            if (v !== "Other") updateField("primary_diagnosis_other", null);
          }}
          options={PRIMARY_DIAGNOSIS_CATEGORIES.map(category => ({ value: category, label: category }))}
        />
      </Field>
      {assessment.primary_diagnosis_category === "Other" && (
        <Field label="Specify category" htmlFor="primary_diagnosis_other" required>
          <TextArea
            id="primary_diagnosis_other"
            value={assessment.primary_diagnosis_other ?? ""}
            onChange={v => updateField("primary_diagnosis_other", v || null)}
            rows={1}
            maxLength={ASSESSMENT_TEXT_LIMITS.primary_diagnosis_other}
          />
        </Field>
      )}
      <Field label="Medical History" htmlFor="medical_history" className={layout.fullWidth}>
        <TextArea id="medical_history" value={s("medical_history")} onChange={v => updateField("medical_history", v)} rows={3} maxLength={ASSESSMENT_TEXT_LIMITS.medical_history} />
      </Field>
      <Field label="Social History" htmlFor="social_history">
        <TextArea id="social_history" value={s("social_history")} onChange={v => updateField("social_history", v)} rows={3} maxLength={ASSESSMENT_TEXT_LIMITS.social_history} />
      </Field>
      <Field label="Religion / Dietary Practices" htmlFor="religion">
        <TextArea
          id="religion"
          value={s("religion") ?? ""}
          onChange={v => updateField("religion", v || null)}
          rows={1}
          maxLength={ASSESSMENT_TEXT_LIMITS.religion}
        />
      </Field>
        </div>
      </AssessmentSection>

      <AssessmentSection legend="Calculation context">
        <div className={layout.grid}>
      <Field label="Physical Activity Level (PAL)" required={CALCULATION_INPUT_HELPERS.physical_activity_level.required}>
        <SelectInput
          value={s("physical_activity_level")}
          onChange={v => updateField("physical_activity_level", v || null)}
          options={Object.entries(ACTIVITY_FACTORS).map(([key, { label, factor }]) => ({
            value: key,
            label: `${label} (×${factor})`,
          }))}
        />
      </Field>
      <Field label="Pregnancy / Lactation" required={CALCULATION_INPUT_HELPERS.pregnancy_lactation_status.required}>
        <SelectInput
          value={(assessment.pregnancy_lactation_status as string) || "none"}
          onChange={v => updateField("pregnancy_lactation_status", v || "none")}
          options={[
            { value: "none", label: "None" },
            { value: "pregnant_t1", label: "Pregnant — first trimester" },
            { value: "pregnant_t2", label: "Pregnant — second trimester" },
            { value: "pregnant_t3", label: "Pregnant — third trimester" },
            { value: "pregnant_unspecified", label: "Pregnant — trimester not confirmed" },
            { value: "lactating", label: "Lactating" },
          ]}
        />
        {assessment.pregnancy_lactation_status === "pregnant_unspecified" && (
          <p className="mt-1 text-xs text-amber-700">
            Confirm the pregnancy trimester before automatic maternal targets are available.
          </p>
        )}
      </Field>
      <Field label="Edema Present" required={CALCULATION_INPUT_HELPERS.edema_present.required}>
        <SelectInput
          value={assessment.edema_present ? "yes" : "no"}
          onChange={v => {
            const present = v === "yes";
            updateField("edema_present", present);
            if (!present) updateField("dry_weight_kg", null);
          }}
          options={[
            { value: "no", label: "No" },
            { value: "yes", label: "Yes" },
          ]}
        />
      </Field>
      {assessment.edema_present && (
        <Field label="Dry Weight (kg)" required>
          <TextInput
            type="number"
            min={1}
            max={400}
            value={String(assessment.dry_weight_kg ?? "")}
            onChange={v => updateField("dry_weight_kg", v ? Number(v) : null)}
          />
        </Field>
      )}
        </div>
      </AssessmentSection>

      <AssessmentSection legend="Food safety and preferences">
        <div className={layout.grid}>
      <Field label="Allergies (Hard Filter for meal plans)" className={layout.fullWidth}>
        <div className="flex flex-wrap gap-1.5 py-1">
          {COMMON_ALLERGENS.map(a => (
            <button
              key={a}
              type="button"
              aria-pressed={allergies.includes(a)}
              onClick={() => updateField("allergies", allergies.includes(a) ? allergies.filter(x => x !== a) : [...allergies, a])}
              className={`min-h-11 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide transition-all cursor-pointer ${allergies.includes(a)
                  ? "bg-red-100 border-red-300 text-red-800"
                  : "bg-warm-50 border-warm-200 text-warm-500 hover:border-red-200 hover:text-red-700"
                }`}
            >
              {a}
            </button>
          ))}
        </div>
        <InfoHint label="How food allergies affect meal generation" title="Food allergy exclusion">
          Recorded allergens are excluded from auto-generated meal recommendations. Review the final plan before use.
        </InfoHint>
      </Field>
      <Field label="Food Dislikes (Soft Filter — warnings only)" htmlFor="food_dislikes">
        <CharacterCountListInput value={assessment.food_dislikes ?? []} onChange={v => updateField("food_dislikes", v)} maxLength={FOOD_DISLIKE_LIMIT} maxEntries={FOOD_DISLIKE_MAX_ENTRIES} label="Food dislike" />
      </Field>
      <Field label="Medications" htmlFor="medications">
        <TextArea id="medications" value={assessment.medications?.join(", ") ?? ""} onChange={v => updateField("medications", v ? [v] : [])} rows={1} maxLength={MEDICATION_LIMIT} />
      </Field>
        </div>
      </AssessmentSection>
    </div>
  );

  const renderBiochemicalTab = () => (
    <div className="space-y-4">
      <AssessmentSection legend="Lab values">
          <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          {LAB_FIELDS.map(field => {
            const rawValue = (assessment.biochemical_data?.[field.key as keyof NonNullable<typeof assessment.biochemical_data>] ?? "") as string | number;
            const numValue = rawValue !== "" ? Number(rawValue) : null;
            const status = numValue !== null ? getLabStatus(numValue, field, patientSex) : "normal";
            const { low, high } = getLabRange(field, patientSex);
            const isAbnormal = status !== "normal";

            return (
              <div
                key={field.key}
                className={`rounded-xl border p-2.5 transition-colors ${isAbnormal
                    ? "border-red-200 bg-red-50/40"
                    : "border-warm-200 bg-white"
                  }`}
              >
                <label htmlFor={`lab-${field.key}`} className="mb-1.5 flex items-start justify-between gap-1 text-xs font-bold uppercase tracking-wider">
                  <span className={`min-w-0 leading-tight ${isAbnormal ? "text-red-700" : "text-warm-500"}`}>
                    {field.label}
                  </span>
                  {isAbnormal && (
                    <span className={`shrink-0 text-xs font-extrabold px-1.5 py-0.5 rounded ${status === "low"
                        ? "bg-sky-100 text-sky-700"
                        : "bg-red-100 text-red-700"
                      }`}>
                      {status === "low" ? "LOW" : "HIGH"}
                    </span>
                  )}
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="number"
                    id={`lab-${field.key}`}
                    value={rawValue}
                    step="any"
                    onChange={e => {
                      const value = e.target.value;
                      if (field.key === "abg" && value.length >= String(rawValue).length
                        && (value.length > ABG_LIMIT || !/^[+-]?\d*\.?\d{0,2}$/.test(value))) return;
                      updateField("biochemical_data", {
                        ...assessment.biochemical_data,
                        [field.key]: coerceBiochemicalValue(field.key, value),
                      });
                    }}
                    className={`min-h-11 min-w-0 w-full flex-1 rounded-lg border bg-white px-3 py-2 text-sm text-warm-900 transition-all placeholder:text-warm-400 focus:outline-none focus:ring-2 ${isAbnormal
                        ? "border-red-300 focus:ring-red-400/20 focus:border-red-400"
                        : "border-warm-200 focus:ring-emerald-500/20 focus:border-emerald-500"
                      }`}
                  />
                  {field.unit && (
                    <span className="shrink-0 text-xs text-warm-400">
                      {field.unit}
                    </span>
                  )}
                </div>
                <div className="mt-1.5 space-y-0.5">
                  <p className="text-xs text-warm-400">
                    Normal: {low !== null ? low : "—"} – {high !== null ? high : "—"}{field.unit ? ` ${field.unit}` : ""}
                  </p>
                  {field.note && (
                    <details className="group/reference pt-0.5">
                      <summary className="inline-flex min-h-6 cursor-pointer items-center text-xs font-semibold text-warm-500 underline decoration-warm-300 underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                        Reference guidance
                      </summary>
                      <p className="pt-1 text-xs italic leading-relaxed text-warm-500">{field.note}</p>
                    </details>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </AssessmentSection>
      <AttachmentsPanel
        ncpId={ncpId}
        kind="labs"
        uploadLabel="Upload Lab Results (PDF or Image)"
        blurb="Attach lab sheets and biochemical results for this NCP cycle. Stored for record-keeping and appended to the printed NCP report."
      />
    </div>
  );

  const renderReferralTab = () => {
    const draft = screeningDraft ?? (patient ? buildScreeningDraft(patient, assessment) : null);
    const screeningType = draft?.screeningType ?? "adult";

    return (
      <div className="space-y-4">
        <div role="tablist" aria-label="Referral and screening sections" className="grid grid-cols-1 gap-2 rounded-xl border border-warm-200 bg-warm-50/60 p-1.5 sm:grid-cols-3">
          {REFERRAL_SECTIONS.map((section) => (
            <button
              key={section.key}
              type="button"
              role="tab"
              aria-selected={referralSection === section.key}
              onClick={() => setReferralSection(section.key)}
              className={`min-h-11 rounded-lg px-3 py-2 text-xs font-extrabold uppercase tracking-wider transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${referralSection === section.key
                  ? "bg-white text-emerald-700 shadow-sm"
                  : "text-warm-500 hover:bg-white/70 hover:text-warm-800"
                }`}
            >
              {section.label}
            </button>
          ))}
        </div>

        {referralSection === "details" && (
        <div className="space-y-3">
          <div className="space-y-4 rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-4 sm:shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div className="flex items-center gap-2">
                  <h4 className="text-base font-extrabold text-warm-900 uppercase tracking-wider">
                    Referral / Screening Form
                  </h4>
                  <InfoHint label="How screening demographics are saved" title="Patient profile updates">
                    Edits to demographics persist to the patient profile when you save.
                  </InfoHint>
                </div>
              </div>
              <div className="grid w-full shrink-0 grid-cols-2 gap-2 sm:flex sm:w-auto">
                {(["adult", "pediatric"] as const).map(t => (
                  <button
                    key={t}
                    type="button"
                    onClick={() => {
                      updateScreeningDraftField("screeningType", t);
                      setSectionAChecks(new Array(getScreeningConditions(t).length).fill(false));
                      setSectionBChecks(new Array(getScreeningIntakeHistory(t).length).fill(false));
                    }}
                    className={`min-h-11 rounded-lg border px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer ${screeningType === t
                        ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                        : "bg-white text-warm-500 border-warm-200 hover:border-warm-300"
                      }`}
                  >
                    {t === "adult" ? "Adult B.07" : "Pediatric B.06"}
                  </button>
                ))}
              </div>
            </div>

            <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-4">
              <Field label="First Name" htmlFor="screening-first-name">
                <TextInput id="screening-first-name" className="min-h-11 focus-visible:ring-2" value={draft?.firstName ?? ""} onChange={v => updateScreeningDraftField("firstName", v)} />
              </Field>
              <Field label="Last Name" htmlFor="screening-last-name">
                <TextInput id="screening-last-name" className="min-h-11 focus-visible:ring-2" value={draft?.lastName ?? ""} onChange={v => updateScreeningDraftField("lastName", v)} />
              </Field>
              <Field label="Date of Birth">
                <DatePicker label="Date of birth" value={draft?.dob ?? ""} onChange={v => updateScreeningDraftField("dob", v)} />
              </Field>
              <Field label="Age">
                <TextInput value={draft?.age ?? ""} onChange={v => updateScreeningDraftField("age", v)} disabled />
              </Field>
              <Field label="Sex">
                <SelectInput
                  value={draft?.sex ?? ""}
                  onChange={v => updateScreeningDraftField("sex", v)}
                  options={[
                    { value: "Male", label: "Male" },
                    { value: "Female", label: "Female" },
                  ]}
                />
              </Field>
              <Field label="Address">
                <TextInput value={draft?.address ?? ""} onChange={v => updateScreeningDraftField("address", v)} />
              </Field>
              <Field label="Referred by">
                <TextInput value={draft?.referredBy ?? ""} onChange={v => updateScreeningDraftField("referredBy", v)} />
              </Field>
              <Field label="Referral Diagnosis" className="md:col-span-2 xl:col-span-2">
                <TextArea
                  value={draft?.diagnosis ?? ""}
                  onChange={v => updateScreeningDraftField("diagnosis", v)}
                  rows={2}
                />
              </Field>
              <Field label="Diet Prescription">
                <TextInput value={draft?.dietPrescription ?? ""} onChange={v => updateScreeningDraftField("dietPrescription", v)} />
              </Field>

              <Field label="Referral Date & Time" className="md:col-span-2 xl:col-span-2">
                <DateTimePicker ariaLabel="Referral date and time" value={draft?.referralDatetime ?? ""} onChange={v => updateScreeningDraftField("referralDatetime", v)} />
              </Field>
            </div>

          </div>

          <div className="space-y-4">
            <AttachmentsPanel
              ncpId={ncpId}
              kind="referral"
              uploadLabel="Upload Referral / Screening Form"
              blurb="Attach referral and screening forms for this NCP cycle. Stored for record-keeping and appended to the printed NCP report."
            />
            {/* <div className="bg-forest-900 border border-forest-line rounded-2xl p-5 text-warm-100 shadow-sm">
              <h4 className="text-xs font-extrabold uppercase tracking-wider text-emerald-300 mb-2">Workflow Note</h4>
              <p className="text-xs leading-relaxed text-warm-300">
                Save after entering screening details. The patient profile updates with the editable demographics captured here. Risk scoring is calculated based on assessment and biochemical data.
              </p>
            </div> */}
          </div>
        </div>
        )}

        {referralSection === "conditions" && (
          <div className="rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-4 sm:shadow-sm">
            <h4 className="text-xs font-extrabold text-warm-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Shield className="h-3.5 w-3.5 text-emerald-600" />
              Section A - Clinical Conditions
            </h4>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
              {getScreeningConditions(screeningType).map((cond, i) => (
                <label key={i} className="flex min-h-11 cursor-pointer items-start gap-2 rounded-lg p-2 text-xs text-warm-700 transition-colors hover:bg-warm-50">
                  <input
                    type="checkbox"
                    checked={sectionAChecks[i] || false}
                    onChange={e => {
                      const next = [...sectionAChecks];
                      next[i] = e.target.checked;
                      setSectionAChecks(next);
                    }}
                    className="mt-0.5 shrink-0 accent-emerald-600"
                  />
                  <span className="leading-tight">{cond}</span>
                </label>
              ))}
            </div>
          </div>
        )}

        {referralSection === "intake" && (
          <div className="rounded-none border-0 bg-transparent p-0 shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-4 sm:shadow-sm">
            <h4 className="text-xs font-extrabold text-warm-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Scale className="h-3.5 w-3.5 text-emerald-600" />
              Section B - Intake / Weight History
            </h4>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
              {getScreeningIntakeHistory(screeningType).map((item, i) => (
                <label key={i} className="flex min-h-11 cursor-pointer items-start gap-2 rounded-lg p-2 text-xs text-warm-700 transition-colors hover:bg-warm-50">
                  <input
                    type="checkbox"
                    checked={sectionBChecks[i] || false}
                    onChange={e => {
                      const next = [...sectionBChecks];
                      next[i] = e.target.checked;
                      setSectionBChecks(next);
                    }}
                    className="mt-0.5 shrink-0 accent-emerald-600"
                  />
                  <span className="leading-tight">{item}</span>
                </label>
              ))}
            </div>
          </div>
        )}
      </div>
    );
  };

  const renderRiskScore = () => (
    <div className="bg-white border border-warm-200 rounded-xl p-4 space-y-3">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h4 className="text-xs font-extrabold text-warm-800 uppercase tracking-wider flex items-center gap-1.5">
          <Activity className="h-3.5 w-3.5 text-emerald-600" />
          Scoring of Nutritional Risk Related Factors
          <InfoHint label="Nutritional risk score legend" title="Risk score legend">
            <p>Low: 0–1 point. Moderate: 2–3 points. High: more than 3 points.</p>
          </InfoHint>
        </h4>
        <div className="flex items-center gap-2 flex-wrap justify-end">
          <button
            type="button"
            onClick={resetAutomaticRiskScore}
            className={`min-h-11 px-3 py-2 rounded-full text-xs font-extrabold uppercase tracking-wider border transition-colors ${!riskManualOverride
                ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                : "bg-white text-warm-500 border-warm-200 hover:border-emerald-200 hover:text-emerald-700"
              }`}
          >
            Automatic
          </button>
          <button
            type="button"
            onClick={enableManualRiskScore}
            className={`min-h-11 px-3 py-2 rounded-full text-xs font-extrabold uppercase tracking-wider border transition-colors ${riskManualOverride
                ? "bg-amber-50 text-amber-700 border-amber-200"
                : "bg-white text-warm-500 border-warm-200 hover:border-amber-200 hover:text-amber-700"
              }`}
          >
            Manual edit
          </button>
        </div>
        <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border ${riskInfo.color}`}>
          {riskInfo.label} — {riskScore} pts
        </span>
      </div>
      {riskManualOverride && (
        <p className={`text-xs font-semibold px-3 py-2 rounded-lg border ${computedRiskInfo.color}`}>
          Automatic score: {computedRiskInfo.label} - {riskResult.score} pts
        </p>
      )}
      <details className="rounded-lg border border-warm-200 bg-warm-50/60 px-3 py-2">
        <summary className="flex min-h-11 cursor-pointer items-center text-xs font-bold text-warm-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
          How automatic scoring works
        </summary>
        <ul className="space-y-1 pb-2 text-xs leading-relaxed text-warm-600">
          <li>+1: screening type selected.</li>
          <li>+1: IBW below 85% or above 130%.</li>
          <li>Unintentional weight loss requires RND confirmation in Manual edit (+2 points); a recorded percentage alone is not scored.</li>
          <li>+1: chewing, swallowing, constipation, diarrhea, or intolerance note.</li>
          <li>+1: Albumin below 3.5 g/dL.</li>
          <li>+1: Glucose below 70 or above 125 mg/dL, Potassium below 3.5 or above 5.0 mEq/L, Creatinine above the sex-specific limit, or BUN above 20 mg/dL.</li>
          <li>+1: nutrient-drug interaction or lifestyle note.</li>
        </ul>
      </details>
      <div className="grid grid-cols-1 gap-2 xl:grid-cols-2">
        {RISK_FACTORS.map((factor, i) => (
          <div key={i} className={`min-h-11 flex items-center justify-between gap-3 text-xs text-warm-700 px-3 py-2 rounded-lg border ${riskManualOverride ? "border-warm-200 bg-warm-50/40" : "border-transparent"}`}>
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={riskChecks[i]}
                onChange={e => toggleManualRiskFactor(factor.key, e.target.checked)}
                className="shrink-0 accent-emerald-600 cursor-pointer"
              />
              <span className="leading-tight">{factor.label}</span>
            </div>
            <span className="text-xs font-bold text-warm-400 shrink-0">{factor.points} pt{factor.points > 1 ? "s" : ""}</span>
          </div>
        ))}
      </div>
      <div className="grid grid-cols-1 gap-3 pt-1 sm:max-w-sm">
        <Field label="Weight Loss/Gain %">
          <TextInput
            type="number"
            value={String(displayWeightChangePct ?? "")}
            onChange={v => updateField("weight_loss_percentage", v ? Number(v) : null)}
            disabled={computedWeightChangePct !== null}
          />
        </Field>
      </div>
    </div>
  );

  const renderSummaryTab = () => (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
      <div className="space-y-3 rounded-xl border border-warm-200 bg-white p-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex items-center gap-1">
            <h3 className="text-sm font-extrabold text-warm-800">Assessment Summary</h3>
            <InfoHint label="How the assessment summary draft works" title="Assessment summary draft">
              Generated text is a draft. Review and edit it before saving the assessment.
            </InfoHint>
          </div>
          <div className="flex shrink-0 flex-wrap items-center gap-2">
            {summaryUndo !== null && (
              <button
                type="button"
                onClick={handleUndoSummary}
                className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-warm-200 bg-white px-3 py-2 text-xs font-bold text-warm-700 transition-colors hover:border-warm-300 hover:bg-warm-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
              >
                <RotateCcw className="h-3.5 w-3.5" />
                Undo
              </button>
            )}
            <button
              type="button"
              onClick={handleGenerateSummary}
              className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-extrabold uppercase tracking-wider text-white transition-colors hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:flex-none"
            >
              <ClipboardCheck className="h-3.5 w-3.5" />
              {s("rnd_summary").trim() ? "Regenerate Summary" : "Generate Summary"}
            </button>
          </div>
        </div>

        <div aria-live="polite" className={`rounded-lg border px-3 py-2 text-xs font-semibold ${summaryIsStale
            ? "border-amber-200 bg-amber-50 text-amber-800"
            : "border-warm-200 bg-warm-50 text-warm-600"
          }`}>
          {summaryIsStale
            ? "Assessment changed - regenerate to refresh"
            : summaryNotice ?? (summaryWasEdited
                ? "Draft edited after generation. Regeneration will replace it; Undo restores the prior text."
                : s("rnd_summary").trim()
                  ? "Summary is current with the loaded assessment data."
                  : "Generate a draft after entering assessment details.")}
        </div>

        <Field label="RND Summary (Clinical Observations)" htmlFor="rnd_summary">
          <TextArea
            id="rnd_summary"
            value={s("rnd_summary")}
            onChange={handleSummaryChange}
            rows={4}
            maxLength={ASSESSMENT_TEXT_LIMITS.rnd_summary}
          />
        </Field>
      </div>
      {renderRiskScore()}
    </div>
  );

  const tabContent: Record<TabKey, React.ReactNode> = {
    dietary: renderDietaryTab(),
    anthropometric: renderAnthropometricTab(),
    client: renderClientTab(),
    biochemical: renderBiochemicalTab(),
    referral: renderReferralTab(),
    summary: renderSummaryTab(),
  };

  // ─── Render ─────────────────────────────────────────────────────────
  return (
    <div className="space-y-4 font-sans">
      {assessmentGuard.dialog}
      {/* Breadcrumb */}
      <NcpBreadcrumb step="Nutrition Assessment" />

      <NcpPatientHeader
        patient={patient}
        ncpId={ncpId}
        physician={screeningDraft?.referredBy}
        riskScore={riskScore}
        foodDetails={[...allergies, ...(assessment.food_dislikes ?? []), assessment.dietary_restrictions]}
        interventionGoal={interventionGoal}
        medicalDiagnosis={screeningDraft?.diagnosis}
        onBeforeUnselectPatient={assessmentGuard.requestLeave}
      />

      {/* Status Messages */}
      {error && (
        <div className="px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700 font-bold flex items-center gap-2">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" /> {error}
        </div>
      )}
      {success && (
        <div className="px-4 py-3 bg-emerald-50 border border-emerald-100 rounded-xl text-sm text-emerald-700 font-bold flex items-center gap-2">
          <ClipboardCheck className="h-3.5 w-3.5 shrink-0" /> {success}
        </div>
      )}

      {/* Save Assessment */}
      <div className="flex justify-end rounded-none border-0 bg-transparent px-0 py-0 shadow-none sm:rounded-xl sm:border sm:border-warm-200 sm:bg-white sm:px-4 sm:py-3 sm:shadow-sm">
        <button
          type="button"
          onClick={handleSave}
          disabled={saving}
          className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-extrabold uppercase tracking-wider text-white shadow-sm transition-colors hover:bg-emerald-700 active:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-emerald-400 sm:w-auto"
        >
          <Save className="h-3.5 w-3.5" />
          {saving ? "Saving..." : "Save Assessment"}
        </button>
      </div>

      {/* Tab Navigation */}
      <div className="rounded-none border-0 bg-white shadow-none sm:rounded-xl sm:border sm:border-warm-200 sm:shadow-sm">
        <NcpSectionTabs
          items={TABS.map(({ key, label, icon: Icon }) => ({ key, label, icon: <Icon className="h-3.5 w-3.5" /> }))}
          value={activeTab}
          onChange={setActiveTab}
          ariaLabel="Assessment categories"
          idPrefix="assessment"
        />

        {/* Tab Content */}
        <div
          id={`assessment-panel-${activeTab}`}
          role="tabpanel"
          aria-labelledby={`assessment-tab-${activeTab}`}
          className="p-0 py-4 sm:p-4 lg:p-5"
        >
          {tabContent[activeTab]}
          <div hidden={activeTab !== 'dietary'} className="mt-4">
            <AssessmentSection legend="Food Recall">
              <Field label="Food recall notes (optional)">
                <TextArea ariaLabel="Food recall notes" value={assessment.dietary_intake ?? ''}
                  onChange={value => updateField('dietary_intake', value)} maxLength={ASSESSMENT_TEXT_LIMITS.dietary_intake} />
              </Field>
              <FoodRecallNutrientFields values={Object.fromEntries(Object.entries(assessment.intake_nutrients ?? { energy_kcal: null }).map(([key, value]) => [key, value == null ? '' : String(value)]))}
                onChange={values => updateField('intake_nutrients', Object.fromEntries(Object.entries(values).map(([key, value]) => [key, value.trim() === '' ? null : value])))}
                renderField={(field, value, onChange) => <Field label={`${field.label} intake (${field.unit})`}><TextInput type="number" ariaLabel={`${field.label} intake`} value={value} onChange={onChange} min={0} max={field.max} step={0.01} /></Field>} />
              <FoodRecallCalculator key={`${ncpId}:${assessment.updated_at ?? 'draft'}`} value={assessment.food_recall} disabled={saving} leaveRef={recallEditor} onDirtyChange={setRecallDirty}
                onSave={plan => { acceptedRecall.current = plan; updateField('food_recall', plan); }} />
            </AssessmentSection>
          </div>
        </div>

      </div>
    </div>
  );
}
