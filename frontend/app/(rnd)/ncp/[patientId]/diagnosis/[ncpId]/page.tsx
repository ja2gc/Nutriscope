"use client";

import { NcpNoPatientSelected } from "@/components/ncp/NcpNoPatientSelected";

import React, { use, useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { SectionLoading } from "@/components/ui/SectionLoading";
import {
  Stethoscope, ChevronRight,
  Plus, Trash2, Pencil, AlertTriangle, CheckCircle2,
  RefreshCw, X, CheckCheck, Lock,
} from "lucide-react";
import ButtonFilterGroup from "@/components/ui/ButtonFilterGroup";
import { fetchPatientById, Patient } from "@/services/patientService";
import {
  fetchDiagnosesPage, storeDiagnosis, updateDiagnosis, deleteDiagnosis,
  aiSuggestDiagnoses, aiApproveDiagnosis, dismissPesSuggestion, fetchPesDraftStatus,
  Diagnosis, StoreDiagnosisPayload, AiSuggestion, PesDraftMeta,
} from "@/services/diagnosisService";
import { fetchAssessment, type Assessment } from "@/services/assessmentService";
import { fetchIntervention } from "@/services/interventionService";
import { buildDiagnosisProblemText, NO_DIAGNOSIS, type DiagnosisDomain, type IntakePattern } from "@/lib/diagnosisBuilder";
import { composeDiagnosisComponent, splitDiagnosisComponent } from "@/lib/diagnosisEditor";
import DiagnosisOtherField from "@/components/ncp/DiagnosisOtherField";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { InfoHint } from "@/components/ui/InfoHint";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import { NcpBreadcrumb } from "@/components/ncp/NcpBreadcrumb";
import { paginateAiDrafts } from "@/lib/aiDraftPagination";
import { NcpSectionTabs } from "@/components/ncp/NcpSectionTabs";

// ─── Domain Metadata ─────────────────────────────────────────────────────────

const DOMAIN_META = {
  NI: { label: "Intake (NI)" },
  NC: { label: "Clinical (NC)" },
  NB: { label: "Behavioral-Env (NB)" },
  NO: { label: "Other (NO)" },
} as const;

// ─── G-NCP Problem Options ────────────────────────────────────────────────────

const NI_NUTRIENTS = [
  "Energy", "Protein", "Carbohydrates", "Fat", "Fluid", "Fiber",
  "Vitamins", "Minerals", "Oral Intake", "Protein-energy",
  "Enteral nutrition infusion", "Parenteral nutrition infusion", "Bioactive substance",
  "Nutrient / substance (specify)",
];

const NI_MICRONUTRIENTS: Record<"Vitamins" | "Minerals", string[]> = {
  Vitamins: [
    "Vitamin A", "Vitamin C", "Vitamin D", "Vitamin E", "Vitamin K", "Thiamin", "Riboflavin",
    "Niacin", "Folate", "Vitamin B6", "Vitamin B12", "Pantothenic acid", "Biotin",
  ],
  Minerals: [
    "Calcium", "Chloride", "Chromium", "Copper", "Fluoride", "Iodine", "Iron", "Magnesium",
    "Manganese", "Molybdenum", "Phosphorus", "Potassium", "Selenium", "Sodium", "Zinc",
    "Sulfate", "Boron", "Cobalt",
  ],
};

const NC_PROBLEM_GROUPS = {
  "Functional": [
    "Swallowing difficulty",
    "Biting/Chewing (masticatory) difficulty",
    "Breastfeeding difficulty",
    "Altered GI function"
  ],
  "Biochemical": [
    "Impaired nutrient utilization",
    "Altered nutrition-related laboratory values (specify)",
    "Food–medication interaction (specify)",
    "Predicted food–medication interaction (specify)"
  ],
  "Weight and growth": [
    "Underweight",
    "Unintended weight loss",
    "Unintended weight gain",
    "Overweight/obesity",
    "Overweight, adult or pediatric",
    "Obese, pediatric",
    "Obese, Class I",
    "Obese, Class II",
    "Obese, Class III",
    "Suboptimal growth rate",
    "Excessive growth rate"
  ]
};
const NC_PROBLEMS = Object.values(NC_PROBLEM_GROUPS).flat();

const NB_PROBLEM_GROUPS = {
  "Knowledge and beliefs": [
    "Food- and nutrition-related knowledge deficit",
    "Unsupported beliefs/attitudes about food- or nutrition-related topics (use with caution)",
    "Not ready for diet/lifestyle change",
    "Self-monitoring deficit",
    "Disordered eating pattern",
    "Limited adherence to nutrition-related recommendations",
    "Undesirable food choices"
  ],
  "Physical activity and function": [
    "Physical inactivity",
    "Excessive physical activity",
    "Inability to manage self-care",
    "Impaired ability to prepare foods/meals",
    "Poor nutrition quality of life",
    "Self-feeding difficulty"
  ],
  "Food safety and access": [
    "Intake of unsafe food",
    "Limited access to food or water",
    "Limited access to nutrition-related supplies"
  ]
};
const NB_PROBLEMS = Object.values(NB_PROBLEM_GROUPS).flat();

// ─── G-NCP Etiology Options ───────────────────────────────────────────────────

const NI_ETIOLOGIES = [
  "Poor appetite / anorexia",
  "Nausea or vomiting",
  "Dysphagia or feeding difficulties",
  "Prescribed diet restriction",
  "Mechanical eating difficulty",
  "GI disease or malabsorption",
  "Post-surgical changes to GI tract",
  "Increased metabolic demand",
  "Medication side effects affecting intake",
];

const NC_ETIOLOGIES = [
  "Poor appetite / anorexia",
  "Energy intake exceeding needs",
  "Mechanical eating difficulty",
  "Chronic illness (DM, CKD, Cardiac, etc.)",
  "Acute illness or trauma",
  "Active infection or systemic inflammation",
  "Medications affecting nutritional status",
  "Altered GI function or motility",
  "Metabolic disorder or enzyme deficiency",
  "Reduced nutrient absorption",
  "Prolonged hospitalization",
  "Surgical intervention affecting nutrition",
];

const NB_ETIOLOGIES = [
  "Lack of nutrition knowledge or education",
  "Cultural or religious food practices",
  "Financial constraints / food insecurity",
  "Psychological distress or eating disorder",
  "Limited access to food or nutrition resources",
  "Inadequate social support",
  "Poor health literacy",
  "Motivational barriers to change",
  "Pain or mobility limitation",
  "Limited ability to prepare meals or feed independently",
];

// ─── G-NCP Signs & Symptoms Options ──────────────────────────────────────────

const NI_SIGNS = [
  "Estimated intake < 75% of estimated needs",
  "Estimated intake > 125% of estimated needs",
  "Unintentional weight loss > 5% in 3 months",
  "BMI < 18.5 (underweight)",
  "BMI ≥ 23 (overweight) or ≥ 25 (obesity), Asia-Pacific",
  "Serum albumin < 3.5 g/dL",
  "Low hemoglobin / hematocrit",
  "Muscle wasting noted on physical exam",
  "Peripheral edema or ascites",
  "Fatigue / weakness on activity",
];

const NC_SIGNS = [
  "BMI ≥ 23 (Asia-Pacific overweight / obesity)",
  "Swallowing / chewing difficulty documented",
  "Food-medication interaction documented",
  "Suboptimal energy intake documented",
  "Abnormal laboratory values (specify in notes)",
  "Significant unintended weight change",
  "Muscle wasting or loss of subcutaneous fat",
  "Poor wound healing or pressure injury",
  "Altered bowel function (constipation / diarrhea)",
  "Serum albumin < 3.5 g/dL",
  "Elevated inflammatory markers (CRP, WBC)",
  "Abnormal blood glucose levels",
  "Abnormal lipid panel values",
  "Altered kidney function markers (BUN, Creatinine)",
];

const NB_SIGNS = [
  "Patient unable to identify appropriate food choices",
  "Missed meals or skipping planned feedings",
  "Poor adherence to prescribed diet (patient report)",
  "Refuses recommended nutrition therapy",
  "Selects inappropriate foods for medical condition",
  "No self-monitoring of food intake",
  "Limited support at home for dietary compliance",
  "Reports financial barriers to food access",
  "Stopped usual walks or planned activity",
  "Difficulty preparing meals or feeding independently",
];

function getEtiologies(domain: DiagnosisDomain) {
  if (domain === "NO") return [];
  if (domain === "NI") return NI_ETIOLOGIES;
  if (domain === "NC") return NC_ETIOLOGIES;
  return NB_ETIOLOGIES;
}

function getSigns(domain: DiagnosisDomain) {
  if (domain === "NO") return [];
  if (domain === "NI") return NI_SIGNS;
  if (domain === "NC") return NC_SIGNS;
  return NB_SIGNS;
}

// ─── Types ────────────────────────────────────────────────────────────────────

type TabKey = "table" | "problem" | "etiology" | "signs" | "pes" | "ai";

interface BuilderState {
  editingId: number | null;
  domain: DiagnosisDomain;
  niPattern: IntakePattern;
  niDetail: string;
  niDirection: "Inadequate" | "Excessive";
  niNutrient: string;
  niNutrientSpecify: keyof typeof NI_MICRONUTRIENTS | "Nutrient / substance (specify)" | "";
  ncProblems: string[];
  nbProblems: string[];
  etiologyChecks: string[];
  etiologyNotes: string;
  signChecks: string[];
  signNotes: string;
  pesOverride: string;
  extraNotes: string;
  problemOverride: string;
  problemOther: string;
  problemOtherChecked: boolean;
  etiologyOther: string;
  etiologyOtherChecked: boolean;
  signOther: string;
  signOtherChecked: boolean;
  pesEdited: boolean;
  aiDraftId?: string;
}

function defaultBuilder(): BuilderState {
  return {
    editingId: null,
    domain: "NI",
    niPattern: "intake", niDetail: "",
    niDirection: "Inadequate",
    niNutrient: "",
    niNutrientSpecify: "",
    ncProblems: [],
    nbProblems: [],
    etiologyChecks: [],
    etiologyNotes: "",
    signChecks: [],
    signNotes: "",
    pesOverride: "",
    extraNotes: "",
    problemOverride: "",
    problemOther: "", problemOtherChecked: false,
    etiologyOther: "", etiologyOtherChecked: false,
    signOther: "", signOtherChecked: false, pesEdited: false,
  };
}

function buildProblemText(b: BuilderState): string {
  return buildDiagnosisProblemText(b);
}

function buildEtiologyText(b: BuilderState): string {
  if (b.domain === "NO") return "";
  return composeDiagnosisComponent(b.etiologyChecks, b.etiologyOther, b.etiologyOtherChecked) || "[Select etiology]";
}

function buildSignsText(b: BuilderState): string {
  if (b.domain === "NO") return "";
  return composeDiagnosisComponent(b.signChecks, b.signOther, b.signOtherChecked) || "[Select signs and symptoms]";
}

function hasIncompleteOther(b: BuilderState): boolean {
  return [
    [b.domain !== "NI" && b.problemOtherChecked, b.problemOther, 75],
    [b.etiologyOtherChecked, b.etiologyOther, 400],
    [b.signOtherChecked, b.signOther, 400],
  ].some(([checked, value, limit]) => checked && (!(value as string).trim() || Array.from(value as string).length > (limit as number)));
}

function buildPes(problem: string, etiology: string, signs: string): string {
  if (problem === NO_DIAGNOSIS && !etiology && !signs) return problem;
  return `${problem} related to ${etiology} as evidenced by ${signs}`;
}

function buildPayload(b: BuilderState): StoreDiagnosisPayload {
  const problem = buildProblemText(b);
  const etiology = buildEtiologyText(b);
  const signs = buildSignsText(b);
  const pesStatement = b.pesOverride.trim() || buildPes(problem, etiology, signs);

  return {
    domain: b.domain,
    problem,
    etiology,
    signs_symptoms: signs,
    // Persist the PES the RND actually sees (manual override wins; otherwise the
    // builder-derived statement). Backend falls back to P-E-S if this is blank.
    pes_statement: pesStatement,
    extra_notes: b.extraNotes || null,
    ai_generated: Boolean(b.aiDraftId),
    ai_draft_id: b.aiDraftId,
    builder_data: b.domain === "NO" ? undefined : {
      problem_override: b.problemOverride || (b.domain === "NI" && (b.niPattern !== "intake" || b.niNutrientSpecify || /infusion|bioactive/i.test(b.niNutrient)) ? problem : ""),
      problem_checks: b.domain === "NC" ? b.ncProblems : b.domain === "NB" ? b.nbProblems : [],
      problem_other: b.domain !== "NI" && b.problemOtherChecked ? b.problemOther.trim() : "",
      etiology_checks: b.etiologyChecks,
      etiology_other: b.etiologyOtherChecked ? b.etiologyOther.trim() : "",
      sign_checks: b.signChecks,
      sign_other: b.signOtherChecked ? b.signOther.trim() : "",
      etiology_notes: b.etiologyNotes, sign_notes: b.signNotes,
      ...(b.domain === "NI" ? { ni_direction: b.niDirection, ni_nutrient: b.niNutrient, ni_pattern: b.niPattern, ni_group: b.niNutrientSpecify, ni_detail: b.niDetail } : {}),
    },
  };
}

function loadBuilderFromDiagnosis(d: Diagnosis): BuilderState {
  const b = defaultBuilder();
  b.editingId = d.id ?? null;
  b.domain = d.domain;
  b.problemOverride = d.problem;
  b.extraNotes = d.extra_notes ?? "";
  const stored = d.builder_data;
  const etiology = splitDiagnosisComponent(d.etiology ?? "", getEtiologies(d.domain));
  const signs = splitDiagnosisComponent(d.signs_symptoms ?? "", getSigns(d.domain));
  const problems = splitDiagnosisComponent(d.problem, d.domain === "NC" ? NC_PROBLEMS : NB_PROBLEMS);
  b.etiologyChecks = stored?.etiology_checks ?? etiology.checks;
  b.etiologyOther = stored?.etiology_other ?? etiology.other;
  b.etiologyOtherChecked = Boolean(b.etiologyOther);
  b.signChecks = stored?.sign_checks ?? signs.checks;
  b.signOther = stored?.sign_other ?? signs.other;
  b.signOtherChecked = Boolean(b.signOther);
  b.etiologyNotes = stored?.etiology_notes ?? "";
  b.signNotes = stored?.sign_notes ?? "";
  if (d.domain === "NI") {
    if (stored?.ni_pattern) {
      b.niPattern = stored.ni_pattern;
      b.niDirection = stored.ni_direction ?? b.niDirection;
      b.niNutrient = stored.ni_nutrient ?? "";
      b.niNutrientSpecify = stored.ni_group ?? "";
      b.niDetail = stored.ni_detail ?? "";
    } else {
    const match = d.problem.match(/^(Inadequate|Excessive)\s+(.+)\s+Intake$/i);
    if (match) {
      b.niDirection = match[1] as "Inadequate" | "Excessive";
      b.niNutrient = stored?.ni_nutrient ?? match[2];
      const group = (["Vitamins", "Minerals"] as const).find(key => NI_MICRONUTRIENTS[key].some(n => n.toLowerCase() === b.niNutrient.toLowerCase()))
        ?? (/^vitamin\s/i.test(b.niNutrient) ? "Vitamins" : undefined);
      if (group) b.niNutrientSpecify = group;
      else if (!NI_NUTRIENTS.includes(b.niNutrient) && !NI_NUTRIENTS.includes(b.niNutrient + " Intake")) b.niNutrientSpecify = "Nutrient / substance (specify)";
      if (NI_NUTRIENTS.includes(b.niNutrient + " Intake")) b.niNutrient += " Intake";
    } else b.problemOverride = d.problem;
    }
  } else {
    const aliases: Record<string, string> = {
      "Overweight / Obesity": "Overweight/obesity",
      "Food and Nutrition Knowledge Deficit": "Food- and nutrition-related knowledge deficit",
      "Harmful Beliefs / Attitudes about Food": "Unsupported beliefs/attitudes about food- or nutrition-related topics (use with caution)",
      "Not Ready for Diet / Lifestyle Change": "Not ready for diet/lifestyle change",
      "Food Insecurity": "Limited access to food or water",
    };
    const options = d.domain === "NC" ? NC_PROBLEMS : NB_PROBLEMS;
    const checks = (stored?.problem_checks ?? (aliases[d.problem] ? [d.problem] : problems.checks)).map(p => aliases[p] ?? options.find(option => option.toLowerCase() === p.toLowerCase()) ?? p);
    if (d.domain === "NC") b.ncProblems = checks;
    else b.nbProblems = checks;
    b.problemOther = stored?.problem_other ?? (aliases[d.problem] ? "" : problems.other);
    b.problemOtherChecked = Boolean(b.problemOther);
  }
  const composed = buildPes(buildProblemText(b), buildEtiologyText(b), buildSignsText(b));
  b.pesOverride = d.pes_statement ?? composed;
  b.pesEdited = Boolean(d.pes_statement && d.pes_statement !== composed);
  return b;
}

// ─── Small UI Helpers ─────────────────────────────────────────────────────────

function DomainBadge({ domain }: { domain: DiagnosisDomain }) {
  const meta = DOMAIN_META[domain];
  return (
    <span className="text-xs font-semibold text-warm-700 whitespace-nowrap">
      {meta.label}
    </span>
  );
}

function Checkbox({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex min-h-11 items-start gap-2 text-xs text-warm-700 cursor-pointer hover:bg-warm-50 p-2.5 rounded-lg transition-colors">
      <input
        type="checkbox"
        checked={checked}
        onChange={e => onChange(e.target.checked)}
        className="mt-0.5 shrink-0 accent-emerald-600"
      />
      <span className="leading-tight">{label}</span>
    </label>
  );
}

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <span className="block text-xs font-bold text-warm-500 uppercase tracking-wider mb-1.5">{children}</span>
  );
}

