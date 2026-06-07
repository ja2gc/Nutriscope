"use client";

import React, { use, useCallback, useEffect, useState, useRef } from "react";
import Link from "next/link";
import {
  ClipboardCheck, Utensils, Ruler, UserRound, FlaskConical,
  FileText, Sparkles, Save, Upload, AlertTriangle, Shield,
  ChevronRight, Activity, Scale, Heart
} from "lucide-react";
import { fetchPatientById, Patient } from "@/services/patientService";
import {
  calcIBW, calcAjBW, calcPercentIBW, calcBMR, calcTEE, calcBmrWeight,
  classifyNutritionalStatus, ACTIVITY_FACTORS,
} from "@/lib/nutritionCalculations";
import {
  Assessment, fetchAssessment, saveAssessment,
  fetchScreeningDocument,
  fetchOcrDocuments,
  ScreeningDocumentRecord,
  OcrDocumentRecord,
  uploadScreeningDocument, uploadLabsDocument,
  approveScreeningDocument,
  getScreeningDocumentFileUrl,
  getOcrDocumentFileUrl,
} from "@/services/assessmentService";

// ─── Constants ───────────────────────────────────────────────────────────
const COMMON_ALLERGENS = ["milk", "eggs", "fish", "shellfish", "tree nuts", "peanuts", "wheat", "soybeans"];

const TABS = [
  { key: "dietary", label: "A: Dietary History", icon: Utensils },
  { key: "anthropometric", label: "B: Anthropometrics", icon: Ruler },
  { key: "client", label: "C: Client History", icon: UserRound },
  { key: "biochemical", label: "D: Biochemical / Labs", icon: FlaskConical },
  { key: "referral", label: "E: Referral / Screening", icon: FileText },
  { key: "summary", label: "F: RND Summary", icon: Sparkles },
] as const;

type TabKey = (typeof TABS)[number]["key"];

type ScreeningDraft = {
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
  { label: "Screening criteria for potential nutritional risk", points: 1 },
  { label: "Less than 85% or greater than 130% ideal body weight", points: 1 },
  { label: "Unintentional weight loss over weeks/months", points: 2 },
  { label: "Mechanical / digestive problem", points: 1 },
  { label: "Low albumin", points: 1 },
  { label: "Significant lab result", points: 1 },
  { label: "Other/s", points: 1 },
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

const LAB_FIELDS: { key: string; label: string; unit: string }[] = [
  { key: "albumin", label: "Albumin", unit: "g/dL" },
  { key: "hematocrit", label: "Hematocrit", unit: "%" },
  { key: "bun", label: "BUN", unit: "mg/dL" },
  { key: "hemoglobin", label: "Hemoglobin", unit: "g/dL" },
  { key: "calcium", label: "Calcium", unit: "mg/dL" },
  { key: "ldl", label: "LDL", unit: "mg/dL" },
  { key: "cholesterol", label: "Cholesterol", unit: "mg/dL" },
  { key: "phosphate", label: "Phosphate", unit: "mg/dL" },
  { key: "creatinine", label: "Creatinine", unit: "mg/dL" },
  { key: "potassium", label: "Potassium", unit: "mmol/L" },
  { key: "glucose", label: "Glucose", unit: "mg/dL" },
  { key: "sodium", label: "Sodium", unit: "mmol/L" },
  { key: "hba1c", label: "HbA1C", unit: "%" },
  { key: "triglycerides", label: "Triglycerides", unit: "mg/dL" },
  { key: "hdl", label: "HDL", unit: "mg/dL" },
  { key: "urr", label: "URR", unit: "%" },
  { key: "bp", label: "BP", unit: "mmHg" },
  { key: "abg", label: "ABG", unit: "various" },
];

// ─── Helpers ─────────────────────────────────────────────────────────────
function confidenceBorder(score: number | undefined | null): string {
  if (score === undefined || score === null) return "border-zinc-200";
  if (score > 0.8) return "border-emerald-400";
  if (score >= 0.5) return "border-amber-400";
  return "border-red-400";
}

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

function calculateAgeFromDob(dob?: string | null) {
  if (!dob) {
    return "N/A";
  }

  const birthDate = new Date(dob);
  if (Number.isNaN(birthDate.getTime())) {
    return "N/A";
  }

  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDelta = today.getMonth() - birthDate.getMonth();

  if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
    age -= 1;
  }

  return `${age}`;
}

function normalizeChecklistEntries(value: unknown): string[] {
  if (!value) {
    return [];
  }

  if (Array.isArray(value)) {
    return value.filter((entry): entry is string => typeof entry === "string");
  }

  if (typeof value === "string") {
    return value
      .split(/[,;\n]/)
      .map((entry) => entry.trim())
      .filter(Boolean);
  }

  if (typeof value === "object") {
    return Object.entries(value as Record<string, unknown>)
      .filter(([, entry]) => Boolean(entry))
      .map(([entry]) => entry);
  }

  return [];
}

