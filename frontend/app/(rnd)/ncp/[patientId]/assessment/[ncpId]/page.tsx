"use client";

import React, { use, useCallback, useEffect, useLayoutEffect, useState, useRef } from "react";
import Link from "next/link";
import {
  ClipboardCheck, Utensils, Ruler, UserRound, FlaskConical,
  FileText, Sparkles, Save, Upload, AlertTriangle,
  ChevronRight, Activity, Paperclip, Trash2, Download, Eye, X,
  Shield, Scale, RotateCcw,
} from "lucide-react";
import { fetchPatientById, Patient, updatePatient, PatientUpdateData } from "@/services/patientService";
import {
  calcIBW, calcPercentIBW, calcBMR, calcTEE, calcBmrWeight,
  classifyBmi, classifyNutritionalStatus, ACTIVITY_FACTORS,
} from "@/lib/nutritionCalculations";
import { CALCULATION_INPUT_HELPERS } from "@/lib/assessmentCalculationInputs";
import { getAnthropometricSafetyWarning } from "@/lib/anthropometricSafety";
import { formatDateInputValue, formatPatientAge } from "@/lib/patientAge";
import { changedPersonNameFields, personDisplayName } from "@/lib/personName";
import {
  Assessment, AssessmentValidationError, fetchAssessment, saveAssessment,
  AttachmentRecord, uploadAttachment, fetchAttachments, deleteAttachment,
  getAttachmentFileUrl,
} from "@/services/assessmentService";
import { coerceBiochemicalValue } from "@/services/biochemical";
import { deriveRiskScore, RISK_FACTORS, scoreRiskFactors } from "@/lib/assessmentRiskScoring";
import { buildAssessmentSummary, type AssessmentSummaryInput } from "@/lib/assessmentSummary";
import { fetchIntervention } from "@/services/interventionService";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { DatePicker, DateTimePicker } from "@/components/ui/DatePicker";

// ─── Constants ───────────────────────────────────────────────────────────
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];
const ASSESSMENT_FIELD_LABELS: Record<string, string> = {
  weight: "Weight",
  usual_weight: "Usual weight",
  dry_weight_kg: "Dry weight",
  height: "Height",
  physical_activity_level: "Physical activity level",
};

function formatAssessmentValidationError(error: AssessmentValidationError): string {
  const fields = Object.keys(error.errors).map((field) => ASSESSMENT_FIELD_LABELS[field] ?? field.replace(/_/g, " "));
  return fields.length > 0
    ? `Complete required calculation fields before saving: ${fields.join(", ")}.`
    : error.message;
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
  referralType: string;
  referredBy: string;
  referralDatetime: string;
  ward: string;
  hospitalNumber: string;
  ageGroupCategory: string;
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
  "Unintentional weight loss in the past 3 months",
  "Reduced dietary intake in the past week",
  "BMI below 18.5 and above 30 (to be computed by the RND)",
  "Others",
  "Pregnant patient is aged 18 years old or 35 years old",
  "Pregnancy with Hyperemesis Gravidarum / Pregnancy-induced Hypertension",
  "Multiple Pregnancy",
  "Lactating Mother",
];

const PEDIATRIC_INTAKE_WEIGHT_HISTORY = [
  "Unintentional weight loss in the past 3 months",
  "Patient on breastmilk feeding",
  "Reduced dietary intake in the past week",
  "Reduction of dietary intake in the past week/s and/or during the hospital stay",
  "For patients ages >5 years old to <18 years old, 364 days: BMI z-scores above +2 and below -2 (c/o RND)",
  "For patients ages >2 to 5 years old: Weight for Height z-scores above +2 and below -2 (c/o RND)",
  "For patients ages 1 month to 2 years old: Weight for Length z-scores above +2 and below -2 (c/o RND)",
  "Others",
];