// ─── Main Component ───────────────────────────────────────────────────────────

export default function NcpDiagnosisPage({
  params,
}: {
  params: Promise<{ patientId: string; ncpId: string }>;
}) {
  const resolvedParams = use(params);
  const { patientId, ncpId } = resolvedParams;

  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [activeTab, setActiveTab] = useState<TabKey>("table");
  const [patient, setPatient] = useState<Patient | null>(null);
  const [diagnoses, setDiagnoses] = useState<Diagnosis[]>([]);
  const [diagnosesMeta, setDiagnosesMeta] = useState<PaginationMeta | null>(null);
  const [diagnosesQuery, setDiagnosesQuery] = useState({ page: 1, quiet: false });
  const diagnosesPage = diagnosesQuery.page;
  const setDiagnosesPage = (page: number, quiet = false) => setDiagnosesQuery({ page, quiet });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [domainFilter, setDomainFilter] = useState<"ALL" | DiagnosisDomain>("ALL");
  const [builder, setBuilder] = useState<BuilderState>(defaultBuilder());
  const [aiSuggestions, setAiSuggestions] = useState<AiSuggestion[]>([]);
  const [aiConcerns, setAiConcerns] = useState<NonNullable<import("@/services/diagnosisService").PesDraftResult["concerns"]>>([]);
  const [aiDraftsPage, setAiDraftsPage] = useState(1);
  const [aiLoading, setAiLoading] = useState(false);
  const [aiMeta, setAiMeta] = useState<PesDraftMeta | null>(null);
  const [aiUnavailable, setAiUnavailable] = useState<string | null>(null);
  const [aiWarning, setAiWarning] = useState<string | null>(null);
  const [aiStatusLoading, setAiStatusLoading] = useState(false);
  useEffect(() => {
    if (!aiWarning) return;
    const timer = setTimeout(() => setAiWarning(null), 5000);
    return () => clearTimeout(timer);
  }, [aiWarning]);
  const aiStatusRequest = useRef(0);
  const dataRequest = useRef(0);

  const refreshAiStatus = useCallback(async () => {
    const request = ++aiStatusRequest.current;
    setAiStatusLoading(true);
    try {
      const result = await fetchPesDraftStatus(ncpId);
      if (request !== aiStatusRequest.current) return;
      setAiSuggestions([...result.data, ...(result.review_drafts ?? [])]); setAiConcerns(result.concerns ?? []); setAiMeta(result.meta); setAiUnavailable(null);
      if (result.meta.can_generate || result.meta.can_retry) setAiWarning(null);
    } catch {
      if (request === aiStatusRequest.current) {
        setAiMeta(null); setAiUnavailable("Could not load AI suggestions. Try again.");
      }
    } finally {
      if (request === aiStatusRequest.current) setAiStatusLoading(false);
    }
  }, [ncpId]);
  const [hasAssessment, setHasAssessment] = useState(false);
  const [loadFailed, setLoadFailed] = useState(false);
  const [assessmentContext, setAssessmentContext] = useState<Assessment | null>(null);
  const [interventionGoal, setInterventionGoal] = useState<string | null>(null);

  const loadData = useCallback(async (quiet = diagnosesQuery.quiet) => {
    if (isPlaceholder) { setLoading(false); return; }
    const request = ++dataRequest.current;
    void refreshAiStatus();
    try {
      if (!quiet) setLoading(true);
        setLoadFailed(false);
      const [p, d, a, intervention] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchDiagnosesPage(ncpId, diagnosesPage),
        fetchAssessment(ncpId),
        fetchIntervention(ncpId),
      ]);
      if (request !== dataRequest.current) return;
      if (quiet && [p, d, a, intervention].some(result => result.status === "rejected")) {
        setError("Could not refresh diagnoses. Your change was saved; reload to get the latest data.");
        return;
      }
        if (p.status === "rejected" || d.status === "rejected" || intervention.status === "rejected"
          || (a.status === "rejected" && a.reason?.status !== 404)) {
          setLoadFailed(true);
          setError("Could not load diagnosis and care context. Try again before editing.");
          return;
        }
      if (p.status === "fulfilled") setPatient(p.value);
      if (d.status === "fulfilled") {
        setDiagnoses(d.value.data);
        setDiagnosesMeta(d.value.meta);
      }
      if (a.status === "fulfilled") {
        setHasAssessment(true);
        setAssessmentContext(a.value);
      } else {
        setHasAssessment(false);
        setAssessmentContext(null);
      }
      if (intervention.status === "fulfilled") {
        setInterventionGoal(intervention.value?.goal_type ?? null);
      }
    } catch {
      // silent
    } finally {
      if (request === dataRequest.current) setLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder, diagnosesPage, diagnosesQuery.quiet, refreshAiStatus]);

  useEffect(() => {
    const requests = { data: dataRequest, ai: aiStatusRequest };
    void loadData();
    return () => { requests.data.current++; requests.ai.current++; };
  }, [loadData]);

  // Auto-update PES override when builder fields change
  useEffect(() => {
    const problem = buildProblemText(builder);
    const etiology = buildEtiologyText(builder);
    const signs = buildSignsText(builder);
    if (
      !builder.pesEdited && !hasIncompleteOther(builder) && !problem.includes("[") &&
      !etiology.includes("[") &&
      !signs.includes("[")
    ) {
      setBuilder(prev => ({ ...prev, pesOverride: buildPes(problem, etiology, signs) }));
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    builder.domain, builder.niDirection, builder.niNutrient, builder.niPattern, builder.niDetail, builder.niNutrientSpecify,
    builder.ncProblems, builder.nbProblems, builder.problemOverride,
    builder.etiologyChecks, builder.etiologyOther, builder.etiologyOtherChecked,
    builder.signChecks, builder.signOther, builder.signOtherChecked,
    builder.problemOther, builder.problemOtherChecked, builder.pesEdited,
  ]);


  useEffect(() => {
    if (isPlaceholder) return;
    const refresh = () => { void refreshAiStatus().catch(() => setAiUnavailable("Could not load AI suggestions. Try again.")); };
    const reset = aiMeta?.budget?.used ? aiMeta.budget.resets_at : null;
    const delay = reset ? Math.max(1000, new Date(reset).getTime() - Date.now() + 1000) : null;
    const timer = delay !== null ? setTimeout(refresh, Math.min(delay, 2147483647)) : undefined;
    window.addEventListener("focus", refresh);
    return () => { if (timer !== undefined) clearTimeout(timer); window.removeEventListener("focus", refresh); };
  }, [aiMeta?.budget?.resets_at, aiMeta?.budget?.used, isPlaceholder, refreshAiStatus]);

  const updateBuilder = (patch: Partial<BuilderState>) => setBuilder(prev => ({ ...prev, ...patch }));

  const noDiagnosisWarning = "Delete the saved 'No nutrition diagnosis at this time' entry before adding or generating a diagnosis.";
  const hasNoDiagnosis = diagnoses.some(d => d.domain === "NO") || Boolean(aiMeta?.blocked_by_no_diagnosis);

  const startNew = () => {
    if (hasNoDiagnosis) { setError(noDiagnosisWarning); return; }
    setBuilder(defaultBuilder());
    setActiveTab("problem");
    setError(null);
  };

  const startEdit = (d: Diagnosis) => {
    setBuilder(loadBuilderFromDiagnosis(d));
    setActiveTab("problem");
    setError(null);
  };

  const handleDelete = async (d: Diagnosis) => {
    setAiWarning(null);
    if (!d.id) return;
    if (!confirm(`Delete this diagnosis?\n"${d.pes_statement ?? d.problem}"`)) return;
    try {
      setDeleting(d.id);
      await deleteDiagnosis(ncpId, d.id);
      if (d.domain === "NO") setAiMeta(prev => prev ? { ...prev, blocked_by_no_diagnosis: false } : prev);
      if (diagnoses.length === 1 && diagnosesPage > 1) {
        setDiagnosesPage(diagnosesPage - 1, true);
      } else {
        await loadData(true);
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to delete.");
    } finally {
      setDeleting(null);
    }
  };

  const handleSave = async () => {
    if (hasNoDiagnosis && builder.domain !== "NO") { setError(noDiagnosisWarning); return; }
    const payload = buildPayload(builder);
    const problem = buildProblemText(builder);
    const etiology = buildEtiologyText(builder);
    const signs = buildSignsText(builder);

    if (hasIncompleteOther(builder) || problem.includes("[") || etiology.includes("[") || signs.includes("[") || payload.pes_statement!.length > 500 || [builder.extraNotes, builder.etiologyNotes, builder.signNotes].some(note => Array.from(note).length > 125)) {
      setError("Complete each component and any checked Other field within its character limit before saving.");
      setActiveTab("pes");
      return;
    }

    try {
      setSaving(true);
      setError(null);
      if (builder.editingId) {
        await updateDiagnosis(ncpId, builder.editingId, payload);
        setSuccess("Diagnosis updated.");
      } else {
        await storeDiagnosis(ncpId, payload);
        setSuccess("Diagnosis saved.");
      }
      setBuilder(defaultBuilder());
      setActiveTab("table");
      if (diagnosesPage === 1) {
        await loadData(true);
      } else {
        setDiagnosesPage(1, true);
      }
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  const handleAiSuggest = async () => {
    if (hasNoDiagnosis) { setError(noDiagnosisWarning); return; }
    try {
      setAiLoading(true);
      setError(null);
      setAiUnavailable(null);
      setAiWarning(null);
      const retry = Boolean(aiMeta?.can_retry && !aiMeta.can_generate);
      const result = await aiSuggestDiagnoses(ncpId, retry);
      setAiSuggestions([...result.data, ...(result.review_drafts ?? [])]);
      setAiConcerns(result.concerns ?? []);
      setAiDraftsPage(1);
      setAiMeta(result.meta);
      if (result.data.length === 0 && !(result.review_drafts?.length) && !(result.concerns?.length)) {
        setAiWarning(result.meta.can_retry
          ? 'Could not generate AI suggestions. Try again or add a diagnosis manually.'
          : 'No additional nutrition diagnosis found.');
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "External AI drafts are unavailable.";
      if (message.includes("External AI drafts are unavailable")) {
        setAiUnavailable(message);
      } else {
        setError(message);
      }
    } finally {
      setAiLoading(false);
    }
  };

  const handleAiAccept = async (draftId: string) => {
    setAiWarning(null);
    try {
      setAiLoading(true);
      await aiApproveDiagnosis(ncpId, { draft_id: draftId });
      if (diagnosesPage === 1) {
        await loadData(true);
      } else {
        setDiagnosesPage(1, true);
      }
      setAiSuggestions(prev => prev.filter(draft => draft.draft_id !== draftId));
      setAiDraftsPage(1);
      setSuccess("PES draft accepted and saved.");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to accept diagnosis.");
    } finally {
      await refreshAiStatus().catch(() => undefined);
      setAiLoading(false);
    }
  };

  const handleAiDismiss = async (s: AiSuggestion) => {
    setAiWarning(null);
    try {
      setAiLoading(true);
      setError(null);
      const result = await dismissPesSuggestion(ncpId, s.draft_id);
      setAiSuggestions([...result.data, ...(result.review_drafts ?? [])]);
      setAiConcerns(result.concerns ?? []);
      setAiDraftsPage(1);
      setAiMeta(result.meta);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to dismiss PES draft.");
    } finally {
      setAiLoading(false);
    }
  };

  const handleAiEdit = (s: AiSuggestion) => {
    const b = loadBuilderFromDiagnosis({
      domain: s.domain, problem: s.label, etiology: s.etiology, signs_symptoms: s.signs,
      extra_notes: s.extra_notes, builder_data: s.builder_data,
    });
    b.aiDraftId = s.draft_id;
    setBuilder(b);
    setActiveTab("problem");
  };

  // ─── Placeholder / Loading ────────────────────────────────────────────────

  if (isPlaceholder) return <NcpNoPatientSelected step="Nutrition Diagnosis" />;

  if (loadFailed && !loading) {
    return <div className="p-6 space-y-3"><p role="alert" className="text-sm text-red-700">{error}</p><button type="button" className="min-h-11 underline" onClick={() => { void loadData(false); }}>Try again</button></div>;
  }

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Diagnosis" />
        <div role="status" aria-busy="true" aria-label="Loading diagnoses…" className="space-y-4">{[1, 2, 3].map(i => <div key={i} className="h-16 bg-warm-100 rounded-xl motion-safe:animate-pulse" />)}</div>
      </div>
    );
  }

  if (!hasAssessment) {
    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Diagnosis" />
        <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
            <Lock className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-800 mt-4 uppercase tracking-wider">Assessment Required</h3>
          <p className="text-sm text-warm-500 mt-2 leading-relaxed">
            Save the nutrition assessment before starting diagnosis. You can return here after Assessment is saved.
          </p>
          <Link href={`/ncp/${patientId}/assessment/${ncpId}`} className="inline-flex mt-6 px-4 py-2.5 bg-forest-900 hover:bg-forest-900 text-white text-sm font-bold uppercase tracking-wider rounded-lg transition-colors">
            Go to Assessment
          </Link>
        </div>
      </div>
    );
  }

  const filteredDiagnoses = domainFilter === "ALL" ? diagnoses : diagnoses.filter(d => d.domain === domainFilter);
  const TABS: { key: TabKey; label: string }[] = [
    { key: "table", label: "Diagnosis Table" },
    { key: "problem", label: "P — Problem" },
    ...(builder.domain === "NO" ? [] : [
      { key: "etiology" as const, label: "E — Etiology" },
      { key: "signs" as const, label: "S — Signs & Symptoms" },
      { key: "pes" as const, label: "PES Statement" },
    ]),
    { key: "ai", label: "AI Review" },
  ];

  // ─── Tab Renderers ────────────────────────────────────────────────────────

  const renderTableTab = () => (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <ButtonFilterGroup
          options={[
            { value: "ALL", label: "All" },
            { value: "NI", label: "NI" },
            { value: "NC", label: "NC" },
            { value: "NB", label: "NB" },
            { value: "NO", label: "NO" },
          ]}
          value={domainFilter}
          onChange={(val) => setDomainFilter(val as "ALL" | DiagnosisDomain)}
          label="Filter:"
        />
        <button
          type="button"
          onClick={startNew}
          disabled={hasNoDiagnosis}
          className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer shadow-sm"
        >
          <Plus className="h-3.5 w-3.5" />
          Add New Diagnosis
        </button>
      </div>

      {filteredDiagnoses.length === 0 ? (
        <div className="rounded-none border-0 bg-transparent p-5 text-center shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-10 sm:shadow-sm">
          <Stethoscope className="h-7 w-7 text-warm-300 mx-auto mb-3" />
          <p className="text-sm font-bold text-warm-600 uppercase tracking-wider">No diagnoses recorded</p>
          <p className="text-xs text-warm-400 mt-1">
            {domainFilter !== "ALL" ? `No ${DOMAIN_META[domainFilter].label} diagnoses yet.` : `Click "Add New Diagnosis" to begin the PES builder.`}
          </p>
        </div>
      ) : (
        <div className="min-w-0 overflow-hidden rounded-none border-0 bg-transparent shadow-none sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:shadow-sm">
          <div className="overflow-x-auto">
          <table className="w-full text-sm min-w-[480px]">
            <thead>
              <tr className="border-b border-warm-100 bg-warm-50/80">
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider w-8">#</th>
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Domain</th>
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">PES Statement</th>
                <th className="text-right px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredDiagnoses.map((d, i) => (
                <tr key={d.id} className="border-b border-warm-100 hover:bg-warm-50/60 transition-colors">
                  <td className="px-4 py-3 text-warm-400 font-mono font-bold">{i + 1}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-col items-start gap-1">
                      <DomainBadge domain={d.domain} />
                      {d.ai_generated && (
                        <span className="text-xs font-semibold text-orange-700" title="AI-assisted diagnosis">AI assisted</span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-warm-700 max-w-[640px]">
                    <span className="line-clamp-3 italic text-xs" title={d.pes_statement}>{d.pes_statement}</span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                      {d.domain !== "NO" && <button
                        type="button"
                        onClick={() => startEdit(d)}
                        className="p-1.5 text-warm-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors cursor-pointer"
                        title="Edit"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </button>}
                      <button
                        type="button"
                        onClick={() => handleDelete(d)}
                        disabled={deleting === d.id}
                        className="p-1.5 text-warm-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
                        title="Delete"
                      >
                        {deleting === d.id ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <Trash2 className="h-3.5 w-3.5" />}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        </div>
      )}
      <Pagination meta={diagnosesMeta} page={diagnosesPage} onPageChange={setDiagnosesPage} />
    </div>
  );

  const micronutrientOptions = builder.niNutrientSpecify === "Vitamins" || builder.niNutrientSpecify === "Minerals"
    ? NI_MICRONUTRIENTS[builder.niNutrientSpecify] : null;
  const micronutrientLabel = builder.niNutrientSpecify === "Vitamins" ? "Vitamin" : "Mineral";

  const intakeSubjects = builder.niPattern === "composition"
    ? ["enteral nutrition composition or modality", "parenteral nutrition composition or modality", "intake of types of fats", "intake of types of proteins or amino acids", "intake of types of carbohydrate"]
    : builder.niPattern === "other"
      ? ["Increased energy expenditure", "Limited food acceptance", "Excessive alcohol intake", "Malnutrition", "Inconsistent carbohydrate intake", "Imbalance of nutrients"]
      : builder.niPattern === "predicted" ? ["Energy", "Nutrient / substance (specify)"]
      : builder.niPattern === "needs" ? ["Energy", "Protein", "Fat", "Carbohydrates", "Fluid", "Fiber", "Vitamins", "Minerals", "Nutrient / substance (specify)"] : NI_NUTRIENTS;

  const renderProblemTab = () => (
    <div className="space-y-6 max-w-2xl">
      <div>
        <SectionLabel>Step 1: Select Diagnostic Domain</SectionLabel>
        <div className="flex gap-4 flex-wrap border-b border-warm-200">
          {(["NI", "NC", "NB", "NO"] as const).map(d => (
            <button
              key={d}
              type="button"
              disabled={hasNoDiagnosis || (d === "NO" && (diagnosesMeta?.total ?? 0) > 0)}
              onClick={() => setBuilder({ ...defaultBuilder(), domain: d, editingId: builder.editingId, aiDraftId: d === "NO" ? undefined : builder.aiDraftId })}
              className={`px-1 py-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer ${
                builder.domain === d
                  ? "border-emerald-700 text-emerald-800"
                  : "border-transparent text-warm-600 hover:text-warm-900"
              }`}
            >
              {DOMAIN_META[d].label}
            </button>
          ))}
        </div>
      </div>

      {builder.domain === "NO" && <p className="rounded-xl border border-warm-200 bg-warm-50 p-4 text-sm font-semibold">{NO_DIAGNOSIS}</p>}

      {builder.domain === "NI" && (
        <div className="space-y-4">
          <label className="block text-xs font-semibold text-warm-600">
            Problem family
            <select aria-label="Intake problem family" value={builder.niPattern} onChange={e => updateBuilder({ niPattern: e.target.value as IntakePattern, niNutrient: "", niNutrientSpecify: "", niDetail: "", problemOverride: "" })} className="mt-2 min-h-11 w-full rounded-lg border border-warm-200 bg-white px-3 py-2 text-sm">
              <option value="intake">Intake amount</option>
              <option value="predicted">Predicted intake</option>
              <option value="needs">Nutrient needs</option>
              <option value="composition">Composition or modality</option>
              <option value="other">Other intake problems</option>
            </select>
          </label>
          {!["composition", "other"].includes(builder.niPattern) && <div>
            <SectionLabel>Step 2: Select Direction</SectionLabel>
            <div className="flex gap-3">
              {(["Inadequate", "Excessive"] as const).map(dir => (
                <button
                  key={dir}
                  type="button"
                  disabled={dir === "Excessive" && builder.niNutrient === "Protein-energy"}
                  onClick={() => updateBuilder({ niDirection: dir, problemOverride: "" })}
                  className={`px-4 py-2 text-sm font-bold rounded-xl border-2 transition-all cursor-pointer ${
                    builder.niDirection === dir
                      ? "bg-sky-50 text-sky-700 border-sky-400"
                      : "bg-white text-warm-500 border-warm-200 hover:border-zinc-400"
                  }`}
                >
                  {builder.niPattern === "needs" ? (dir === "Inadequate" ? "Increased" : "Decreased") : (builder.niPattern === "predicted" || builder.niNutrient === "Bioactive substance") && dir === "Inadequate" ? "Suboptimal" : dir}
                </button>
              ))}
            </div>
          </div>}
          <div>
            <SectionLabel>{builder.niPattern === "other" ? "Select problem" : builder.niPattern === "composition" ? "Select composition or modality" : "Step 3: Select Nutrient / Substance"}</SectionLabel>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
              {intakeSubjects.map(n => (
                <button
                  key={n}
                  type="button"
                  aria-pressed={builder.niNutrient === n || builder.niNutrientSpecify === n}
                  onClick={() => {
                    const group = n === "Vitamins" || n === "Minerals" || n === "Nutrient / substance (specify)" ? n : "";
                    updateBuilder({ niNutrient: group ? "" : n, niNutrientSpecify: group, niDetail: "", problemOverride: "", ...(n === "Protein-energy" ? { niDirection: "Inadequate" } : {}) });
                  }}
                  className={`px-3 py-2 text-sm font-semibold rounded-lg border transition-all cursor-pointer text-left ${
                    (builder.niNutrient === n || builder.niNutrientSpecify === n)
                      ? "bg-sky-50 text-sky-800 border-sky-300 font-bold"
                      : "bg-white text-warm-600 border-warm-200 hover:border-warm-300"
                  }`}
                >
                  {n}
                </button>
              ))}
            </div>
            {((builder.niPattern === "composition" && builder.niNutrient.startsWith("intake of types")) || builder.niNutrient === "Bioactive substance") && <label className="mt-2 block text-xs font-semibold text-warm-600">Specify type or substance<input aria-label="Specify type or substance" value={builder.niDetail} maxLength={50} onChange={e => updateBuilder({ niDetail: e.target.value, problemOverride: "" })} className="mt-2 min-h-11 w-full rounded-lg border border-warm-200 px-3 py-2 text-sm" /></label>}
            {micronutrientOptions ? (
              <label className="mt-2 block text-xs font-semibold text-warm-600">
                Select {micronutrientLabel.toLowerCase()}
                <select
                  aria-label={micronutrientLabel}
                  value={builder.niNutrient}
                  onChange={e => updateBuilder({ niNutrient: e.target.value, problemOverride: "" })}
                  className="mt-2 w-full min-w-0 px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400/20 focus:border-sky-400"
                >
                  <option value="" disabled>Select {micronutrientLabel.toLowerCase()}</option>
                  {builder.niNutrient && !micronutrientOptions.includes(builder.niNutrient) && <option value={builder.niNutrient}>{builder.niNutrient}</option>}
                  {micronutrientOptions.map(nutrient => <option key={nutrient} value={nutrient}>{nutrient}</option>)}
                </select>
              </label>
            ) : builder.niNutrientSpecify && (
              <label className="mt-2 block text-xs font-semibold text-warm-600">
                Specify nutrient or substance
              <input
                type="text"
                aria-label={builder.niNutrientSpecify}
                maxLength={50}
                value={builder.niNutrient}
                onChange={e => updateBuilder({ niNutrient: e.target.value, problemOverride: "" })}
                className="mt-2 w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400/20 focus:border-sky-400"
              />
              </label>
            )}
          </div>
        </div>
      )}

      {builder.domain === "NC" && (
        <div>
          <SectionLabel>Step 2: Select Clinical Problem(s)</SectionLabel>
          <div className="space-y-2">
            {Object.entries(NC_PROBLEM_GROUPS).map(([group, problems]) => <details key={group} open={!builder.editingId || problems.some(p => builder.ncProblems.includes(p))} className="rounded-xl border border-warm-200 bg-warm-50/60 p-3">
              <summary className="min-h-11 cursor-pointer text-sm font-semibold text-warm-700">{group}</summary>
              {problems.map(p => <Checkbox key={p} checked={builder.ncProblems.includes(p)}
                onChange={checked => updateBuilder({ problemOverride: "", ncProblems: checked ? [...builder.ncProblems, p] : builder.ncProblems.filter(x => x !== p) })} label={p} />)}
            </details>)}
            {builder.ncProblems.filter(p => !NC_PROBLEMS.includes(p)).map(p => <Checkbox key={p} checked onChange={() => updateBuilder({ problemOverride: "", ncProblems: builder.ncProblems.filter(x => x !== p) })} label={p} />)}
            <DiagnosisOtherField id="clinical-problem" label="Other clinical problem" checked={builder.problemOtherChecked} value={builder.problemOther} onToggle={checked => updateBuilder({ problemOtherChecked: checked, problemOverride: "" })} onChange={value => updateBuilder({ problemOther: value, problemOverride: "" })} />
          </div>
        </div>
      )}

      {builder.domain === "NB" && (
        <div>
          <SectionLabel>Step 2: Select Behavioral-Environmental Problem(s)</SectionLabel>
          <div className="space-y-2">
            {Object.entries(NB_PROBLEM_GROUPS).map(([group, problems]) => <details key={group} open={!builder.editingId || problems.some(p => builder.nbProblems.includes(p))} className="rounded-xl border border-warm-200 bg-warm-50/60 p-3">
              <summary className="min-h-11 cursor-pointer text-sm font-semibold text-warm-700">{group}</summary>
              {problems.map(p => <Checkbox key={p} checked={builder.nbProblems.includes(p)}
                onChange={checked => updateBuilder({ problemOverride: "", nbProblems: checked ? [...builder.nbProblems, p] : builder.nbProblems.filter(x => x !== p) })} label={p} />)}
            </details>)}
            {builder.nbProblems.filter(p => !NB_PROBLEMS.includes(p)).map(p => <Checkbox key={p} checked onChange={() => updateBuilder({ problemOverride: "", nbProblems: builder.nbProblems.filter(x => x !== p) })} label={p} />)}
            <DiagnosisOtherField id="behavioral-problem" label="Other behavioral problem" checked={builder.problemOtherChecked} value={builder.problemOther} onToggle={checked => updateBuilder({ problemOtherChecked: checked, problemOverride: "" })} onChange={value => updateBuilder({ problemOther: value, problemOverride: "" })} />
          </div>
        </div>
      )}

      <div>
        <SectionLabel>Extra Notes (optional)</SectionLabel>
        <CharacterCountTextarea
          value={builder.extraNotes}
          aria-label="Extra Notes (optional)"
          onChange={value => updateBuilder({ extraNotes: value })}
          rows={2}
          maxLength={125}
          className="w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 placeholder:text-warm-400 resize-none"
        />
      </div>

      <div className="p-4 bg-warm-50 rounded-xl border border-warm-200">
        <span className="text-xs font-bold text-warm-500 uppercase tracking-wider block mb-1">Problem preview (P)</span>
        <span className="text-sm font-semibold text-warm-800">{buildProblemText(builder)}</span>
      </div>

      <div className="flex justify-end">
        <button
          type="button"
          onClick={builder.domain === "NO" ? handleSave : () => setActiveTab("etiology")}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
        >
          {builder.domain === "NO" ? "Save Diagnosis" : <>Next: Etiology <ChevronRight className="h-3.5 w-3.5" /></>}
        </button>
      </div>
    </div>
  );

  const renderEtiologyTab = () => (
    <div className="space-y-5 max-w-2xl">
      <div>
        <SectionLabel>Select Etiology Factors (Related to)</SectionLabel>
        <p className="mb-3 text-sm text-warm-600">Explain why the problem occurs using the specific cause or barrier, such as pain limiting walking.</p>
        <InfoHint label="Etiology guidance" title="Find the root cause">
          <p>Consider beliefs/attitudes, culture, knowledge, physical function, physiology/metabolism, psychology, social/personal factors, treatment, access and behavior; name the actual cause, not its category.</p>
        </InfoHint>
        <div className="space-y-1 bg-warm-50/60 rounded-xl p-4 border border-warm-200">
          {getEtiologies(builder.domain).map(e => (
            <Checkbox
              key={e}
              checked={builder.etiologyChecks.includes(e)}
              onChange={checked => updateBuilder({
                etiologyChecks: checked
                  ? [...builder.etiologyChecks, e]
                  : builder.etiologyChecks.filter(x => x !== e)
              })}
              label={e}
            />
          ))}

          <DiagnosisOtherField id="etiology-other" label="Other etiology" maxLength={400} checked={builder.etiologyOtherChecked} value={builder.etiologyOther} onToggle={checked => updateBuilder({ etiologyOtherChecked: checked })} onChange={value => updateBuilder({ etiologyOther: value })} />
        </div>
      </div>
      <div>
        <SectionLabel>Additional Etiology Notes</SectionLabel>
        <CharacterCountTextarea
          value={builder.etiologyNotes}
          aria-label="Additional Etiology Notes"
          onChange={value => updateBuilder({ etiologyNotes: value })}
          rows={3}
          maxLength={125}
          className="w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 placeholder:text-warm-400 resize-none"
        />
      </div>
      <div className="p-4 bg-warm-50 rounded-xl border border-warm-200">
        <span className="text-xs font-bold text-warm-500 uppercase tracking-wider block mb-1">Etiology preview (E)</span>
        <span className="text-sm font-semibold text-warm-800">{buildEtiologyText(builder)}</span>
      </div>
      <div className="flex justify-between">
        <button type="button" onClick={() => setActiveTab("problem")} className="px-4 py-2 text-xs font-bold text-warm-600 bg-white border border-warm-200 rounded-lg hover:bg-warm-50 transition-colors cursor-pointer">
          ← Back
        </button>
        <button type="button" onClick={() => setActiveTab("signs")} className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
          Next: Signs & Symptoms <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );

  const renderSignsTab = () => (
    <div className="space-y-5 max-w-2xl">
      <div>
        <SectionLabel>Select Signs & Symptoms (As Evidenced By)</SectionLabel>
        <p className="mb-3 text-sm text-warm-600">Show specific findings that demonstrate the problem and can be reassessed, including recorded amounts, frequency or duration.</p>
        <InfoHint label="Signs and symptoms guidance" title="Show the evidence">
          <p>Use relevant food/activity history, body measurements, labs/tests, physical findings or client history; for inactivity, describe stopped usual walks rather than repeating “physical inactivity.”</p>
        </InfoHint>
        <div className="space-y-1 bg-warm-50/60 rounded-xl p-4 border border-warm-200">
          {Array.from(new Set([...getSigns(builder.domain), ...builder.signChecks])).map(s => (
            <Checkbox
              key={s}
              checked={builder.signChecks.includes(s)}
              onChange={checked => updateBuilder({
                signChecks: checked
                  ? [...builder.signChecks, s]
                  : builder.signChecks.filter(x => x !== s)
              })}
              label={s}
            />
          ))}

          <DiagnosisOtherField id="sign-other" label="Other signs and symptoms" maxLength={400} checked={builder.signOtherChecked} value={builder.signOther} onToggle={checked => updateBuilder({ signOtherChecked: checked })} onChange={value => updateBuilder({ signOther: value })} />
        </div>
      </div>
      <div>
        <SectionLabel>Additional Signs & Symptoms Notes</SectionLabel>
        <CharacterCountTextarea
          value={builder.signNotes}
          aria-label="Additional Signs and Symptoms Notes"
          onChange={value => updateBuilder({ signNotes: value })}
          rows={3}
          maxLength={125}
          className="w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 placeholder:text-warm-400 resize-none"
        />
      </div>
      <div className="p-4 bg-warm-50 rounded-xl border border-warm-200">
        <span className="text-xs font-bold text-warm-500 uppercase tracking-wider block mb-1">Signs & Symptoms preview (S)</span>
        <span className="text-sm font-semibold text-warm-800">{buildSignsText(builder)}</span>
      </div>
      <div className="flex justify-between">
        <button type="button" onClick={() => setActiveTab("etiology")} className="px-4 py-2 text-xs font-bold text-warm-600 bg-white border border-warm-200 rounded-lg hover:bg-warm-50 transition-colors cursor-pointer">
          ← Back
        </button>
        <button type="button" onClick={() => setActiveTab("pes")} className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
          Next: PES Statement <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );

  const renderPesTab = () => {
    const problem = buildProblemText(builder);
    const etiology = buildEtiologyText(builder);
    const signs = buildSignsText(builder);
    const isComplete = !hasIncompleteOther(builder) && builder.pesOverride.length <= 500 && !problem.includes("[") && !etiology.includes("[") && !signs.includes("[");

    return (
      <div className="space-y-5 max-w-2xl">
        <div className="grid grid-cols-1 gap-4">
          <div className="p-4 bg-warm-50 border border-warm-200 rounded-xl space-y-3">
            <div>
              <span className="text-xs font-bold text-sky-600 uppercase tracking-wider block mb-0.5">Problem (P)</span>
              <span className="text-sm font-semibold text-warm-800">{problem}</span>
            </div>
            <div>
              <span className="text-xs font-bold text-sky-600 uppercase tracking-wider block mb-0.5">Etiology (E)</span>
              <span className="text-sm font-semibold text-warm-800">{etiology}</span>
            </div>
            <div>
              <span className="text-xs font-bold text-amber-600 uppercase tracking-wider block mb-0.5">Signs & Symptoms (S)</span>
              <span className="text-sm font-semibold text-warm-800">{signs}</span>
            </div>
          </div>

          <div>
            <SectionLabel>PES Statement (Auto-Generated — Editable)</SectionLabel>
            <CharacterCountTextarea
              value={builder.pesOverride}
              aria-label="PES statement"
              onChange={value => updateBuilder({ pesOverride: value, pesEdited: true })}
              rows={4}
              maxLength={500}
              className="w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-medium text-warm-900 placeholder:text-warm-400 resize-none"
            />
            <p className="text-xs text-warm-400 mt-1">The statement above is auto-generated from the builder. Edit manually if needed before saving.</p>
          </div>

          {builder.pesEdited && <button type="button" onClick={() => updateBuilder({ pesEdited: false })} className="min-h-11 text-xs font-semibold text-emerald-700 underline">Use builder statement</button>}

          {!isComplete && (
            <div className="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700 font-semibold">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
              Please complete Problem, Etiology, and Signs & Symptoms first.
            </div>
          )}
        </div>

        <div className="flex justify-between">
          <button type="button" onClick={() => setActiveTab("signs")} className="px-4 py-2 text-xs font-bold text-warm-600 bg-white border border-warm-200 rounded-lg hover:bg-warm-50 transition-colors cursor-pointer">
            ← Back
          </button>
          <button
            type="button"
            onClick={handleSave}
            disabled={saving || !isComplete}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-warm-300 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed shadow-sm"
          >
            {saving ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <CheckCircle2 className="h-3.5 w-3.5" />}
            {saving ? "Saving..." : builder.editingId ? "Update Diagnosis" : "Save Diagnosis"}
          </button>
        </div>
      </div>
    );
  };

  const aiSuggestionOutcome = !hasNoDiagnosis && hasAssessment && aiMeta && !aiUnavailable && aiMeta.budget.remaining > 0
    && !aiMeta.can_generate && !aiMeta.can_retry && aiSuggestions.length === 0
    ? "No additional nutrition diagnosis found."
    : null;
  const aiSuggestionReason = aiSuggestionOutcome || aiMeta?.can_retry
    ? null : aiMeta?.disabled_reason;

  const aiTab = (
    <div className="space-y-5">
      {aiStatusLoading && <SectionLoading label="Loading AI status…" />}
      <div className="sm:rounded-2xl sm:border sm:border-warm-200 sm:bg-white sm:p-5">
        <div className="mb-2 flex items-center gap-1 text-sm font-bold uppercase tracking-wider text-emerald-600">
          <span>AI Suggestions</span>
          <InfoHint label="How PES drafts work" title="How PES drafts work">
            Reviews saved findings and returns up to five supported nutrition diagnoses. Relevant evidence changes or deletion reopen review. Referral diagnoses provide context. Review every draft before saving.
          </InfoHint>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => { void handleAiSuggest(); }}
            disabled={aiLoading || aiStatusLoading || hasNoDiagnosis || !hasAssessment || !aiMeta || aiMeta.budget.remaining === 0 || Boolean(aiUnavailable)}
            aria-describedby="ai-generation-state"
            title={aiSuggestionReason ?? aiUnavailable ?? undefined}
            className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-extrabold uppercase tracking-wider text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-warm-300"
          >
            {aiLoading && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
            {aiLoading ? "Generating suggestions..." : "Generate AI Suggestions"}
          </button>
          {(aiSuggestionReason || aiUnavailable) && <InfoHint label="AI suggestion status" title="AI suggestion status">{aiSuggestionReason ?? aiUnavailable}</InfoHint>}
          {aiUnavailable && <button type="button" onClick={() => { void refreshAiStatus().catch(() => setAiUnavailable("Could not load AI suggestions. Try again.")); }} className="min-h-11 text-xs font-semibold text-emerald-700 underline">Try again</button>}
        </div>
        <p id="ai-generation-state" role="status" className="mt-2 text-xs text-warm-600">
          {aiMeta?.budget && <span>{aiMeta.budget.used}/{aiMeta.budget.limit} diagnosis requests used in this 10-minute window. </span>}
          {aiUnavailable ?? aiSuggestionReason}
        </p>
        {!hasAssessment && (
          <p className="text-xs font-semibold text-amber-700">Save the Assessment before requesting PES drafts.</p>
        )}
      </div>

      {(aiWarning || aiSuggestionOutcome) && aiConcerns.length === 0 && (
        <div role={aiWarning ? 'alert' : undefined} className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          <p>{aiWarning ?? aiSuggestionOutcome}</p>
        </div>
      )}

      {[false, true].map(review => {
        const drafts = aiSuggestions.filter(draft => Boolean(draft.review_required) === review);
        if (drafts.length === 0) return null;
        const { items: pagedAiDrafts, meta: aiDraftsMeta } = paginateAiDrafts(drafts, review ? 1 : aiDraftsPage);
        const visibleAiDrafts = review ? drafts : pagedAiDrafts;
        return (
        <section key={String(review)} aria-label={review ? 'May need more thorough review' : 'No issues detected'} className="space-y-3">
          <div className="flex items-start gap-2">
            <h4 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider">{review ? 'May need more thorough review' : 'No issues detected'} ({drafts.length} pending)</h4>
            <InfoHint label={review ? 'About suggestions needing review' : 'About PES drafts'} title="Review before saving">
              {review ? 'Some details may need a closer look. Review the notes alongside the assessment and use your clinical judgment before saving.'
                : 'No issues were found during the initial checks. Review the draft for clinical accuracy before saving.'}
            </InfoHint>
          </div>
          {visibleAiDrafts.map(s => {
            const pes = `${s.label} related to ${s.etiology} as evidenced by ${s.signs}`;
            return (
              <div key={s.draft_id} className="min-w-0 space-y-3 border-b border-warm-200 pb-5 last:border-b-0 sm:rounded-2xl sm:border sm:bg-white sm:p-5 sm:shadow-sm">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <DomainBadge domain={s.domain} />
                  <span className="text-xs font-bold text-orange-600 uppercase tracking-wider">AI draft for RND review</span>
                </div>
                <div className="space-y-2 text-sm">
                  {s.review_reasons?.map(reason => <p key={reason} className="text-sm text-amber-800">{reason}</p>)}
                </div>
                <div className="min-w-0 sm:rounded-lg sm:border sm:border-warm-100 sm:bg-warm-50 sm:p-3">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block mb-1">PES Statement</span>
                  <p className="line-clamp-3 text-sm font-medium text-warm-800 italic leading-relaxed" title={pes}>{pes}</p>
                </div>
                <div className="space-y-3 text-xs text-warm-600 sm:rounded-lg sm:border sm:border-warm-100 sm:bg-warm-50 sm:p-3">
                  {s.evidence_by_component ? ([['problem', 'P'], ['etiology', 'E'], ['signs', 'S']] as const).map(([role, label]) => (
                    <div key={role}>
                      <span className="mb-1 block font-bold uppercase tracking-wider text-warm-500">{label} evidence</span>
                      <ul className="list-disc space-y-1 pl-4">
                        {s.evidence_by_component?.[role].map(item => <li key={item.key}>{item.text.split(': ', 1)[0]}</li>)}
                      </ul>
                    </div>
                  )) : (
                  <div>
                    <span className="mb-1 block font-bold uppercase tracking-wider text-warm-500">Evidence used</span>
                    <ul className="list-disc space-y-1 pl-4">
                      {s.evidence_used.map(item => <li key={item}>{item}</li>)}
                    </ul>
                  </div>
                  )}
                </div>
                {s.extra_notes && <p className="text-xs text-warm-600"><span className="font-semibold">Extra Notes: </span>{s.extra_notes}</p>}
                {s.builder_data.etiology_notes && <p className="text-xs text-warm-600"><span className="font-semibold">Additional Etiology Notes: </span>{s.builder_data.etiology_notes}</p>}
                {s.builder_data.sign_notes && <p className="text-xs text-warm-600"><span className="font-semibold">Additional Signs and Symptoms Notes: </span>{s.builder_data.sign_notes}</p>}
                <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-warm-100">
                  {!review && <button
                    type="button"
                    onClick={() => handleAiAccept(s.draft_id)}
                    disabled={aiLoading || saving}
                    className="inline-flex min-h-11 items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <CheckCheck className="h-3 w-3" /> Accept
                  </button>}
                  <button
                    type="button"
                    onClick={() => handleAiEdit(s)}
                    disabled={aiLoading || saving}
                    className="inline-flex min-h-11 items-center gap-1.5 px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold uppercase tracking-wider rounded-lg border border-sky-200 transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <Pencil className="h-3 w-3" /> {review ? 'Review and edit' : 'Edit'}
                  </button>
                  <button
                    type="button"
                    onClick={() => handleAiDismiss(s)}
                    disabled={aiLoading}
                    className="inline-flex min-h-11 items-center gap-1.5 px-3 py-1.5 bg-warm-100 hover:bg-warm-200 text-warm-600 text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <X className="h-3 w-3" /> Dismiss
                  </button>
                </div>
              </div>
            );
          })}
          {!review && <Pagination meta={aiDraftsMeta} page={aiDraftsMeta.current_page} onPageChange={setAiDraftsPage} />}
        </section>
        );
      })}

      {aiConcerns.length > 0 && (
        <details className="border-y border-amber-200 bg-amber-50 py-4 sm:rounded-2xl sm:border sm:p-5">
          <summary className="cursor-pointer font-semibold text-amber-900">Possible concerns ({aiConcerns.length})</summary>
          <p className="mt-2 text-sm text-amber-900">Possibilities needing assessment review; not saved diagnoses.</p>
          {aiConcerns.map(concern => (
            <div key={concern.concern_id} className="mt-4 space-y-2 border-t border-amber-200 pt-3 text-sm">
              <p className="font-semibold">{concern.label}</p>
              <ul className="list-disc pl-5">{concern.evidence_used.map(finding => <li key={finding}>{finding}</li>)}</ul>
              <p><strong>Clarify: </strong>{concern.missing}</p>
              <div className="flex flex-wrap gap-3">
                <Link href={`/ncp/${patientId}/assessment/${ncpId}`} className="inline-flex min-h-11 items-center font-semibold underline">Review assessment</Link>
                <button type="button" disabled={aiLoading} className="min-h-11 font-semibold underline" onClick={async () => {
                  setAiLoading(true);
                  try {
                    const result = await dismissPesSuggestion(ncpId, concern.concern_id);
                    setAiConcerns(result.concerns ?? []);
                  } catch (err) {
                    setError(err instanceof Error ? err.message : "Could not dismiss concern.");
                  } finally { setAiLoading(false); }
                }}>Dismiss</button>
              </div>
            </div>
          ))}
        </details>
      )}
    </div>
  );

  const tabContent: Record<TabKey, React.ReactNode> = {
    table: renderTableTab(),
    problem: renderProblemTab(),
    etiology: renderEtiologyTab(),
    signs: renderSignsTab(),
    pes: renderPesTab(),
    ai: aiTab,
  };

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="space-y-4 font-sans">
      {/* Breadcrumb */}
      <NcpBreadcrumb step="Nutrition Diagnosis" />

      <NcpPatientHeader
        patient={patient}
        ncpId={ncpId}
        physician={patient?.physician}
        riskScore={assessmentContext?.risk_score ?? assessmentContext?.computed_risk_score}
        foodDetails={[
          ...(assessmentContext?.allergies ?? []),
          ...(assessmentContext?.food_dislikes ?? []),
          assessmentContext?.dietary_restrictions,
        ]}
        interventionGoal={interventionGoal}
        medicalDiagnosis={patient?.medical_diagnosis}
      />

      {hasNoDiagnosis && <div role="alert" className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{noDiagnosisWarning}</div>}

      {/* Status Messages */}
      {error && (
        <div className="px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-sm text-red-700 font-bold flex items-center gap-2">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" /> {error}
        </div>
      )}
      {success && (
        <div className="px-4 py-3 bg-emerald-50 border border-emerald-100 rounded-xl text-sm text-emerald-700 font-bold flex items-center gap-2">
          <CheckCircle2 className="h-3.5 w-3.5 shrink-0" /> {success}
        </div>
      )}

      {/* Tab Navigation */}
      <div className={`min-w-0 rounded-none border-0 shadow-none ${activeTab === "ai" ? "bg-transparent" : "bg-white"} sm:rounded-xl sm:border sm:border-warm-200 sm:bg-white sm:shadow-sm`}>
        <NcpSectionTabs
          items={TABS.map(tab => ({
            ...tab,
            marker: builder.editingId !== null && ["problem", "etiology", "signs", "pes"].includes(tab.key),
          }))}
          value={activeTab}
          onChange={setActiveTab}
          ariaLabel="Diagnosis sections"
          idPrefix="diagnosis"
          panelId="diagnosis-panel"
        />

        <div id="diagnosis-panel" role="tabpanel" aria-labelledby={`diagnosis-tab-${activeTab}`} tabIndex={0} className="px-0 py-4 sm:p-5">
          {tabContent[activeTab]}
        </div>
      </div>

      {/* Builder context bar */}
      {activeTab !== "table" && activeTab !== "ai" && (
        <div className="flex items-center justify-between rounded-none border-0 bg-transparent px-0 py-3.5 shadow-none sm:rounded-xl sm:border sm:border-warm-200 sm:bg-white sm:px-5 sm:shadow-sm">
          <div className="text-xs text-warm-500 font-semibold select-none">
            {builder.editingId ? `Editing diagnosis #${builder.editingId}` : "New diagnosis"}
          </div>
          <button
            type="button"
            onClick={() => { setBuilder(defaultBuilder()); setActiveTab("table"); }}
            className="text-xs font-bold text-warm-400 hover:text-warm-700 transition-colors cursor-pointer"
          >
            Cancel
          </button>
        </div>
      )}
    </div>
  );
}