function resolveChecklistState(labels: string[], value: unknown) {
  const normalized = normalizeChecklistEntries(value).map((entry) => entry.toLowerCase());

  if (normalized.length === 0) {
    return new Array(labels.length).fill(false);
  }

  return labels.map((label) => {
    const lowerLabel = label.toLowerCase();
    return normalized.some((entry) => entry === lowerLabel || entry.includes(lowerLabel) || lowerLabel.includes(entry));
  });
}

function toText(value: unknown, fallback = ""): string {
  if (typeof value === "string") {
    return value;
  }

  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }

  return fallback;
}

function toNumber(value: unknown): number | null {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function normalizeScreeningType(value: unknown): "adult" | "pediatric" {
  return value === "pediatric" ? "pediatric" : "adult";
}

function getScreeningType(
  patient: Patient | null,
  documentData: ScreeningDocumentRecord | null
): "adult" | "pediatric" {
  if (documentData?.type === "adult" || documentData?.type === "pediatric") {
    return documentData.type;
  }

  return normalizeScreeningType(patient?.screening_type);
}

function getScreeningConditions(type: "adult" | "pediatric") {
  return type === "pediatric" ? PEDIATRIC_CLINICAL_CONDITIONS : ADULT_CLINICAL_CONDITIONS;
}

function getScreeningIntakeHistory(type: "adult" | "pediatric") {
  return type === "pediatric" ? PEDIATRIC_INTAKE_WEIGHT_HISTORY : ADULT_INTAKE_WEIGHT_HISTORY;
}

function asChecklistItems(value: unknown): string[] {
  return normalizeChecklistEntries(value);
}

function buildMappedFields(
  draft: ScreeningDraft | null,
  type: "adult" | "pediatric",
  sectionAChecks: boolean[],
  sectionBChecks: boolean[]
) {
  return {
    screening_type: type,
    patient_name: draft?.patientName ?? "",
    age: draft?.age ?? "",
    sex: draft?.sex ?? "",
    address: draft?.address ?? "",
    height: draft?.height ?? "",
    weight: draft?.weight ?? "",
    clinical_conditions: getScreeningConditions(type).filter((label, index) => sectionAChecks[index]),
    intake_weight_history: getScreeningIntakeHistory(type).filter((label, index) => sectionBChecks[index]),
    diagnosis: draft?.diagnosis ?? "",
    diet_prescription: draft?.dietPrescription ?? "",
    referral_type: draft?.referralType ?? "",
    referred_by: draft?.referredBy ?? "",
    referral_datetime: draft?.referralDatetime ?? "",
  };
}

// ─── Field Components ────────────────────────────────────────────────────
function Field({ label, children, span }: { label: string; children: React.ReactNode; span?: number }) {
  return (
    <div className={span ? `col-span-${span}` : ""} style={span ? { gridColumn: `span ${span}` } : undefined}>
      <label className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1.5">{label}</label>
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
          <p className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Processing OCR extraction...</p>
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
  const [labValues, setLabValues] = useState<Record<string, string>>({});
  const [labConfidence, setLabConfidence] = useState<Record<string, number>>({});
  const [riskChecks, setRiskChecks] = useState<boolean[]>(new Array(7).fill(false));
  const [sectionAChecks, setSectionAChecks] = useState<boolean[]>(new Array(ADULT_CLINICAL_CONDITIONS.length).fill(false));
  const [sectionBChecks, setSectionBChecks] = useState<boolean[]>(new Array(ADULT_INTAKE_WEIGHT_HISTORY.length).fill(false));
  const [screeningDocument, setScreeningDocument] = useState<ScreeningDocumentRecord | null>(null);
  const [screeningDraft, setScreeningDraft] = useState<ScreeningDraft | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploadingScreening, setUploadingScreening] = useState(false);
  const [uploadingLabs, setUploadingLabs] = useState(false);
  const [pollingScreening, setPollingScreening] = useState(false);
  const [pollingLabs, setPollingLabs] = useState(false);
  const [latestLabDocument, setLatestLabDocument] = useState<OcrDocumentRecord | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const pollingScreeningRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const pollingLabsRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const buildScreeningDraft = useCallback((
    basePatient: Patient,
    documentData?: ScreeningDocumentRecord | null,
    baseAssessment?: Assessment | null
  ): ScreeningDraft => {
    const extracted = documentData?.mapped_fields ?? documentData?.extracted_data ?? {};

    return {
      patientName: toText(extracted.patient_name ?? extracted.name ?? basePatient.name),
      age: toText(extracted.age ?? calculateAgeFromDob(basePatient.dob)),
      sex: toText(extracted.sex ?? basePatient.sex),
      address: toText(extracted.address ?? basePatient.address),
      height: toText(extracted.height ?? baseAssessment?.height ?? ""),
      weight: toText(extracted.weight ?? baseAssessment?.weight ?? ""),
      diagnosis: toText(extracted.diagnosis ?? extracted.medical_diagnosis ?? basePatient.medical_diagnosis),
      dietPrescription: toText(extracted.diet_prescription ?? extracted.dietPrescription ?? ""),
      referralType: toText(extracted.referral_type ?? extracted.referralType ?? ""),
      referredBy: toText(extracted.referred_by ?? extracted.physician ?? basePatient.physician),
      referralDatetime: toText(extracted.referral_datetime ?? extracted.referralDateTime ?? ""),
      ward: toText(extracted.ward ?? basePatient.ward ?? ""),
      hospitalNumber: toText(extracted.hospital_number ?? basePatient.hospital_number ?? ""),
      ageGroupCategory: toText(extracted.age_group_category ?? basePatient.age_group_category ?? ""),
      screeningType: (extracted.screening_type ?? basePatient.screening_type ?? "adult") === "pediatric" ? "pediatric" : "adult",
    };
  }, []);

  // ─── Load Data ──────────────────────────────────────────────────────
  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    try {
      setLoading(true);
      const [p, a, sDoc, ocrDocs] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchAssessment(ncpId),
        fetchScreeningDocument(ncpId),
        fetchOcrDocuments(ncpId),
      ]);
      const loadedPatient = p.status === "fulfilled" ? p.value : null;
      const loadedAssessment = a.status === "fulfilled" ? a.value : null;
      const loadedScreeningDocument = sDoc.status === "fulfilled" ? sDoc.value : null;

      if (p.status === "fulfilled") {
        setPatient(p.value);
      }
      if (a.status === "fulfilled") {
        setAssessment(a.value);
        setAssessmentExists(true);
      }
      if (sDoc.status === "fulfilled") {
        setScreeningDocument(sDoc.value);
        const screeningTypeValue = getScreeningType(loadedPatient, loadedScreeningDocument);
        const extracted = sDoc.value?.mapped_fields ?? sDoc.value?.extracted_data ?? {};
        setSectionAChecks(resolveChecklistState(getScreeningConditions(screeningTypeValue), extracted.clinical_conditions ?? extracted.section_a ?? extracted.section_a_checks));
        setSectionBChecks(resolveChecklistState(getScreeningIntakeHistory(screeningTypeValue), extracted.intake_weight_history ?? extracted.section_b ?? extracted.section_b_checks));
        if (Object.keys(extracted).length > 0) {
          setAssessment((current) => ({
            ...current,
            weight: current.weight ?? toNumber(extracted.weight) ?? current.weight,
            height: current.height ?? toNumber(extracted.height) ?? current.height,
            usual_weight: toNumber(extracted.usual_weight) ?? current.usual_weight,
            present_diet: toText(extracted.present_diet, current.present_diet ?? ""),
            dietary_intake: toText(extracted.dietary_intake, current.dietary_intake ?? ""),
            appetite_changes: toText(extracted.appetite_changes, current.appetite_changes ?? ""),
            dietary_restrictions: toText(extracted.dietary_restrictions, current.dietary_restrictions ?? ""),
            weight_loss_percentage: toNumber(extracted.weight_loss_percentage) ?? current.weight_loss_percentage,
            weight_loss_period: toText(extracted.weight_loss_period, current.weight_loss_period ?? ""),
            ibw_percentage: toNumber(extracted.ibw_percentage) ?? current.ibw_percentage,
            food_intolerance: toText(extracted.food_intolerance, current.food_intolerance ?? ""),
            nutrient_drug_interaction: toText(extracted.nutrient_drug_interaction, current.nutrient_drug_interaction ?? ""),
          }));
        }
      }
      if (loadedPatient) {
        setScreeningDraft(buildScreeningDraft(loadedPatient, loadedScreeningDocument, loadedAssessment));
      }
      if (ocrDocs.status === "fulfilled" && ocrDocs.value.length > 0) {
        const latestDoc = ocrDocs.value[ocrDocs.value.length - 1];
        setLatestLabDocument(latestDoc);
        const parsedFields = latestDoc?.parsed_fields ?? {};
        const confidenceScore = typeof latestDoc?.confidence_score === "number"
          ? latestDoc.confidence_score
          : Number(latestDoc?.confidence_score ?? 0);
        setLabValues((current) => ({
          ...current,
          ...Object.fromEntries(
            Object.entries(parsedFields).map(([key, value]) => [key, value === null || value === undefined ? "" : String(value)]),
          ),
        }));
        setLabConfidence((current) => ({
          ...current,
          ...Object.fromEntries(
            Object.keys(parsedFields).map((key) => [key, confidenceScore || 0.85]),
          ),
        }));
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

  // ─── Risk Score Calc ────────────────────────────────────────────────
  const riskScore = riskChecks.reduce((sum, checked, i) => checked ? sum + RISK_FACTORS[i].points : sum, 0);
  const riskInfo = riskBadge(riskScore);

  // ─── Field Updater ──────────────────────────────────────────────────
  const updateField = (key: keyof Assessment, value: unknown) => {
    setAssessment(prev => ({ ...prev, [key]: value }));
  };

  const s = (key: keyof Assessment): string => (assessment[key] as string) ?? "";

  const updateScreeningDraftField = (key: keyof ScreeningDraft, value: string) => {
    setScreeningDraft((current) => {
      const next = current ?? (patient ? buildScreeningDraft(patient) : null);
      if (!next) {
        return current;
      }

      return { ...next, [key]: value };
    });
  };

  // ─── Polling Helpers ────────────────────────────────────────────────
  const startScreeningPolling = useCallback((docId: number | string) => {
    if (pollingScreeningRef.current) clearInterval(pollingScreeningRef.current);
    setPollingScreening(true);
    let attempts = 0;
    const maxAttempts = 24; // 24 × 2.5s = 60s timeout

    pollingScreeningRef.current = setInterval(async () => {
      attempts++;
      try {
        const refreshed = await fetchScreeningDocument(ncpId);
        if (refreshed?.status === "completed" || refreshed?.status === "failed") {
          clearInterval(pollingScreeningRef.current!);
          pollingScreeningRef.current = null;
          setPollingScreening(false);
          setScreeningDocument(refreshed);

          if (refreshed?.status === "completed" && patient) {
            const extracted = refreshed.mapped_fields ?? refreshed.extracted_data ?? {};
            const screeningTypeValue = getScreeningType(patient, refreshed);
            setSectionAChecks(resolveChecklistState(getScreeningConditions(screeningTypeValue), extracted.clinical_conditions ?? extracted.section_a ?? extracted.section_a_checks));
            setSectionBChecks(resolveChecklistState(getScreeningIntakeHistory(screeningTypeValue), extracted.intake_weight_history ?? extracted.section_b ?? extracted.section_b_checks));
            setScreeningDraft(buildScreeningDraft(patient, refreshed, assessment));
            // Hydrate weight/height into assessment if blank
            setAssessment((prev) => ({
              ...prev,
              weight: prev.weight ?? (extracted.weight ? Number(extracted.weight) : null),
              height: prev.height ?? (extracted.height ? Number(extracted.height) : null),
            }));
            setSuccess("Screening form extracted and fields auto-filled.");
            setTimeout(() => setSuccess(null), 5000);
          } else if (refreshed?.status === "failed") {
            setError("OCR extraction failed. Please try uploading again.");
          }
        } else if (attempts >= maxAttempts) {
          clearInterval(pollingScreeningRef.current!);
          pollingScreeningRef.current = null;
          setPollingScreening(false);
          setError("OCR extraction timed out. The document was saved but fields were not auto-filled.");
        }
      } catch {
        // Keep polling silently on transient errors
      }
    }, 2500);
  }, [ncpId, patient, assessment, buildScreeningDraft]);

  const startLabsPolling = useCallback((docId: number | string) => {
    if (pollingLabsRef.current) clearInterval(pollingLabsRef.current);
    setPollingLabs(true);
    let attempts = 0;
    const maxAttempts = 24;

    pollingLabsRef.current = setInterval(async () => {
      attempts++;
      try {
        const allDocs = await fetchOcrDocuments(ncpId);
        const target = allDocs.find((d) => d.id === docId) ?? allDocs[allDocs.length - 1];
        if (target?.status === "completed" || target?.status === "failed") {
          clearInterval(pollingLabsRef.current!);
          pollingLabsRef.current = null;
          setPollingLabs(false);
          if (target.status === "completed") {
            setLatestLabDocument(target);
            const parsedFields = target.parsed_fields ?? {};
            const confidenceScore = typeof target.confidence_score === "number"
              ? target.confidence_score
              : Number(target.confidence_score ?? 0);
            setLabValues((prev) => ({
              ...prev,
              ...Object.fromEntries(
                Object.entries(parsedFields).map(([k, v]) => [k, v === null || v === undefined ? "" : String(v)]),
              ),
            }));
            setLabConfidence((prev) => ({
              ...prev,
              ...Object.fromEntries(Object.keys(parsedFields).map((k) => [k, confidenceScore || 0.85])),
            }));
            setSuccess("Lab results extracted and fields auto-filled.");
            setTimeout(() => setSuccess(null), 5000);
          } else {
            setError("Lab OCR extraction failed. Please try uploading again.");
          }
        } else if (attempts >= maxAttempts) {
          clearInterval(pollingLabsRef.current!);
          pollingLabsRef.current = null;
          setPollingLabs(false);
          setError("Lab OCR extraction timed out.");
        }
      } catch {
        // Keep polling silently
      }
    }, 2500);
  }, [ncpId]);

  // Clean up intervals on unmount
  useEffect(() => {
    return () => {
      if (pollingScreeningRef.current) clearInterval(pollingScreeningRef.current);
      if (pollingLabsRef.current) clearInterval(pollingLabsRef.current);
    };
  }, []);

  // ─── Save ───────────────────────────────────────────────────────────
  const handleSave = async () => {
    try {
      setSaving(true);
      setError(null);
      setSuccess(null);
      const toSave: Partial<Assessment> = {
        ...assessment,
        bmi: computedBmi,
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

      if (activeTab === "referral") {
        if (!screeningDocument?.id) {
          throw new Error("No screening document is available to approve.");
        }

        const currentScreeningType = screeningDraft?.screeningType ?? getScreeningType(patient, screeningDocument);
        const mappedFields = buildMappedFields(
          screeningDraft ?? (patient ? buildScreeningDraft(patient, screeningDocument, saved) : null),
          currentScreeningType,
          sectionAChecks,
          sectionBChecks
        );

        const approvedDocument = await approveScreeningDocument(screeningDocument.id, { mapped_fields: mappedFields });
        setScreeningDocument(approvedDocument);

        const [refreshedAssessment, refreshedPatient] = await Promise.all([
          fetchAssessment(ncpId),
          fetchPatientById(patientId),
        ]);

        setAssessment(refreshedAssessment);
        setPatient(refreshedPatient);
        setScreeningDraft(buildScreeningDraft(refreshedPatient, approvedDocument, refreshedAssessment));
      }

      setSuccess(activeTab === "referral" ? "Screening form approved successfully." : "Assessment saved successfully.");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to save assessment.");
    } finally {
      setSaving(false);
    }
  };

  // ─── Clear OCR Document Handlers ────────────────────────────────────
  const handleClearLabDocument = () => {
    setLatestLabDocument(null);
    setLabValues({});
    setLabConfidence({});
  };

  const handleClearScreeningDocument = () => {
    setScreeningDocument(null);
    setScreeningDraft(patient ? buildScreeningDraft(patient, null, assessment) : null);
  };

  // ─── Upload Handlers ────────────────────────────────────────────────
  const handleScreeningUpload = async (file: File) => {
    try {
      setUploadingScreening(true);
      setError(null);
      const uploaded = await uploadScreeningDocument(ncpId, file);
      // Immediately set a pending document so the preview renders
      const pending = await fetchScreeningDocument(ncpId);
      setScreeningDocument(pending);
      setUploadingScreening(false);
      setSuccess("Screening document uploaded. Extracting fields...");
      // Start polling for OCR completion
      const docId = uploaded?.id ?? pending?.id;
      if (docId) startScreeningPolling(docId);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to upload screening document.");
      setUploadingScreening(false);
    }
  };

  const handleLabsUpload = async (file: File) => {
    try {
      setUploadingLabs(true);
      setError(null);
      const uploaded = await uploadLabsDocument(ncpId, file);
      setLatestLabDocument(uploaded);
      setUploadingLabs(false);
      setSuccess("Lab document uploaded. Extracting fields...");
      // Start polling for OCR completion
      const docId = uploaded?.id;
      if (docId) startLabsPolling(docId);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to upload lab document.");
      setUploadingLabs(false);
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
        <TextArea value={s("appetite_changes")} onChange={v => updateField("appetite_changes", v)} placeholder="Changes in appetite..." rows={2} />
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
      {/* ── Measurements ─────────────────────────────────────────────── */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Field label="Weight (kg)">
          <TextInput type="number" value={String(assessment.weight ?? "")} onChange={v => updateField("weight", v ? Number(v) : null)} placeholder="e.g. 70.5" />
        </Field>
        <Field label="Usual Weight (kg)">
          <TextInput type="number" value={String(assessment.usual_weight ?? "")} onChange={v => updateField("usual_weight", v ? Number(v) : null)} placeholder="e.g. 72.0" />
        </Field>
        <Field label="Height (cm)">
          <TextInput type="number" value={String(assessment.height ?? "")} onChange={v => updateField("height", v ? Number(v) : null)} placeholder="e.g. 170" />
        </Field>
        <Field label="MUAC (mm)">
          <TextInput type="number" value={String(assessment.muac_mm ?? "")} onChange={v => updateField("muac_mm", v ? Number(v) : null)} placeholder="e.g. 250" />
        </Field>
        <Field label="Waist Circumference (cm)">
          <TextInput type="number" value={String(assessment.waist_cm ?? "")} onChange={v => updateField("waist_cm", v ? Number(v) : null)} placeholder="e.g. 90" />
        </Field>
        <Field label="Hip Circumference (cm)">
          <TextInput type="number" value={String(assessment.hip_cm ?? "")} onChange={v => updateField("hip_cm", v ? Number(v) : null)} placeholder="e.g. 100" />
        </Field>
        <Field label="Weight Loss %">
          <TextInput type="number" value={String(assessment.weight_loss_percentage ?? "")} onChange={v => updateField("weight_loss_percentage", v ? Number(v) : null)} placeholder="e.g. 5.0" />
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

      {/* ── Auto-Calculated Panel ────────────────────────────────────── */}
      {weight > 0 && height > 0 && (
        <div className="bg-zinc-50 border border-zinc-200 rounded-xl p-4">
          <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
            <Activity className="h-3 w-3" /> Calculated Values
          </p>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {/* BMI */}
            <div className="bg-white border border-zinc-200 rounded-lg p-3">
              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">BMI</p>
              <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                {computedBmi !== null ? computedBmi.toFixed(1) : "—"}
              </p>
              <p className="text-[9px] text-zinc-500">kg/m²</p>
            </div>
            {/* IBW */}
            <div className="bg-white border border-zinc-200 rounded-lg p-3">
              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">IBW</p>
              <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                {computedIBW !== null ? computedIBW.toFixed(1) : "—"}
              </p>
              <p className="text-[9px] text-zinc-500">kg (Hamwi)</p>
            </div>
            {/* %IBW */}
            <div className="bg-white border border-zinc-200 rounded-lg p-3">
              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">% IBW</p>
              <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                {computedPercentIBW !== null ? computedPercentIBW.toFixed(0) : "—"}
              </p>
              <p className="text-[9px] text-zinc-500">%</p>
            </div>
            {/* AjBW — only if >120% IBW */}
            {computedAjBW !== null ? (
              <div className="bg-white border border-amber-200 rounded-lg p-3">
                <p className="text-[9px] font-bold text-amber-500 uppercase tracking-wider">AjBW</p>
                <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                  {computedAjBW.toFixed(1)}
                </p>
                <p className="text-[9px] text-zinc-500">kg (used for BMR)</p>
              </div>
            ) : (
              <div className="bg-white border border-zinc-200 rounded-lg p-3">
                <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">AjBW</p>
                <p className="text-lg font-black text-zinc-400 font-mono mt-0.5">N/A</p>
                <p className="text-[9px] text-zinc-400">not needed</p>
              </div>
            )}
            {/* BMR */}
            <div className="bg-white border border-zinc-200 rounded-lg p-3">
              <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">BMR</p>
              <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                {computedBMR !== null ? Math.round(computedBMR) : "—"}
              </p>
              <p className="text-[9px] text-zinc-500">kcal/day</p>
            </div>
            {/* TEE */}
            <div className="bg-white border border-emerald-200 rounded-lg p-3">
              <p className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">Est. TEE</p>
              <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                {computedTEE !== null ? computedTEE : "—"}
              </p>
              <p className="text-[9px] text-zinc-500">kcal/day (PAL ×{palFactor})</p>
            </div>
            {/* WHR */}
            {computedWHR !== null && (
              <div className={`bg-white border rounded-lg p-3 ${whrRisk === "High Risk" ? "border-red-200" : "border-zinc-200"}`}>
                <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">WHR</p>
                <p className="text-lg font-black text-zinc-900 font-mono mt-0.5">
                  {computedWHR.toFixed(2)}
                </p>
                <p className={`text-[9px] font-bold ${whrRisk === "High Risk" ? "text-red-500" : "text-emerald-600"}`}>
                  {whrRisk}
                </p>
              </div>
            )}
          </div>

          {/* Nutritional Status Badge */}
          {computedNutritionalStatus !== null && (
            <div className={`mt-3 p-3 rounded-lg border ${computedNutritionalStatus.colorClass}`}>
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
        </div>
      )}
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
      <div className="grid gap-4 lg:grid-cols-[1fr_auto]">
        <DropZone label="Upload Lab Results (PDF or Image) for OCR Extraction" onUpload={handleLabsUpload} uploading={uploadingLabs} />
        {latestLabDocument?.id && (
          <div className="flex flex-col items-center justify-center gap-2">
            <div className="flex items-center gap-2">
              <span className="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Lab Sheet Preview</span>
              <button type="button" onClick={handleClearLabDocument} className="text-[9px] text-zinc-400 hover:text-red-500 font-bold cursor-pointer transition-colors">✕ Remove</button>
            </div>
            <div className={`relative rounded-xl border-2 overflow-hidden bg-zinc-50 flex items-center justify-center ${
              pollingLabs ? "border-amber-300 animate-pulse" : "border-zinc-200"
            }`} style={{ width: 160, height: 200 }}>
              {pollingLabs && (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/80 z-10">
                  <div className="h-1 w-24 bg-zinc-200 rounded-full overflow-hidden">
                    <div className="h-full bg-amber-400 rounded-full animate-pulse" style={{ width: "70%" }} />
                  </div>
                  <span className="text-[9px] text-amber-700 font-bold uppercase">Extracting...</span>
                </div>
              )}
              <img
                src={getOcrDocumentFileUrl(latestLabDocument.id!)}
                alt="Lab result scan"
                className="w-full h-full object-contain"
                onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
              />
            </div>
            <span className={`text-[9px] font-bold px-2 py-0.5 rounded-full ${
              latestLabDocument.status === "completed" ? "bg-emerald-50 text-emerald-700" :
              latestLabDocument.status === "failed" ? "bg-red-50 text-red-700" :
              "bg-amber-50 text-amber-700"
            }`}>
              {latestLabDocument.status ?? "pending"}
            </span>
          </div>
        )}
      </div>
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        {LAB_FIELDS.map(lf => (
          <div key={lf.key} className={`p-3 bg-white border-2 rounded-xl transition-colors ${confidenceBorder(labConfidence[lf.key])}`}>
            <label className="block text-[9px] font-bold text-zinc-500 uppercase tracking-wider mb-1">
              {lf.label} <span className="text-zinc-400 normal-case">({lf.unit})</span>
            </label>
            <input
              type="text"
              value={labValues[lf.key] ?? ""}
              onChange={e => setLabValues(prev => ({ ...prev, [lf.key]: e.target.value }))}
              placeholder="—"
              className="w-full text-xs bg-transparent outline-none text-zinc-900 font-mono font-semibold placeholder:text-zinc-300"
            />
          </div>
        ))}
      </div>
    </div>
  );

  const renderReferralTab = () => {
    const draft = screeningDraft ?? (patient ? buildScreeningDraft(patient) : null);
    const extracted = screeningDocument?.mapped_fields ?? screeningDocument?.extracted_data ?? {};
    const screeningConfidence = screeningDocument?.confidence_score !== undefined && screeningDocument?.confidence_score !== null
      ? Number(screeningDocument.confidence_score)
      : null;

    const screeningType = draft?.screeningType ?? getScreeningType(patient, screeningDocument);

    const snapshotFields = [
      { label: "Patient Name", value: extracted.patient_name ?? extracted.name ?? draft?.patientName ?? "Not extracted yet" },
      { label: "Ward", value: extracted.ward ?? draft?.ward ?? "Not extracted yet" },
      { label: "Physician", value: extracted.physician ?? extracted.referred_by ?? draft?.referredBy ?? "Not extracted yet" },
      { label: "Diagnosis", value: extracted.medical_diagnosis ?? extracted.diagnosis ?? draft?.diagnosis ?? "Not extracted yet" },
    ];

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
                  OCR hydration fills the screening form, and edits here persist back to the patient profile on save.
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
                <TextInput value={draft?.age ?? ""} onChange={v => updateScreeningDraftField("age", v)} placeholder="Derived or OCR-hydrated age" />
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
                        const ext = screeningDocument?.mapped_fields ?? screeningDocument?.extracted_data ?? {};
                        setSectionAChecks(resolveChecklistState(getScreeningConditions(t), ext.clinical_conditions ?? ext.section_a ?? ext.section_a_checks));
                        setSectionBChecks(resolveChecklistState(getScreeningIntakeHistory(t), ext.intake_weight_history ?? ext.section_b ?? ext.section_b_checks));
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
                <span className="text-[10px] text-zinc-500 font-semibold ml-auto">
                  OCR confidence {screeningConfidence !== null && Number.isFinite(screeningConfidence)
                    ? `${Math.round(screeningConfidence * 100)}%`
                    : "pending"}
                </span>
              </div>

              <DropZone
                label="Upload Screening Form (PDF or Image) for OCR Extraction"
                onUpload={handleScreeningUpload}
                uploading={uploadingScreening}
              />
              {/* OCR status indicator while polling */}
              {pollingScreening && (
                <div className="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg">
                  <div className="h-1.5 w-1.5 bg-amber-500 rounded-full animate-ping" />
                  <span className="text-[10px] font-bold text-amber-700 uppercase tracking-wider">OCR Extraction in progress...</span>
                </div>
              )}
            </div>

            {/* Uploaded document preview */}
            {screeningDocument?.id && (
              <div className="rounded-xl border border-zinc-200 overflow-hidden bg-zinc-50">
                <div className="flex items-center justify-between px-3 py-2 bg-white border-b border-zinc-100">
                  <span className="text-[9px] font-bold uppercase tracking-wider text-zinc-500">Uploaded Document Preview</span>
                  <div className="flex items-center gap-3">
                    <span className={`text-[9px] font-bold px-2 py-0.5 rounded-full ${
                      screeningDocument.status === "completed" ? "bg-emerald-50 text-emerald-700" :
                      screeningDocument.status === "failed" ? "bg-red-50 text-red-700" :
                      "bg-amber-50 text-amber-700"
                    }`}>
                      {pollingScreening ? "Extracting..." : (screeningDocument.status ?? "pending")}
                    </span>
                    <button type="button" onClick={handleClearScreeningDocument} className="text-[9px] text-zinc-400 hover:text-red-500 font-bold cursor-pointer transition-colors">✕ Remove</button>
                  </div>
                </div>
                <div className="relative flex items-center justify-center p-2" style={{ minHeight: 240 }}>
                  {pollingScreening && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-white/80 z-10">
                      <div className="h-1.5 w-32 bg-zinc-200 rounded-full overflow-hidden">
                        <div className="h-full bg-emerald-500 rounded-full animate-pulse" style={{ width: "60%" }} />
                      </div>
                      <span className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Processing OCR...</span>
                    </div>
                  )}
                  <img
                    src={getScreeningDocumentFileUrl(screeningDocument.id!)}
                    alt="Uploaded screening form"
                    className="max-w-full max-h-64 object-contain rounded"
                    onError={(e) => {
                      const target = e.target as HTMLImageElement;
                      target.style.display = 'none';
                      const parent = target.parentElement;
                      if (parent && !parent.querySelector('.pdf-fallback')) {
                        const fallback = document.createElement('div');
                        fallback.className = 'pdf-fallback text-center text-xs text-zinc-500 py-8';
                        fallback.innerHTML = '<p class="font-bold">PDF Document</p><p class="text-[10px] mt-1">Preview not available for PDF files</p>';
                        parent.appendChild(fallback);
                      }
                    }}
                  />
                </div>
              </div>
            )}
          </div>

          <div className="space-y-4">
            <div className="bg-white border border-zinc-250 rounded-2xl p-5 shadow-sm">
              <h4 className="text-[10px] font-extrabold text-zinc-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <Sparkles className="h-3.5 w-3.5 text-emerald-600" />
                OCR Snapshot
              </h4>
              <div className="space-y-2">
                {snapshotFields.map((field) => (
                  <div key={field.label} className="rounded-lg border border-zinc-100 bg-zinc-50/80 px-3 py-2">
                    <div className="text-[9px] font-bold uppercase tracking-wider text-zinc-400">{field.label}</div>
                    <div className="text-xs font-semibold text-zinc-800 mt-0.5">{String(field.value)}</div>
                  </div>
                ))}
              </div>
            </div>

            <div className="bg-zinc-950 border border-zinc-900 rounded-2xl p-5 text-zinc-100 shadow-sm">
              <h4 className="text-[10px] font-extrabold uppercase tracking-wider text-emerald-300 mb-2">Workflow Note</h4>
              <p className="text-[11px] leading-relaxed text-zinc-300">
                Save after verifying the screening form. The patient profile updates with the editable demographics captured here, while the checklist remains the source of the risk score.
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
          <label key={i} className="flex items-center justify-between gap-3 text-[11px] text-zinc-700 cursor-pointer hover:bg-zinc-50 px-3 py-2 rounded-lg transition-colors border border-transparent hover:border-zinc-100">
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={riskChecks[i]}
                onChange={e => {
                  const next = [...riskChecks];
                  next[i] = e.target.checked;
                  setRiskChecks(next);
                }}
                className="shrink-0 accent-emerald-600"
              />
              <span className="leading-tight">{factor.label}</span>
            </div>
            <span className="text-[9px] font-bold text-zinc-400 shrink-0">{factor.points} pt{factor.points > 1 ? "s" : ""}</span>
          </label>
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

        {/* Risk Score (visible after Tab E, above Tab F) */}
        {(activeTab === "referral" || activeTab === "summary") && (
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