const REFERRAL_TYPE_OPTIONS = [
  { value: "Per Orem", label: "Per Orem" },
  { value: "Tube Feeding", label: "Tube Feeding" },
  { value: "NPO / TPN", label: "NPO / TPN" },
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
const DIETARY_METHOD_OPTIONS = [
  { value: "24_hour_recall", label: "24-Hour Recall" },
  { value: "food_frequency", label: "Food Frequency" },
  { value: "3_day_record", label: "3-Day Record" },
  { value: "other", label: "Other" },
];
const FUNCTIONAL_OPTIONS = ["Bed ridden", "Needs assistance", "Ambulatory"];

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
    <div className={`${span ? `col-span-${span}` : ""} ${className}`} style={span ? { gridColumn: `span ${span}` } : undefined}>
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

function TextInput({ value, onChange, placeholder, type, disabled, min, max, id, className }: {
  value: string; onChange: (v: string) => void; placeholder?: string; type?: string; disabled?: boolean;
  min?: number; max?: number; id?: string; className?: string;
}) {
  return (
    <input
      type={type ?? "text"}
      id={id}
      value={value}
      onChange={e => onChange(e.target.value)}
      placeholder={placeholder}
      disabled={disabled}
      min={min}
      max={max}
      className={`min-h-11 w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-warm-400 disabled:bg-warm-50 disabled:cursor-not-allowed ${className ?? ""}`}
    />
  );
}

function TextArea({ value, onChange, placeholder, rows }: {
  value: string; onChange: (v: string) => void; placeholder?: string; rows?: number;
}) {
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useLayoutEffect(() => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    textarea.style.height = "auto";
    textarea.style.height = `${textarea.scrollHeight}px`;
  }, [rows, value]);

  return (
    <textarea
      ref={textareaRef}
      value={value}
      onChange={e => onChange(e.target.value)}
      placeholder={placeholder}
      rows={rows ?? 3}
      className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm text-warm-900 transition-colors placeholder:text-warm-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
    />
  );
}

