"use client";

import React, { use, useCallback, useEffect, useState, useRef } from "react";
import Link from "next/link";
import {
  ClipboardCheck, Utensils, Ruler, UserRound, FlaskConical,
  FileText, Sparkles, Save, Upload, AlertTriangle,
  ChevronRight, Activity, Heart, Paperclip, Trash2, Download,
  Shield, Scale,
} from "lucide-react";
import { fetchPatientById, Patient, updatePatient, PatientUpdateData } from "@/services/patientService";
import {
  calcIBW, calcAjBW, calcPercentIBW, calcBMR, calcTEE, calcBmrWeight,
  classifyNutritionalStatus, ACTIVITY_FACTORS,
} from "@/lib/nutritionCalculations";
import {
  Assessment, fetchAssessment, saveAssessment,
  AttachmentRecord, uploadAttachment, fetchAttachments, deleteAttachment,
  getAttachmentFileUrl,
} from "@/services/assessmentService";
import { coerceBiochemicalValue } from "@/services/biochemical";

// ─── Constants ───────────────────────────────────────────────────────────
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];

export type ScreeningDraft = {
  patientName: string;
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
  { key: "dietary", label: "A: Dietary History", icon: Utensils },
  { key: "anthropometric", label: "B: Anthropometrics", icon: Ruler },
  { key: "client", label: "C: Client History", icon: UserRound },
  { key: "biochemical", label: "D: Biochemical / Labs", icon: FlaskConical },
  { key: "referral", label: "E: Referral / Screening", icon: FileText },
  { key: "summary", label: "F: RND Summary", icon: Sparkles },
] as const;

type TabKey = (typeof TABS)[number]["key"];

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
const RISK_FACTORS = [
  { key: "screening_criteria",         label: "Screening criteria for potential nutritional risk", points: 1 },
  { key: "ibw_limit",                  label: "Less than 85% or greater than 130% ideal body weight", points: 1 },
  { key: "unintentional_weight_loss",  label: "Unintentional weight loss over weeks/months", points: 2 },
  { key: "mechanical_digestive_problem", label: "Mechanical / digestive problem", points: 1 },
  { key: "low_albumin",                label: "Low albumin", points: 1 },
  { key: "significant_lab_result",     label: "Significant lab result", points: 1 },
  { key: "others",                     label: "Other/s", points: 1 },
];

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

function calculateAgeFromDob(dob?: string | null) {
  if (!dob) return "N/A";
  const birthDate = new Date(dob);
  if (Number.isNaN(birthDate.getTime())) return "N/A";
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDelta = today.getMonth() - birthDate.getMonth();
  if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
    age -= 1;
  }
  return `${age}`;
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
function Field({ label, children, span, hint }: { label: string; children: React.ReactNode; span?: number; hint?: string }) {
  return (
    <div className={span ? `col-span-${span}` : ""} style={span ? { gridColumn: `span ${span}` } : undefined}>
      <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1.5">
        {label}
        {hint && <span className="ml-1.5 text-emerald-500 font-semibold normal-case tracking-normal">→ {hint}</span>}
      </label>
      {children}
    </div>
  );
}

function TextInput({ value, onChange, placeholder, type, disabled }: {
  value: string; onChange: (v: string) => void; placeholder?: string; type?: string; disabled?: boolean;
}) {
  return (
    <input
      type={type ?? "text"}
      value={value}
      onChange={e => onChange(e.target.value)}
      placeholder={placeholder}
      disabled={disabled}
      className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-zinc-400 disabled:bg-zinc-50 disabled:cursor-not-allowed"
    />
  );
}