function SelectInput({ value, onChange, options, placeholder }: {
  value: string; onChange: (v: string) => void; options: { value: string; label: string }[]; placeholder?: string;
}) {
  return (
    <select
      value={value}
      onChange={e => onChange(e.target.value)}
      className="min-h-11 w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer"
    >
      <option value="">{placeholder ?? "Select..."}</option>
      {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  );
}

function TagInput({ tags, onChange, placeholder }: {
  tags: string[]; onChange: (t: string[]) => void; placeholder?: string;
}) {
  const [input, setInput] = useState("");
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if ((e.key === "Enter" || e.key === ",") && input.trim()) {
      e.preventDefault();
      if (!tags.includes(input.trim())) {
        onChange([...tags, input.trim()]);
      }
      setInput("");
    } else if (e.key === "Backspace" && !input && tags.length) {
      onChange(tags.slice(0, -1));
    }
  };
  return (
    <div className="flex min-h-11 flex-wrap items-center gap-1.5 rounded-lg border border-warm-200 bg-white px-3 py-2">
      {tags.map((tag, i) => (
        <span key={i} className="inline-flex items-center gap-1 px-2 py-0.5 bg-warm-100 border border-warm-200 rounded text-xs font-bold text-warm-700">
          {tag}
          <button
            type="button"
            aria-label={`Remove ${tag}`}
            onClick={() => onChange(tags.filter((_, j) => j !== i))}
            className="ml-0.5 inline-flex min-h-6 min-w-6 cursor-pointer items-center justify-center text-warm-400 hover:text-red-500"
          >
            ×
          </button>
        </span>
      ))}
      <input
        value={input}
        onChange={e => setInput(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder={tags.length === 0 ? (placeholder ?? "Type and press Enter...") : ""}
        className="flex-1 min-w-[80px] text-sm bg-transparent outline-none text-warm-900 placeholder:text-warm-400"
      />
    </div>
  );
}

function DropZone({ label, onUpload, uploading }: {
  label: string; onUpload: (file: File) => void; uploading: boolean;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);

  const handleFile = (file: File) => {
    if (file && (file.type.startsWith("image/") || file.type === "application/pdf")) {
      onUpload(file);
    }
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
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={url}
              alt={name}
              className="max-w-full max-h-[75vh] object-contain p-2"
            />
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
    weight_loss_percentage: null, weight_loss_period: null,
    functional_assessment: null, energy_intake_status: null, ibw_percentage: null,
    present_diet: null, physical_assessment: null,
    chewing_swallowing_difficulties: null, constipation: null, diarrhea_notes: null,
    food_intolerance: null, nutrient_drug_interaction: null,
    dietary_intake_method: null, dietary_record_file: null,
    physical_activity_level: null, muac_mm: null, waist_cm: null, hip_cm: null,
    stress_factor: null, edema_present: false, dry_weight_kg: null, pregnancy_lactation_status: "none",
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
      referralType: "",
      referredBy: basePatient.physician ?? "",
      referralDatetime: "",
      ward: basePatient.ward ?? "",
      hospitalNumber: basePatient.hospital_number ?? "",
      ageGroupCategory: basePatient.age_group_category ?? "",
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
  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    try {
      setLoading(true);
      const [p, a, intervention] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchAssessment(ncpId),
        fetchIntervention(ncpId),
      ]);
      let loadedPatient: Patient | null = null;
      let loadedAssessment: Assessment | null = null;

      if (p.status === "fulfilled") {
        setPatient(p.value);
        loadedPatient = p.value;
      }
      if (a.status === "fulfilled") {
        setAssessment(a.value);
        setAssessmentExists(true);
        loadedAssessment = a.value;
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
      // Assessment may not exist yet for new patients, which is fine
    } finally {
      setLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder, buildScreeningDraft]);

  useEffect(() => { void loadData(); }, [loadData]);

  // ─── BMI Auto-Calc ──────────────────────────────────────────────────
  const weight = Number(assessment.weight) || 0;
  const height = Number(assessment.height) || 0;
  const computedBmi = calcBmi(weight || null, height || null);
  const computedBmiClassification = computedBmi !== null ? classifyBmi(computedBmi) : null;

  // ─── Auto-Calc Panel Derived Values ────────────────────────────────
  const patientSex = (patient?.sex as "Male" | "Female") ?? "Male";
  const patientDobStr = screeningDraft?.dob || patient?.dob || null;
  const patientAgeYears = patientDobStr
    ? (() => {
      const b = new Date(patientDobStr);
      const now = new Date();
      let age = now.getFullYear() - b.getFullYear();
      const m = now.getMonth() - b.getMonth();
      if (m < 0 || (m === 0 && now.getDate() < b.getDate())) age -= 1;
      return age;
    })()
    : 30;

  const computedIBW = weight > 0 && height > 0 ? calcIBW(height, patientSex) : null;
  const computedPercentIBW = computedIBW !== null && weight > 0 ? calcPercentIBW(weight, computedIBW) : null;
  const palKey = assessment.physical_activity_level ?? "sedentary";
  const palFactor = ACTIVITY_FACTORS[palKey]?.factor ?? 1.2;
  const computedBmrWt = computedIBW !== null && weight > 0 ? calcBmrWeight(weight, computedIBW) : null;
  const computedBMR = computedBmrWt !== null
    ? calcBMR(computedBmrWt, height, patientAgeYears, patientSex)
    : null;
  const computedTEE = computedBMR !== null ? Math.round(calcTEE(computedBMR, palFactor)) : null;
  const computedNutritionalStatus = computedBmi !== null && computedPercentIBW !== null
    ? classifyNutritionalStatus(computedBmi, computedPercentIBW)
    : null;
  const computedWHR = assessment.waist_cm && assessment.hip_cm
    ? Math.round((Number(assessment.waist_cm) / Number(assessment.hip_cm)) * 100) / 100
    : null;
  const whrRisk = computedWHR !== null
    ? (patientSex === "Female"
      ? (computedWHR < 0.85 ? "Low Risk" : "High Risk")
      : (computedWHR < 0.90 ? "Low Risk" : "High Risk"))
    : null;

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
  const isAdultPatient = patientAgeYears >= 18;
  const muacClassification = muacValue > 0
    ? (() => {
      if (isAdultPatient) {
        if (muacValue >= 210) return { label: "Normal", color: "text-emerald-600" };
        if (muacValue >= 190) return { label: "Moderate Malnutrition", color: "text-amber-600" };
        return { label: "Severe Malnutrition", color: "text-red-600" };
      }
      // Pediatric 6–59 months
      if (muacValue >= 125) return { label: "Normal", color: "text-emerald-600" };
      if (muacValue >= 115) return { label: "MAM", color: "text-amber-600" };
      return { label: "SAM", color: "text-red-600" };
    })()
    : null;

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

  const summaryInput: AssessmentSummaryInput = (() => {
    const labs = LAB_FIELDS.flatMap((field) => {
      const raw = assessment.biochemical_data?.[field.key as keyof NonNullable<Assessment["biochemical_data"]>];
      if (raw === null || raw === undefined || raw === "") return [];
      const value = Number(raw);
      if (!Number.isFinite(value)) return [];
      return [{
        label: field.label,
        value,
        unit: field.unit,
        status: getLabStatus(value, field, patientSex),
      }];
    });

    return {
      assessment,
      anthropometrics: {
        bmi: computedBmi,
        ibwKg: computedIBW === null ? null : Number(computedIBW.toFixed(1)),
        percentIbw: computedPercentIBW === null ? null : Math.round(computedPercentIBW),
        weightChangePercent: computedWeightChangePct,
        weightChangeDirection: weightChangeDirection === "stable" ? "none" : weightChangeDirection,
        muacClassification: muacClassification?.label ?? null,
        whr: computedWHR,
        whrRisk,
        nutritionalStatus: computedNutritionalStatus?.label ?? assessment.nutritional_status,
      },
      labs,
      risk: riskScore > 0 || effectiveRiskFactors.length > 0
        ? {
            label: riskInfo.label,
            score: riskScore,
            mode: riskManualOverride ? "manual" : "automatic",
            factors: effectiveRiskFactors
              .map((key) => RISK_FACTORS.find((factor) => factor.key === key)?.label)
              .filter((label): label is string => Boolean(label)),
          }
        : null,
    };
  })();
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
  const handleSave = async () => {
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
        return;
      }
      const nameFields = patient && screeningDraft
        ? changedPersonNameFields(patient, screeningDraft.firstName, screeningDraft.lastName)
        : null;

      const toSave: Partial<Assessment> = {
        ...assessment,
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
      setAssessmentExists(true);

      // Save patient demographics if updated via screening form
      if (patient && screeningDraft) {
        const patientData: PatientUpdateData = {
          dob: screeningDraft.dob,
          sex: screeningDraft.sex as "Male" | "Female",
          address: screeningDraft.address,
          ward: screeningDraft.ward,
          physician: screeningDraft.referredBy,
          medical_diagnosis: screeningDraft.diagnosis,
          hospital_number: screeningDraft.hospitalNumber,
          age_group_category: screeningDraft.ageGroupCategory,
          screening_type: screeningDraft.screeningType,
          ...(nameFields ?? {}),
        };
        const updated = await updatePatient(patient.id, patientData);
        setPatient(updated);
      }

      setSuccess("Assessment saved successfully.");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof AssessmentValidationError
        ? formatAssessmentValidationError(err)
        : err instanceof Error ? err.message : "Failed to save assessment.");
    } finally {
      setSaving(false);
    }
  };

  // ─── Loading / Placeholder ──────────────────────────────────────────
  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-warm-600 font-bold">Assessment</span>
        </div>
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

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
          <span>Directory</span><ChevronRight className="h-3 w-3" /><span>Loading...</span>
        </div>
        <div className="space-y-4">
          {[1, 2, 3].map(i => <div key={i} className="h-16 bg-warm-100 rounded-xl animate-pulse" />)}
        </div>
      </div>
    );
  }

  const systemId = patient ? `NS-${String(patient.id).padStart(5, "0")}` : "";
  const allergies = assessment.allergies ?? [];

  // ─── Tab Renderers ──────────────────────────────────────────────────
  const renderDietaryTab = () => (
    <div className="space-y-4">
      <AssessmentSection legend="Diet and intake">
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-4">
      <Field label="Present Diet" className="md:col-span-2 xl:col-span-2">
        <TextArea value={s("present_diet")} onChange={v => updateField("present_diet", v)} placeholder="Current diet description..." rows={2} />
      </Field>
      <Field label="Energy Intake Status">
        <SelectInput
          value={s("energy_intake_status")}
          onChange={v => updateField("energy_intake_status", v || null)}
          options={ENERGY_INTAKE_OPTIONS.map(o => ({ value: o, label: o }))}
          placeholder="Select energy intake status..."
        />
      </Field>
      <Field label="Dietary Intake Method">
        <SelectInput
          value={s("dietary_intake_method")}
          onChange={v => updateField("dietary_intake_method", v || null)}
          options={DIETARY_METHOD_OPTIONS}
          placeholder="Select method..."
        />
      </Field>
      <Field label="Dietary Intake" className="md:col-span-2 xl:col-span-2">
        <TextArea value={s("dietary_intake")} onChange={v => updateField("dietary_intake", v)} placeholder="24-hour recall narrative or food frequency notes..." rows={2} />
      </Field>
      <Field label="Appetite Changes">
        <SelectInput
          value={s("appetite_changes") ?? ""}
          onChange={v => updateField("appetite_changes", v || null)}
          placeholder="Select..."
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
      <Field label="Dietary Restrictions">
        <TextArea value={s("dietary_restrictions")} onChange={v => updateField("dietary_restrictions", v)} placeholder="Restrictions and intolerances..." rows={2} />
      </Field>
        </div>
      </AssessmentSection>

      <AssessmentSection legend="Dietary modifiers">
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-3">
      <Field label="Supplements">
        <TextInput value={s("supplements")} onChange={v => updateField("supplements", v)} placeholder="Current supplements..." />
      </Field>
      <Field label="Knowledge / Beliefs Notes">
        <TextArea value={s("knowledge_notes")} onChange={v => updateField("knowledge_notes", v)} placeholder="RND observations on patient knowledge and beliefs..." rows={2} />
      </Field>
      <Field label="Nutrient-Drug Interaction">
        <TextArea value={s("nutrient_drug_interaction")} onChange={v => updateField("nutrient_drug_interaction", v)} placeholder="Known nutrient-drug interactions..." rows={2} />
      </Field>
        </div>
      </AssessmentSection>
      {/* GI / Tolerance — moved from Anthropometrics tab */}
      <AssessmentSection legend="GI / tolerance">
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-3">
          <Field label="Chewing / Swallowing Difficulties">
            <TextArea value={s("chewing_swallowing_difficulties")} onChange={v => updateField("chewing_swallowing_difficulties", v)} placeholder="Any chewing or swallowing difficulties..." rows={2} />
          </Field>
          <Field label="Constipation">
            <TextArea value={s("constipation")} onChange={v => updateField("constipation", v)} placeholder="Constipation notes..." rows={2} />
          </Field>
          <Field label="Diarrhea Notes">
            <TextArea value={s("diarrhea_notes")} onChange={v => updateField("diarrhea_notes", v)} placeholder="Diarrhea notes..." rows={2} />
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
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">BMI</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedBmi !== null ? "text-warm-900" : "text-warm-300"}`}>
            {computedBmi !== null ? computedBmi.toFixed(1) : "—"}
          </p>
          <p className={`mt-0.5 text-xs font-bold ${computedBmiClassification?.colorClass.split(" ").find((token) => token.startsWith("text-")) ?? "text-warm-400"}`}>
            {computedBmiClassification ? computedBmiClassification.label : "Enter weight & height"}
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
        <div className={`rounded-xl border p-2.5 ${muacClassification !== null ? "bg-white border-warm-200" : "bg-warm-50 border-warm-100"}`}>
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">MUAC</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${muacClassification !== null ? "text-warm-900" : "text-warm-300"}`}>
            {muacValue > 0 ? `${muacValue} mm` : "—"}
          </p>
          {muacClassification !== null
            ? <p className={`text-xs font-bold mt-0.5 ${muacClassification.color}`}>{muacClassification.label}</p>
            : <p className="text-xs text-warm-400 mt-0.5">Enter MUAC (mm)</p>
          }
        </div>
        {/* WHR — from waist_cm ÷ hip_cm */}
        <div className={`rounded-xl border p-2.5 ${computedWHR !== null ? (whrRisk === "High Risk" ? "bg-white border-red-200" : "bg-white border-warm-200") : "bg-warm-50 border-warm-100"}`}>
          <p className="text-xs font-bold text-warm-400 uppercase tracking-wider">WHR</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedWHR !== null ? "text-warm-900" : "text-warm-300"}`}>
            {computedWHR !== null ? computedWHR.toFixed(2) : "—"}
          </p>
          {computedWHR !== null
            ? <p className={`text-xs font-bold mt-0.5 ${whrRisk === "High Risk" ? "text-red-500" : "text-emerald-600"}`}>
              {whrRisk} · {patientSex === "Female" ? "cut-off 0.85" : "cut-off 0.90"}
            </p>
            : <p className="text-xs text-warm-400 mt-0.5">Enter waist & hip cm</p>
          }
        </div>
      </div>

      {/* ── Measurement inputs ───────────────────────────────────────── */}
      <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-3 xl:grid-cols-4">
        {/* Bounds mirror backend config/clinical.php assessment_input_bounds — reject typo'd
            weight/height that would otherwise blow up the prescription engine. */}
        <Field label="Weight (kg)" required={CALCULATION_INPUT_HELPERS.weight.required}>
          <TextInput type="number" min={1} max={400} value={String(assessment.weight ?? "")} onChange={v => updateField("weight", v ? Number(v) : null)} placeholder="e.g. 70.5" />
        </Field>
        <Field label="Usual Weight (kg)" required={CALCULATION_INPUT_HELPERS.usual_weight.required}>
          <TextInput type="number" min={1} max={400} value={String(assessment.usual_weight ?? "")} onChange={v => updateField("usual_weight", v ? Number(v) : null)} placeholder="e.g. 72.0" />
        </Field>
        <Field label="Height (cm)" required={CALCULATION_INPUT_HELPERS.height.required}>
          <TextInput type="number" min={30} max={250} value={String(assessment.height ?? "")} onChange={v => updateField("height", v ? Number(v) : null)} placeholder="e.g. 170" />
        </Field>
        <Field label="MUAC (mm)" required={CALCULATION_INPUT_HELPERS.muac_mm.required}>
          <TextInput type="number" value={String(assessment.muac_mm ?? "")} onChange={v => updateField("muac_mm", v ? Number(v) : null)} placeholder="e.g. 250" />
        </Field>
        <Field label="Waist Circumference (cm)" required={CALCULATION_INPUT_HELPERS.waist_cm.required}>
          <TextInput type="number" value={String(assessment.waist_cm ?? "")} onChange={v => updateField("waist_cm", v ? Number(v) : null)} placeholder="e.g. 90" />
        </Field>
        <Field label="Hip Circumference (cm)" required={CALCULATION_INPUT_HELPERS.hip_cm.required}>
          <TextInput type="number" value={String(assessment.hip_cm ?? "")} onChange={v => updateField("hip_cm", v ? Number(v) : null)} placeholder="e.g. 100" />
        </Field>
        <Field label="Weight Loss/Gain Period" required={CALCULATION_INPUT_HELPERS.weight_loss_period.required}>
          <TextInput value={s("weight_loss_period")} onChange={v => updateField("weight_loss_period", v)} placeholder="e.g. 3 months" />
        </Field>
        <Field label="Functional Assessment">
          <SelectInput
            value={s("functional_assessment")}
            onChange={v => updateField("functional_assessment", v || null)}
            options={FUNCTIONAL_OPTIONS.map(o => ({ value: o, label: o }))}
            placeholder="Select functional status..."
          />
        </Field>
      </div>

    </div>
  );

  const renderClientTab = () => (
    <div className="space-y-4">
      <AssessmentSection legend="Clinical and social context">
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-3">
      <Field label="Medical History" className="md:col-span-2">
        <TextArea value={s("medical_history")} onChange={v => updateField("medical_history", v)} placeholder="Medical history..." rows={3} />
      </Field>
      <Field label="Social History">
        <TextArea value={s("social_history")} onChange={v => updateField("social_history", v)} placeholder="Social history..." rows={3} />
      </Field>
      <Field label="Religion / Dietary Practices">
        <TextInput
          value={s("religion") ?? ""}
          onChange={v => updateField("religion", v || null)}
          placeholder="e.g. Roman Catholic, Muslim, Seventh-Day Adventist"
        />
      </Field>
        </div>
      </AssessmentSection>

      <AssessmentSection legend="Calculation context">
        <div className="grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-5">
      <Field label="Physical Activity Level (PAL)" required={CALCULATION_INPUT_HELPERS.physical_activity_level.required}>
        <SelectInput
          value={s("physical_activity_level")}
          onChange={v => updateField("physical_activity_level", v || null)}
          options={Object.entries(ACTIVITY_FACTORS).map(([key, { label, factor }]) => ({
            value: key,
            label: `${label} (×${factor})`,
          }))}
          placeholder="Select activity level..."
        />
      </Field>
      <Field label="Stress Factor" required={CALCULATION_INPUT_HELPERS.stress_factor.required}>
        <TextInput type="number" value={String(assessment.stress_factor ?? "")} onChange={v => updateField("stress_factor", v ? Number(v) : null)} placeholder="e.g. 1.2" />
      </Field>
      <Field label="Pregnancy / Lactation" required={CALCULATION_INPUT_HELPERS.pregnancy_lactation_status.required}>
        <SelectInput
          value={(assessment.pregnancy_lactation_status as string) || "none"}
          onChange={v => updateField("pregnancy_lactation_status", v || "none")}
          options={[
            { value: "none", label: "None" },
            { value: "pregnant", label: "Pregnant (2nd/3rd trimester)" },
            { value: "lactating", label: "Lactating" },
          ]}
          placeholder="None"
        />
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
          placeholder="No"
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
            placeholder="e.g. 68.0"
          />
        </Field>
      )}
        </div>
      </AssessmentSection>

      <AssessmentSection legend="Food safety and preferences">
        <div className="grid grid-cols-1 items-start gap-3 xl:grid-cols-3">
      <Field label="Allergies (Hard Filter for meal plans)">
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
        {allergies.length > 0 && (
          <p className="text-xs text-red-500 mt-1 font-bold">⚠ These allergens will be hard-excluded from meal plan recommendations.</p>
        )}
      </Field>
      <Field label="Food Dislikes (Soft Filter — warnings only)">
        <TagInput tags={assessment.food_dislikes ?? []} onChange={v => updateField("food_dislikes", v)} placeholder="Type disliked food and press Enter..." />
      </Field>
      <Field label="Medications">
        <TagInput tags={assessment.medications ?? []} onChange={v => updateField("medications", v)} placeholder="Type medication and press Enter..." />
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
                <div className="relative">
                  <input
                    type="number"
                    id={`lab-${field.key}`}
                    value={rawValue}
                    onChange={e => updateField("biochemical_data", {
                      ...assessment.biochemical_data,
                      [field.key]: coerceBiochemicalValue(field.key, e.target.value),
                    })}
                    placeholder={`e.g. ${low ?? high}`}
                    className={`min-h-11 w-full rounded-lg border bg-white px-3 py-2 text-sm text-warm-900 transition-all placeholder:text-warm-400 focus:outline-none focus:ring-2 ${isAbnormal
                        ? "border-red-300 focus:ring-red-400/20 focus:border-red-400"
                        : "border-warm-200 focus:ring-emerald-500/20 focus:border-emerald-500"
                      }`}
                  />
                  {field.unit && (
                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-warm-400 pointer-events-none">
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
          <div className="space-y-4 rounded-2xl border border-warm-200 bg-white p-4 shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h4 className="text-base font-extrabold text-warm-900 uppercase tracking-wider flex items-center gap-2">
                  <FileText className="h-4 w-4 text-emerald-600" />
                  Referral / Screening Form
                </h4>
                <p className="text-xs text-warm-500 mt-1 leading-relaxed">
                  Enter screening demographics below manually. Edits to demographics will persist back to the patient profile on save.
                </p>
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
                <TextInput id="screening-first-name" className="min-h-11 focus-visible:ring-2" value={draft?.firstName ?? ""} onChange={v => updateScreeningDraftField("firstName", v)} placeholder="First name" />
              </Field>
              <Field label="Last Name" htmlFor="screening-last-name">
                <TextInput id="screening-last-name" className="min-h-11 focus-visible:ring-2" value={draft?.lastName ?? ""} onChange={v => updateScreeningDraftField("lastName", v)} placeholder="Last name" />
              </Field>
              <Field label="Date of Birth">
                <DatePicker label="Date of birth" value={draft?.dob ?? ""} onChange={v => updateScreeningDraftField("dob", v)} />
              </Field>
              <Field label="Age">
                <TextInput value={draft?.age ?? ""} onChange={v => updateScreeningDraftField("age", v)} placeholder="Derived age" disabled />
              </Field>
              <Field label="Sex">
                <SelectInput
                  value={draft?.sex ?? ""}
                  onChange={v => updateScreeningDraftField("sex", v)}
                  options={[
                    { value: "Male", label: "Male" },
                    { value: "Female", label: "Female" },
                  ]}
                  placeholder="Select sex"
                />
              </Field>
              <Field label="Address">
                <TextInput value={draft?.address ?? ""} onChange={v => updateScreeningDraftField("address", v)} placeholder="Patient address" />
              </Field>
              <Field label="Ward / Bed No">
                <TextInput value={draft?.ward ?? ""} onChange={v => updateScreeningDraftField("ward", v)} placeholder="Ward / unit" />
              </Field>
              <Field label="Attending Physician">
                <TextInput value={draft?.referredBy ?? ""} onChange={v => updateScreeningDraftField("referredBy", v)} placeholder="Attending physician" />
              </Field>
              <Field label="Medical Diagnosis" className="md:col-span-2 xl:col-span-2">
                <TextArea
                  value={draft?.diagnosis ?? ""}
                  onChange={v => updateScreeningDraftField("diagnosis", v)}
                  placeholder="Medical diagnosis or admitting impression"
                  rows={2}
                />
              </Field>
              <Field label="Hospital Number">
                <TextInput value={draft?.hospitalNumber ?? ""} onChange={v => updateScreeningDraftField("hospitalNumber", v)} placeholder="Hospital number" />
              </Field>
              <Field label="Age Group Category">
                <TextInput
                  value={draft?.ageGroupCategory ?? ""}
                  onChange={v => updateScreeningDraftField("ageGroupCategory", v)}
                  placeholder="Adult / adolescent / pediatric"
                />
              </Field>
              <Field label="Diet Prescription">
                <TextInput value={draft?.dietPrescription ?? ""} onChange={v => updateScreeningDraftField("dietPrescription", v)} placeholder="e.g. Low Sodium, Soft Diet" />
              </Field>
              <Field label="Referral Type">
                <SelectInput
                  value={draft?.referralType ?? ""}
                  onChange={v => updateScreeningDraftField("referralType", v)}
                  options={REFERRAL_TYPE_OPTIONS}
                  placeholder="Select referral type..."
                />
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
          <div className="bg-white border border-warm-200 rounded-2xl p-4 shadow-sm">
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
          <div className="bg-white border border-warm-200 rounded-2xl p-4 shadow-sm">
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
          <li>+2: any recorded unintentional weight loss.</li>
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
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
        <Field label="Weight Loss/Gain %">
          <TextInput
            type="number"
            value={String(displayWeightChangePct ?? "")}
            onChange={v => updateField("weight_loss_percentage", v ? Number(v) : null)}
            placeholder="%"
            disabled={computedWeightChangePct !== null}
          />
        </Field>
        <Field label="Over Period">
          <TextInput value={s("weight_loss_period")} onChange={v => updateField("weight_loss_period", v)} placeholder="e.g. 3 months" />
        </Field>
      </div>
    </div>
  );

  const renderSummaryTab = () => (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
      <div className="space-y-3 rounded-xl border border-warm-200 bg-white p-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h3 className="text-sm font-extrabold text-warm-800">Auto-generated assessment draft</h3>
            <p className="mt-1 text-xs leading-relaxed text-warm-500">
              Builds a concise draft from completed fields. Existing text remains editable.
            </p>
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
          {summaryNotice
            ?? (summaryIsStale
              ? "Assessment changed - regenerate to refresh"
              : summaryWasEdited
                ? "Draft edited after generation. Regeneration will replace it; Undo restores the prior text."
                : s("rnd_summary").trim()
                  ? "Summary is current with the loaded assessment data."
                  : "Generate a draft after entering assessment details.")}
        </div>

        <Field label="RND Summary (Clinical Observations)">
          <TextArea
            value={s("rnd_summary")}
            onChange={handleSummaryChange}
            placeholder="Summarize clinical observations, reassessment needs, and overall nutritional status..."
            rows={4}
          />
        </Field>
        <p className="text-xs leading-relaxed text-warm-500">
          Generated text is a draft. Review and edit it before saving the assessment.
        </p>
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
      {/* Breadcrumb */}
      <div className="flex items-center gap-1.5 text-sm font-semibold text-warm-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
        <ChevronRight className="h-3 w-3" />
        <Link href={`/ncp/patients/${patientId}`} className="hover:text-emerald-700 transition-colors">{personDisplayName(patient, systemId)}</Link>
        <ChevronRight className="h-3 w-3" />
        <span className="text-warm-700 font-bold">Assessment</span>
      </div>

      <NcpPatientHeader
        patient={patient}
        physician={screeningDraft?.referredBy}
        riskScore={riskScore}
        foodDetails={[...allergies, ...(assessment.food_dislikes ?? []), assessment.dietary_restrictions]}
        interventionGoal={interventionGoal}
        medicalDiagnosis={screeningDraft?.diagnosis}
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
      <div className="flex justify-end rounded-xl border border-warm-200 bg-white px-4 py-3 shadow-sm">
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
      <div className="bg-white border border-warm-200 rounded-xl overflow-hidden shadow-sm">
        <div
          role="tablist"
          aria-label="Assessment categories"
          className="flex overflow-x-auto border-b border-warm-200 bg-warm-50/50"
        >
          {TABS.map(tab => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.key;
            return (
              <button
                key={tab.key}
                type="button"
                role="tab"
                aria-selected={isActive}
                aria-controls={`assessment-panel-${tab.key}`}
                onClick={() => setActiveTab(tab.key)}
                className={`flex min-h-11 items-center gap-1.5 px-4 py-3 text-xs font-bold uppercase tracking-wider whitespace-nowrap border-b-2 transition-all cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500 ${isActive
                    ? "text-emerald-700 border-emerald-600 bg-white"
                    : "text-warm-500 border-transparent hover:text-warm-700 hover:bg-white/50"
                  }`}
              >
                <Icon className="h-3.5 w-3.5" />
                {tab.label}
              </button>
            );
          })}
        </div>

        {/* Tab Content */}
        <div
          id={`assessment-panel-${activeTab}`}
          role="tabpanel"
          className="p-3 sm:p-4 lg:p-5"
        >
          {tabContent[activeTab]}
        </div>

      </div>
    </div>
  );
}