function TextArea({ value, onChange, placeholder, rows }: {
  value: string; onChange: (v: string) => void; placeholder?: string; rows?: number;
}) {
  return (
    <textarea
      value={value}
      onChange={e => onChange(e.target.value)}
      placeholder={placeholder}
      rows={rows ?? 3}
      className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all placeholder:text-zinc-400 resize-none"
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
      className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all cursor-pointer"
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
    <div className="flex flex-wrap items-center gap-1.5 px-3 py-2 bg-white border border-zinc-200 rounded-lg min-h-[36px]">
      {tags.map((tag, i) => (
        <span key={i} className="inline-flex items-center gap-1 px-2 py-0.5 bg-zinc-100 border border-zinc-200 rounded text-[10px] font-bold text-zinc-700">
          {tag}
          <button type="button" onClick={() => onChange(tags.filter((_, j) => j !== i))} className="text-zinc-400 hover:text-red-500 ml-0.5 cursor-pointer">×</button>
        </span>
      ))}
      <input
        value={input}
        onChange={e => setInput(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder={tags.length === 0 ? (placeholder ?? "Type and press Enter...") : ""}
        className="flex-1 min-w-[80px] text-xs bg-transparent outline-none text-zinc-900 placeholder:text-zinc-400"
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
      className={`relative border-2 border-dashed rounded-xl p-6 text-center transition-all cursor-pointer select-none ${
        dragOver ? "border-emerald-400 bg-emerald-50/50" : "border-zinc-300 hover:border-zinc-400 bg-zinc-50/30"
      }`}
      onClick={() => inputRef.current?.click()}
      onDragOver={e => { e.preventDefault(); setDragOver(true); }}
      onDragLeave={() => setDragOver(false)}
      onDrop={e => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); }}
    >
      <input ref={inputRef} type="file" accept=".pdf,.png,.jpg,.jpeg" className="hidden" onChange={e => { if (e.target.files?.[0]) handleFile(e.target.files[0]); }} />
      {uploading ? (
        <div className="space-y-2">
          <div className="h-1 w-48 mx-auto bg-zinc-200 rounded-full overflow-hidden">
            <div className="h-full bg-emerald-500 rounded-full animate-pulse" style={{ width: "70%" }} />
          </div>
          <p className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Uploading...</p>
        </div>
      ) : (
        <>
          <Upload className="h-5 w-5 text-zinc-400 mx-auto mb-2" />
          <p className="text-[10px] font-bold text-zinc-600 uppercase tracking-wider">{label}</p>
          <p className="text-[9px] text-zinc-400 mt-1">Drag and drop or click to upload — PDF, JPEG, PNG (max 10MB)</p>
        </>
      )}
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

  const load = useCallback(async () => {
    try {
      setError(null);
      setItems(await fetchAttachments(ncpId));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load attachments.");
    } finally {
      setLoading(false);
    }
  }, [ncpId]);

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
    <div className="space-y-4">
      <div className="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 space-y-3">
        <div className="flex items-center gap-2">
          <Paperclip className="h-4 w-4 text-emerald-600" />
          <h4 className="text-[10px] font-extrabold text-zinc-800 uppercase tracking-wider">Supporting Documents</h4>
        </div>
        <p className="text-[11px] text-zinc-500 leading-relaxed">{blurb}</p>
        <DropZone label={uploadLabel} onUpload={handleUpload} uploading={uploading} />
      </div>

      {error && (
        <div className="px-3 py-2 bg-rose-50 border border-rose-200 rounded-lg text-[11px] text-rose-700 font-semibold">{error}</div>
      )}

      {loading ? (
        <div className="space-y-2">
          {[0, 1].map(i => <div key={i} className="h-12 bg-zinc-100 rounded-xl animate-pulse" />)}
        </div>
      ) : items.length === 0 ? (
        <div className="text-center py-8 text-[11px] text-zinc-400 font-semibold border border-dashed border-zinc-200 rounded-xl">
          No documents attached to this cycle yet.
        </div>
      ) : (
        <div className="space-y-2">
          {items.map(doc => (
            <div key={doc.id} className="flex items-center gap-3 px-3 py-2.5 bg-white border border-zinc-200 rounded-xl">
              <div className="h-9 w-9 shrink-0 rounded-lg bg-zinc-50 border border-zinc-200 flex items-center justify-center overflow-hidden text-zinc-400">
                {isImagePath(doc.file_path) ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={getAttachmentFileUrl(doc.id)} alt={doc.original_name ?? "attachment"} className="h-full w-full object-cover" />
                ) : (
                  <FileText className="h-4 w-4" />
                )}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-xs font-semibold text-zinc-800 truncate">{doc.original_name ?? "Document"}</p>
                <p className="text-[10px] text-zinc-400">
                  {doc.type ? `${doc.type} · ` : ""}{formatDate(doc.created_at)}
                </p>
              </div>
              <a
                href={getAttachmentFileUrl(doc.id)}
                target="_blank"
                rel="noopener noreferrer"
                className="p-1.5 text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                title="Open / download"
              >
                <Download className="h-3.5 w-3.5" />
              </a>
              <button
                type="button"
                onClick={() => handleDelete(doc.id)}
                disabled={deletingId === doc.id}
                className="p-1.5 text-zinc-300 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors disabled:opacity-50"
                title="Remove"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
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
    stress_factor: null, edema_present: false, pregnancy_lactation_status: "none",
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
  const [assessmentExists, setAssessmentExists] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [screeningDraft, setScreeningDraft] = useState<ScreeningDraft | null>(null);
  const [sectionAChecks, setSectionAChecks] = useState<boolean[]>([]);
  const [sectionBChecks, setSectionBChecks] = useState<boolean[]>([]);

  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const buildScreeningDraft = useCallback((basePatient: Patient, baseAssessment?: Assessment | null): ScreeningDraft => {
    return {
      patientName: basePatient.name,
      age: calculateAgeFromDob(basePatient.dob),
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
      return { ...current, [key]: value };
    });
  };

  // ─── Load Data ──────────────────────────────────────────────────────
  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    try {
      setLoading(true);
      const [p, a] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchAssessment(ncpId),
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

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void loadData(); }, [loadData]);

  // ─── BMI Auto-Calc ──────────────────────────────────────────────────
  const weight = Number(assessment.weight) || 0;
  const height = Number(assessment.height) || 0;
  const computedBmi = calcBmi(weight || null, height || null);

  // ─── Auto-Calc Panel Derived Values ────────────────────────────────
  const patientSex = (patient?.sex as "Male" | "Female") ?? "Male";
  const patientDobStr = patient?.dob ?? null;
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
  const computedAjBW = computedIBW !== null && computedPercentIBW !== null && computedPercentIBW > 120
    ? calcAjBW(weight, computedIBW)
    : null;
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
  const computedWeightLossPct = usualWeight > 0 && weight > 0 && usualWeight > weight
    ? Math.round(((usualWeight - weight) / usualWeight) * 1000) / 10  // 1 decimal place
    : null;

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

  // ─── Risk Score (backend-authoritative) ────────────────────────────
  const checkedSet = new Set(assessment.checked_factors ?? []);
  const riskChecks = RISK_FACTORS.map(f => checkedSet.has(f.key));
  const riskScore = RISK_FACTORS.reduce((sum, f, i) => riskChecks[i] ? sum + f.points : sum, 0);
  const riskInfo = riskBadge(riskScore);

  // ─── Field Updater ──────────────────────────────────────────────────
  const updateField = (key: keyof Assessment, value: unknown) => {
    setAssessment(prev => ({ ...prev, [key]: value }));
  };

  const s = (key: keyof Assessment): string => (assessment[key] as string) ?? "";

  // ─── Save ───────────────────────────────────────────────────────────
  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      setSuccess(null);

      const toSave: Partial<Assessment> = {
        ...assessment,
        // Auto-computed fields — override stored value with live computation when available
        bmi: computedBmi,
        ibw_percentage: computedPercentIBW ?? assessment.ibw_percentage,
        weight_loss_percentage: computedWeightLossPct ?? assessment.weight_loss_percentage,
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
          name: screeningDraft.patientName,
          sex: screeningDraft.sex as "Male" | "Female",
          address: screeningDraft.address,
          ward: screeningDraft.ward,
          physician: screeningDraft.referredBy,
          medical_diagnosis: screeningDraft.diagnosis,
          hospital_number: screeningDraft.hospitalNumber,
          age_group_category: screeningDraft.ageGroupCategory,
          screening_type: screeningDraft.screeningType,
        };
        const updated = await updatePatient(patient.id, patientData);
        setPatient(updated);
      }

      setSuccess("Assessment saved successfully.");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to save assessment.");
    } finally {
      setSaving(false);
    }
  };

  // ─── Loading / Placeholder ──────────────────────────────────────────
  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-zinc-600 font-bold">Assessment</span>
        </div>
        <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
          <ClipboardCheck className="h-8 w-8 text-zinc-300 mx-auto mb-4" />
          <h3 className="text-sm font-bold text-zinc-800">No Patient Selected</h3>
          <p className="text-xs text-zinc-500 mt-2">Return to the directory to select or create a patient.</p>
          <Link href="/ncp/patients" className="inline-flex mt-4 px-4 py-2 bg-zinc-950 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg hover:bg-zinc-800 transition-colors">
            Go to Patients
          </Link>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
          <span>Directory</span><ChevronRight className="h-3 w-3" /><span>Loading...</span>
        </div>
        <div className="space-y-4">
          {[1, 2, 3].map(i => <div key={i} className="h-16 bg-zinc-100 rounded-xl animate-pulse" />)}
        </div>
      </div>
    );
  }

  const systemId = patient ? `NS-${String(patient.id).padStart(5, "0")}` : "";
  const allergies = assessment.allergies ?? [];

  // ─── Tab Renderers ──────────────────────────────────────────────────
  const renderDietaryTab = () => (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Present Diet" span={2}>
        <TextArea value={s("present_diet")} onChange={v => updateField("present_diet", v)} placeholder="Current diet description..." />
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
      <Field label="Dietary Intake" span={2}>
        <TextArea value={s("dietary_intake")} onChange={v => updateField("dietary_intake", v)} placeholder="24-hour recall narrative or food frequency notes..." />
      </Field>
      <Field label="Appetite Changes">
        <SelectInput
          value={s("appetite_changes") ?? ""}
          onChange={v => updateField("appetite_changes", v || null)}
          placeholder="Select..."
          options={[
            { value: "normal",         label: "Normal appetite" },
            { value: "decreased",      label: "Decreased — eating less than usual" },
            { value: "increased",      label: "Increased — eating more than usual" },
            { value: "variable",       label: "Variable / Inconsistent" },
            { value: "absent",         label: "Absent / Anorexia" },
            { value: "early_satiety",  label: "Early satiety" },
            { value: "nausea_vomiting",label: "Nausea / Vomiting affecting intake" },
          ]}
        />
      </Field>
      <Field label="Dietary Restrictions">
        <TextArea value={s("dietary_restrictions")} onChange={v => updateField("dietary_restrictions", v)} placeholder="Restrictions and intolerances..." rows={2} />
      </Field>
      <Field label="Supplements">
        <TextInput value={s("supplements")} onChange={v => updateField("supplements", v)} placeholder="Current supplements..." />
      </Field>
      <Field label="Knowledge / Beliefs Notes" span={2}>
        <TextArea value={s("knowledge_notes")} onChange={v => updateField("knowledge_notes", v)} placeholder="RND observations on patient knowledge and beliefs..." />
      </Field>
      <Field label="Nutrient-Drug Interaction" span={2}>
        <TextArea value={s("nutrient_drug_interaction")} onChange={v => updateField("nutrient_drug_interaction", v)} placeholder="Known nutrient-drug interactions..." rows={2} />
      </Field>
      {/* GI / Tolerance — moved from Anthropometrics tab */}
      <div className="col-span-1 md:col-span-2 mt-2">
        <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-3">GI / Tolerance</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
      </div>
    </div>
  );

  const renderAnthropometricTab = () => (
    <div className="space-y-6">

      {/* ── Always-visible calculated summary strip ───────────────────── */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
        {/* IBW — from weight + height (Hamwi) */}
        <div className={`rounded-xl p-3 border ${computedIBW !== null ? "bg-white border-zinc-200" : "bg-zinc-50 border-zinc-100"}`}>
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">IBW</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedIBW !== null ? "text-zinc-900" : "text-zinc-300"}`}>
            {computedIBW !== null ? `${computedIBW.toFixed(1)} kg` : "—"}
          </p>
          <p className="text-[9px] text-zinc-400 mt-0.5">
            {computedIBW !== null
              ? computedPercentIBW !== null
                ? `${computedPercentIBW.toFixed(0)}% IBW · Hamwi`
                : "Hamwi formula"
              : "Enter weight & height"}
          </p>
        </div>
        {/* BMI — from weight + height */}
        <div className={`rounded-xl p-3 border ${computedBmi !== null ? "bg-white border-zinc-200" : "bg-zinc-50 border-zinc-100"}`}>
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">BMI</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedBmi !== null ? "text-zinc-900" : "text-zinc-300"}`}>
            {computedBmi !== null ? computedBmi.toFixed(1) : "—"}
          </p>
          <p className="text-[9px] text-zinc-400 mt-0.5">
            {computedBmi !== null ? "kg/m²" : "Enter weight & height"}
          </p>
        </div>
        {/* BMR — from weight + height + age + sex (+ PAL for TEE) */}
        <div className={`rounded-xl p-3 border ${computedBMR !== null ? "bg-white border-zinc-200" : "bg-zinc-50 border-zinc-100"}`}>
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">BMR / TEE</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedBMR !== null ? "text-zinc-900" : "text-zinc-300"}`}>
            {computedBMR !== null ? Math.round(computedBMR) : "—"}
          </p>
          <p className="text-[9px] text-zinc-400 mt-0.5">
            {computedBMR !== null
              ? `BMR · TEE ${computedTEE ?? "—"} kcal (×${palFactor})`
              : "Enter wt, ht · set age & sex in profile"}
          </p>
        </div>
        {/* Weight Loss % — from weight + usual weight */}
        <div className={`rounded-xl p-3 border ${computedWeightLossPct !== null ? "bg-white border-zinc-200" : "bg-zinc-50 border-zinc-100"}`}>
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">Weight Loss</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedWeightLossPct !== null ? "text-amber-700" : "text-zinc-300"}`}>
            {computedWeightLossPct !== null ? `${computedWeightLossPct}%` : "—"}
          </p>
          <p className="text-[9px] text-zinc-400 mt-0.5">
            {computedWeightLossPct !== null ? "from usual weight" : "Enter weight & usual weight"}
          </p>
        </div>
        {/* MUAC — from muac_mm */}
        <div className={`rounded-xl p-3 border ${muacClassification !== null ? "bg-white border-zinc-200" : "bg-zinc-50 border-zinc-100"}`}>
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">MUAC</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${muacClassification !== null ? "text-zinc-900" : "text-zinc-300"}`}>
            {muacValue > 0 ? `${muacValue} mm` : "—"}
          </p>
          {muacClassification !== null
            ? <p className={`text-[9px] font-bold mt-0.5 ${muacClassification.color}`}>{muacClassification.label}</p>
            : <p className="text-[9px] text-zinc-400 mt-0.5">Enter MUAC (mm)</p>
          }
        </div>
        {/* WHR — from waist_cm ÷ hip_cm */}
        <div className={`rounded-xl p-3 border ${computedWHR !== null ? (whrRisk === "High Risk" ? "bg-white border-red-200" : "bg-white border-zinc-200") : "bg-zinc-50 border-zinc-100"}`}>
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">WHR</p>
          <p className={`text-xl font-black font-mono mt-0.5 ${computedWHR !== null ? "text-zinc-900" : "text-zinc-300"}`}>
            {computedWHR !== null ? computedWHR.toFixed(2) : "—"}
          </p>
          {computedWHR !== null
            ? <p className={`text-[9px] font-bold mt-0.5 ${whrRisk === "High Risk" ? "text-red-500" : "text-emerald-600"}`}>
                {whrRisk} · {patientSex === "Female" ? "cut-off 0.85" : "cut-off 0.90"}
              </p>
            : <p className="text-[9px] text-zinc-400 mt-0.5">Enter waist & hip cm</p>
          }
        </div>
      </div>

      {/* ── Nutritional Status Badge (auto, no manual entry needed) ───── */}
      {computedNutritionalStatus !== null && (
        <div className={`p-3 rounded-xl border ${computedNutritionalStatus.colorClass}`}>
          <div className="flex items-center justify-between flex-wrap gap-2">
            <div>
              <p className="text-[9px] font-bold uppercase tracking-wider opacity-70">Nutritional Status</p>
              <p className="text-sm font-black mt-0.5">{computedNutritionalStatus.label}</p>
            </div>
            {computedNutritionalStatus.suggestedGoal && (
              <div className="text-right">
                <p className="text-[9px] font-bold uppercase tracking-wider opacity-70">Suggested Goal</p>
                <p className="text-[10px] font-bold mt-0.5 capitalize">
                  {computedNutritionalStatus.suggestedGoal.replace(/_/g, " ")}
                  {computedNutritionalStatus.suggestedStage ? ` → ${computedNutritionalStatus.suggestedStage.replace(/_/g, " ")}` : ""}
                </p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ── Measurement inputs ───────────────────────────────────────── */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Field label="Weight (kg)" hint="IBW% · BMI · BMR · Weight Loss%">
          <TextInput type="number" value={String(assessment.weight ?? "")} onChange={v => updateField("weight", v ? Number(v) : null)} placeholder="e.g. 70.5" />
        </Field>
        <Field label="Usual Weight (kg)" hint="Weight Loss%">
          <TextInput type="number" value={String(assessment.usual_weight ?? "")} onChange={v => updateField("usual_weight", v ? Number(v) : null)} placeholder="e.g. 72.0" />
        </Field>
        <Field label="Height (cm)" hint="IBW · BMI · BMR">
          <TextInput type="number" value={String(assessment.height ?? "")} onChange={v => updateField("height", v ? Number(v) : null)} placeholder="e.g. 170" />
        </Field>
        <Field label="MUAC (mm)" hint="MUAC card">
          <TextInput type="number" value={String(assessment.muac_mm ?? "")} onChange={v => updateField("muac_mm", v ? Number(v) : null)} placeholder="e.g. 250" />
        </Field>
        <Field label="Waist Circumference (cm)" hint="WHR card">
          <TextInput type="number" value={String(assessment.waist_cm ?? "")} onChange={v => updateField("waist_cm", v ? Number(v) : null)} placeholder="e.g. 90" />
        </Field>
        <Field label="Hip Circumference (cm)" hint="WHR card">
          <TextInput type="number" value={String(assessment.hip_cm ?? "")} onChange={v => updateField("hip_cm", v ? Number(v) : null)} placeholder="e.g. 100" />
        </Field>
        <Field label="Weight Loss Period">
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
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      <Field label="Medical History" span={2}>
        <TextArea value={s("medical_history")} onChange={v => updateField("medical_history", v)} placeholder="Medical history..." rows={4} />
      </Field>
      <Field label="Social History">
        <TextArea value={s("social_history")} onChange={v => updateField("social_history", v)} placeholder="Social history..." rows={3} />
      </Field>
      <Field label="Religion / Dietary Practices" hint="Helps identify dietary restrictions">
        <TextInput
          value={s("religion") ?? ""}
          onChange={v => updateField("religion", v || null)}
          placeholder="e.g. Roman Catholic, Muslim, Seventh-Day Adventist"
        />
      </Field>
      <Field label="Physical Activity Level (PAL)">
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
      <Field label="Stress Factor" hint="High-protein / acute illness (e.g. 1.2 sepsis)">
        <TextInput type="number" value={String(assessment.stress_factor ?? "")} onChange={v => updateField("stress_factor", v ? Number(v) : null)} placeholder="e.g. 1.2" />
      </Field>
      <Field label="Pregnancy / Lactation" hint="PDRI energy & protein add-on">
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
      <Field label="Edema Present" hint="Flags weight as unreliable">
        <SelectInput
          value={assessment.edema_present ? "yes" : "no"}
          onChange={v => updateField("edema_present", v === "yes")}
          options={[
            { value: "no", label: "No" },
            { value: "yes", label: "Yes — weight may be unreliable" },
          ]}
          placeholder="No"
        />
      </Field>
      <Field label="Allergies (Hard Filter for meal plans)">
        <div className="flex flex-wrap gap-1.5 py-1">
          {COMMON_ALLERGENS.map(a => (
            <button
              key={a}
              type="button"
              onClick={() => updateField("allergies", allergies.includes(a) ? allergies.filter(x => x !== a) : [...allergies, a])}
              className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide border transition-all cursor-pointer ${
                allergies.includes(a)
                  ? "bg-red-100 border-red-300 text-red-800"
                  : "bg-zinc-50 border-zinc-200 text-zinc-500 hover:border-red-200 hover:text-red-700"
              }`}
            >
              {a}
            </button>
          ))}
        </div>
        {allergies.length > 0 && (
          <p className="text-[9px] text-red-500 mt-1 font-bold">⚠ These allergens will be hard-excluded from meal plan recommendations.</p>
        )}
      </Field>
      <Field label="Food Dislikes (Soft Filter — warnings only)">
        <TagInput tags={assessment.food_dislikes ?? []} onChange={v => updateField("food_dislikes", v)} placeholder="Type disliked food and press Enter..." />
      </Field>
      <Field label="Medications" span={2}>
        <TagInput tags={assessment.medications ?? []} onChange={v => updateField("medications", v)} placeholder="Type medication and press Enter..." />
      </Field>
    </div>
  );

  const renderBiochemicalTab = () => (
    <div className="space-y-5">
      <div>
        <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-3">Lab Values</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {LAB_FIELDS.map(field => {
            const rawValue = (assessment.biochemical_data?.[field.key as keyof NonNullable<typeof assessment.biochemical_data>] ?? "") as string | number;
            const numValue = rawValue !== "" ? Number(rawValue) : null;
            const status = numValue !== null ? getLabStatus(numValue, field, patientSex) : "normal";
            const { low, high } = getLabRange(field, patientSex);
            const isAbnormal = status !== "normal";

            return (
              <div
                key={field.key}
                className={`rounded-xl border p-3 transition-colors ${
                  isAbnormal
                    ? "border-red-200 bg-red-50/40"
                    : "border-zinc-200 bg-white"
                }`}
              >
                <label className="block text-[10px] font-bold uppercase tracking-wider mb-1.5 flex items-center justify-between">
                  <span className={isAbnormal ? "text-red-700" : "text-zinc-500"}>
                    {field.label}
                  </span>
                  {isAbnormal && (
                    <span className={`text-[9px] font-extrabold px-1.5 py-0.5 rounded ${
                      status === "low"
                        ? "bg-blue-100 text-blue-700"
                        : "bg-red-100 text-red-700"
                    }`}>
                      {status === "low" ? "LOW" : "HIGH"}
                    </span>
                  )}
                </label>
                <div className="relative">
                  <input
                    type="number"
                    value={rawValue}
                    onChange={e => updateField("biochemical_data", {
                      ...assessment.biochemical_data,
                      [field.key]: coerceBiochemicalValue(field.key, e.target.value),
                    })}
                    placeholder={`e.g. ${low ?? high}`}
                    className={`w-full px-3 py-2 text-xs bg-white border rounded-lg text-zinc-900 focus:outline-none focus:ring-2 transition-all placeholder:text-zinc-400 ${
                      isAbnormal
                        ? "border-red-300 focus:ring-red-400/20 focus:border-red-400"
                        : "border-zinc-200 focus:ring-emerald-500/20 focus:border-emerald-500"
                    }`}
                  />
                  {field.unit && (
                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-zinc-400 pointer-events-none">
                      {field.unit}
                    </span>
                  )}
                </div>
                <div className="mt-1.5 space-y-0.5">
                  <p className="text-[9px] text-zinc-400">
                    Normal: {low !== null ? low : "—"} – {high !== null ? high : "—"}{field.unit ? ` ${field.unit}` : ""}
                  </p>
                  {field.note && (
                    <p className="text-[9px] text-zinc-400 italic leading-relaxed">{field.note}</p>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>
      {/* Upload sits BELOW the lab values so the RND enters data first, then attaches proof. */}
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
      <div className="space-y-5">
        <div className="grid gap-4 lg:grid-cols-[1.4fr_0.6fr]">
          <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h4 className="text-sm font-extrabold text-zinc-950 uppercase tracking-wider flex items-center gap-2">
                  <FileText className="h-4 w-4 text-emerald-600" />
                  Referral / Screening Form
                </h4>
                <p className="text-[11px] text-zinc-500 mt-1 leading-relaxed">
                  Enter screening demographics below manually. Edits to demographics will persist back to the patient profile on save.
                </p>
              </div>
              <span className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${
                screeningType === "pediatric"
                  ? "bg-sky-50 text-sky-700 border-sky-200"
                  : "bg-emerald-50 text-emerald-700 border-emerald-200"
              }`}>
                {screeningType === "pediatric" ? "Pediatric B.06" : "Adult B.07"}
              </span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field label="Patient Name">
                <TextInput value={draft?.patientName ?? ""} onChange={v => updateScreeningDraftField("patientName", v)} placeholder="Patient name" />
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
              <Field label="Medical Diagnosis" span={2}>
                <TextArea
                  value={draft?.diagnosis ?? ""}
                  onChange={v => updateScreeningDraftField("diagnosis", v)}
                  placeholder="Medical diagnosis or admitting impression"
                  rows={3}
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
              <Field label="Referral Date & Time" span={2}>
                <TextInput type="datetime-local" value={draft?.referralDatetime ?? ""} onChange={v => updateScreeningDraftField("referralDatetime", v)} />
              </Field>
            </div>

            <div className="flex flex-col gap-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Screening Type:</span>
                <div className="flex gap-2">
                  {(["adult", "pediatric"] as const).map(t => (
                    <button
                      key={t}
                      type="button"
                      onClick={() => {
                        updateScreeningDraftField("screeningType", t);
                        setSectionAChecks(new Array(getScreeningConditions(t).length).fill(false));
                        setSectionBChecks(new Array(getScreeningIntakeHistory(t).length).fill(false));
                      }}
                      className={`px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg border transition-all cursor-pointer ${
                        screeningType === t
                          ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                          : "bg-white text-zinc-500 border-zinc-200 hover:border-zinc-300"
                      }`}
                    >
                      {t === "adult" ? "Adult B.07" : "Pediatric B.06"}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-4">
            <AttachmentsPanel
              ncpId={ncpId}
              kind="referral"
              uploadLabel="Upload Referral / Screening Form"
              blurb="Attach referral and screening forms for this NCP cycle. Stored for record-keeping and appended to the printed NCP report."
            />
            <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-5 text-zinc-100 shadow-sm">
              <h4 className="text-[10px] font-extrabold uppercase tracking-wider text-emerald-300 mb-2">Workflow Note</h4>
              <p className="text-[11px] leading-relaxed text-zinc-300">
                Save after entering screening details. The patient profile updates with the editable demographics captured here. Risk scoring is calculated based on assessment and biochemical data.
              </p>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm">
            <h4 className="text-[10px] font-extrabold text-zinc-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Shield className="h-3.5 w-3.5 text-emerald-600" />
              Section A - Clinical Conditions
            </h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
              {getScreeningConditions(screeningType).map((cond, i) => (
                <label key={i} className="flex items-start gap-2 text-[11px] text-zinc-700 cursor-pointer hover:bg-zinc-50 p-1.5 rounded-lg transition-colors">
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

          <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm">
            <h4 className="text-[10px] font-extrabold text-zinc-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <Scale className="h-3.5 w-3.5 text-emerald-600" />
              Section B - Intake / Weight History
            </h4>
            <div className="grid grid-cols-1 gap-2">
              {getScreeningIntakeHistory(screeningType).map((item, i) => (
                <label key={i} className="flex items-start gap-2 text-[11px] text-zinc-700 cursor-pointer hover:bg-zinc-50 p-1.5 rounded-lg transition-colors">
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
        </div>
      </div>
    );
  };

  const renderRiskScore = () => (
    <div className="bg-white border border-zinc-200 rounded-xl p-5 space-y-4">
      <div className="flex items-center justify-between">
        <h4 className="text-[10px] font-extrabold text-zinc-800 uppercase tracking-wider flex items-center gap-1.5">
          <Activity className="h-3.5 w-3.5 text-emerald-600" />
          Scoring of Nutritional Risk Related Factors
        </h4>
        <span className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${riskInfo.color}`}>
          {riskInfo.label} — {riskScore} pts
        </span>
      </div>
      <div className="space-y-2">
        {RISK_FACTORS.map((factor, i) => (
          <div key={i} className="flex items-center justify-between gap-3 text-[11px] text-zinc-700 px-3 py-2 rounded-lg border border-transparent">
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={riskChecks[i]}
                readOnly
                className="shrink-0 accent-emerald-600 cursor-default"
              />
              <span className="leading-tight">{factor.label}</span>
            </div>
            <span className="text-[9px] font-bold text-zinc-400 shrink-0">{factor.points} pt{factor.points > 1 ? "s" : ""}</span>
          </div>
        ))}
      </div>
      {riskChecks[2] && (
        <div className="grid grid-cols-2 gap-3 pl-6 pt-1">
          <Field label="Weight Loss %">
            <TextInput type="number" value={String(assessment.weight_loss_percentage ?? "")} onChange={v => updateField("weight_loss_percentage", v ? Number(v) : null)} placeholder="%" />
          </Field>
          <Field label="Over Period">
            <TextInput value={s("weight_loss_period")} onChange={v => updateField("weight_loss_period", v)} placeholder="e.g. 3 months" />
          </Field>
        </div>
      )}
    </div>
  );

  const renderSummaryTab = () => (
    <div className="space-y-4">
      <Field label="RND Summary (Clinical Observations)">
        <TextArea
          value={s("rnd_summary")}
          onChange={v => updateField("rnd_summary", v)}
          placeholder="Summarize clinical observations, reassessment needs, and overall nutritional status..."
          rows={8}
        />
      </Field>
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
      <div className="flex items-center gap-1.5 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
        <ChevronRight className="h-3 w-3" />
        <Link href={`/ncp/patients/${patientId}`} className="hover:text-emerald-700 transition-colors">{patient?.name ?? systemId}</Link>
        <ChevronRight className="h-3 w-3" />
        <span className="text-zinc-700 font-bold">Assessment</span>
      </div>

      {/* Persistent Patient Header */}
      <div className="bg-white border border-zinc-200 rounded-xl px-5 py-3.5 shadow-sm">
        <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
          <div className="flex items-center gap-2.5">
            <div className="h-8 w-8 bg-emerald-50 border border-emerald-100 rounded-lg flex items-center justify-center text-emerald-700">
              <Heart className="h-4 w-4" />
            </div>
            <div>
              <h2 className="text-sm font-extrabold text-zinc-950 tracking-tight">{patient?.name ?? "Loading..."}</h2>
              <p className="text-[10px] font-mono text-zinc-400">{systemId}</p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-3 text-[10px]">
            {patient?.ward && (
              <span className="px-2 py-0.5 bg-zinc-100 text-zinc-700 rounded font-bold">Ward: {patient.ward}</span>
            )}
            {patient?.medical_diagnosis && (
              <span className="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-100 rounded font-bold">Dx: {patient.medical_diagnosis}</span>
            )}
            {allergies.length > 0 && allergies.map((a, i) => (
              <span key={i} className="px-2 py-0.5 bg-red-50 text-red-700 border border-red-100 rounded font-extrabold uppercase tracking-wider">
                ⚠ {a}
              </span>
            ))}
            {riskScore > 0 && (
              <span className={`px-2 py-0.5 rounded font-extrabold uppercase tracking-wider border ${riskInfo.color}`}>
                Risk: {riskInfo.label}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Status Messages */}
      {error && (
        <div className="px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700 font-bold flex items-center gap-2">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" /> {error}
        </div>
      )}
      {success && (
        <div className="px-4 py-3 bg-emerald-50 border border-emerald-100 rounded-xl text-xs text-emerald-700 font-bold flex items-center gap-2">
          <ClipboardCheck className="h-3.5 w-3.5 shrink-0" /> {success}
        </div>
      )}

      {/* Tab Navigation */}
      <div className="bg-white border border-zinc-200 rounded-xl overflow-hidden shadow-sm">
        <div className="flex overflow-x-auto border-b border-zinc-200 bg-zinc-50/50">
          {TABS.map(tab => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.key;
            return (
              <button
                key={tab.key}
                type="button"
                onClick={() => setActiveTab(tab.key)}
                className={`flex items-center gap-1.5 px-4 py-3 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap border-b-2 transition-all cursor-pointer ${
                  isActive
                    ? "text-emerald-700 border-emerald-600 bg-white"
                    : "text-zinc-500 border-transparent hover:text-zinc-700 hover:bg-white/50"
                }`}
              >
                <Icon className="h-3.5 w-3.5" />
                {tab.label}
              </button>
            );
          })}
        </div>

        {/* Tab Content */}
        <div className="p-5">
          {tabContent[activeTab]}
        </div>

        {/* Risk Score — shown only on Tab F (Summary) so it appears once, not on Tab E */}
        {activeTab === "summary" && (
          <div className="px-5 pb-5">
            {renderRiskScore()}
          </div>
        )}
      </div>

      {/* Save Bar */}
      <div className="bg-white border border-zinc-200 rounded-xl px-5 py-3.5 flex items-center justify-between shadow-sm">
        <div className="text-[10px] text-zinc-500 font-semibold select-none">
          NCP Cycle #{ncpId} • All tabs auto-merge into a single save
        </div>
        <button
          type="button"
          onClick={handleSave}
          disabled={saving}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 disabled:bg-emerald-400 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed shadow-sm"
        >
          <Save className="h-3.5 w-3.5" />
          {saving ? "Saving..." : "Save Assessment"}
        </button>
      </div>
    </div>
  );
}
