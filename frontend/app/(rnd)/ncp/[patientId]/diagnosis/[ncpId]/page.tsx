"use client";

import React, { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Stethoscope, User, ChevronRight,
  Plus, Trash2, Pencil, AlertTriangle, CheckCircle2,
  RefreshCw, X, CheckCheck, Lock,
} from "lucide-react";
import ButtonFilterGroup from "@/components/ui/ButtonFilterGroup";
import { fetchPatientById, Patient } from "@/services/patientService";
import {
  fetchDiagnosesPage, storeDiagnosis, updateDiagnosis, deleteDiagnosis,
  aiSuggestDiagnoses, aiApproveDiagnosis, dismissPesSuggestion,
  Diagnosis, StoreDiagnosisPayload, AiSuggestion, PesDraftMeta,
} from "@/services/diagnosisService";
import { fetchAssessment, type Assessment } from "@/services/assessmentService";
import { fetchIntervention } from "@/services/interventionService";
import { buildDiagnosisProblemText } from "@/lib/diagnosisBuilder";
import { matchStoredOption, splitStoredComponent } from "@/lib/diagnosisComponentSplit";
import NcpPatientHeader from "../../../_components/NcpPatientHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { InfoHint } from "@/components/ui/InfoHint";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import { candidateBuilderSelections } from "@/lib/pesBuilderSelections";
import { NcpBreadcrumb } from "@/components/ncp/NcpBreadcrumb";
import { paginateAiDrafts } from "@/lib/aiDraftPagination";

// ─── Domain Metadata ─────────────────────────────────────────────────────────

const DOMAIN_META = {
  NI: { label: "Intake (NI)" },
  NC: { label: "Clinical (NC)" },
  NB: { label: "Behavioral-Env (NB)" },
} as const;

// ─── G-NCP Problem Options ────────────────────────────────────────────────────

const NI_NUTRIENTS = [
  "Energy", "Protein", "Carbohydrates", "Fat", "Fluid", "Fiber",
  "Vitamins (specify)", "Minerals (specify)", "Oral Intake",
];

const NC_PROBLEMS = [
  "Unintended Weight Loss",
  "Overweight / Obesity",
  "Malnutrition (undernutrition)",
  "Altered GI Function",
  "Swallowing / Chewing Difficulty",
  "Altered Nutrition-Related Laboratory Values",
  "Food-Medication Interaction",
  "Predicted Suboptimal Intake",
  "Impaired Nutrient Utilization",
];

const NB_PROBLEMS = [
  "Food and Nutrition Knowledge Deficit",
  "Harmful Beliefs / Attitudes about Food",
  "Not Ready for Diet / Lifestyle Change",
  "Self-Monitoring Deficit",
  "Disordered Eating Pattern",
  "Limited Adherence to Nutrition-Related Recommendations",
  "Undesirable Food Choices",
  "Physical Inactivity",
  "Food Insecurity",
];

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
];

// ─── G-NCP Signs & Symptoms Options ──────────────────────────────────────────

const NI_SIGNS = [
  "Estimated intake < 75% of estimated needs",
  "Estimated intake > 125% of estimated needs",
  "Unintentional weight loss > 5% in 3 months",
  "BMI < 18.5 (underweight)",
  "BMI > 25 (overweight) or > 30 (obese)",
  "Serum albumin < 3.5 g/dL",
  "Low hemoglobin / hematocrit",
  "Muscle wasting noted on physical exam",
  "Peripheral edema or ascites",
  "Fatigue / weakness on activity",
];

const NC_SIGNS = [
  "BMI ≥ 25 (overweight / obesity)",
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
];

function getEtiologies(domain: "NI" | "NC" | "NB") {
  if (domain === "NI") return NI_ETIOLOGIES;
  if (domain === "NC") return NC_ETIOLOGIES;
  return NB_ETIOLOGIES;
}

function getSigns(domain: "NI" | "NC" | "NB") {
  if (domain === "NI") return NI_SIGNS;
  if (domain === "NC") return NC_SIGNS;
  return NB_SIGNS;
}

// ─── Types ────────────────────────────────────────────────────────────────────

type TabKey = "table" | "problem" | "etiology" | "signs" | "pes" | "ai";

interface BuilderState {
  editingId: number | null;
  domain: "NI" | "NC" | "NB";
  niDirection: "Inadequate" | "Excessive";
  niNutrient: string;
  ncProblems: string[];
  nbProblems: string[];
  etiologyChecks: string[];
  etiologyNotes: string;
  signChecks: string[];
  signNotes: string;
  pesOverride: string;
  extraNotes: string;
  problemOverride: string;
}

function defaultBuilder(): BuilderState {
  return {
    editingId: null,
    domain: "NI",
    niDirection: "Inadequate",
    niNutrient: "",
    ncProblems: [],
    nbProblems: [],
    etiologyChecks: [],
    etiologyNotes: "",
    signChecks: [],
    signNotes: "",
    pesOverride: "",
    extraNotes: "",
    problemOverride: "",
  };
}

function buildProblemText(b: BuilderState): string {
  return buildDiagnosisProblemText(b);
}

function buildEtiologyText(b: BuilderState): string {
  const parts = [...b.etiologyChecks];
  if (b.etiologyNotes.trim()) parts.push(b.etiologyNotes.trim());
  return parts.length > 0 ? parts.join("; ") : "[Select etiology]";
}

function buildSignsText(b: BuilderState): string {
  const parts = [...b.signChecks];
  if (b.signNotes.trim()) parts.push(b.signNotes.trim());
  return parts.length > 0 ? parts.join("; ") : "[Select signs and symptoms]";
}

function buildPes(problem: string, etiology: string, signs: string): string {
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
    ai_generated: false,
  };
}

function loadBuilderFromDiagnosis(d: Diagnosis): BuilderState {
  const b = defaultBuilder();
  b.editingId = d.id ?? null;
  b.domain = d.domain;
  b.extraNotes = d.extra_notes ?? "";
  b.pesOverride = d.pes_statement ?? "";
  b.problemOverride = d.problem ?? "";

  // Re-hydrate checkbox selections from the stored joined strings.
  const etiology = splitStoredComponent(d.etiology ?? "", getEtiologies(d.domain));
  b.etiologyChecks = etiology.checks;
  b.etiologyNotes = etiology.notes;
  const signs = splitStoredComponent(d.signs_symptoms ?? "", getSigns(d.domain));
  b.signChecks = signs.checks;
  b.signNotes = signs.notes;

  if (d.domain === "NI") {
    const match = d.problem.match(/^(Inadequate|Excessive)\s+(.+)\s+Intake$/i);
    if (match) {
      b.niDirection = (match[1] as "Inadequate" | "Excessive");
      b.niNutrient = match[2];
      b.problemOverride = "";
    }
  } else if (d.domain === "NC") {
    b.ncProblems = [matchStoredOption(d.problem, NC_PROBLEMS) ?? d.problem];
    b.problemOverride = "";
  } else {
    b.nbProblems = [matchStoredOption(d.problem, NB_PROBLEMS) ?? d.problem];
    b.problemOverride = "";
  }
  return b;
}

// ─── Small UI Helpers ─────────────────────────────────────────────────────────

function DomainBadge({ domain }: { domain: "NI" | "NC" | "NB" }) {
  const meta = DOMAIN_META[domain];
  return (
    <span className="text-xs font-semibold text-warm-700 whitespace-nowrap">
      {meta.label}
    </span>
  );
}

function Checkbox({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex items-start gap-2 text-xs text-warm-700 cursor-pointer hover:bg-warm-50 p-1.5 rounded-lg transition-colors">
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
  const [diagnosesPage, setDiagnosesPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [domainFilter, setDomainFilter] = useState<"ALL" | "NI" | "NC" | "NB">("ALL");
  const [builder, setBuilder] = useState<BuilderState>(defaultBuilder());
  const [aiSuggestions, setAiSuggestions] = useState<AiSuggestion[]>([]);
  const [aiDraftsPage, setAiDraftsPage] = useState(1);
  const [aiLoading, setAiLoading] = useState(false);
  const [aiMeta, setAiMeta] = useState<PesDraftMeta | null>(null);
  const [aiUnavailable, setAiUnavailable] = useState<string | null>(null);
  const [hasAssessment, setHasAssessment] = useState(false);
  const [assessmentContext, setAssessmentContext] = useState<Assessment | null>(null);
  const [interventionGoal, setInterventionGoal] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    try {
      setLoading(true);
      const [p, d, a, intervention] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchDiagnosesPage(ncpId, diagnosesPage),
        fetchAssessment(ncpId),
        fetchIntervention(ncpId),
      ]);
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
      setLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder, diagnosesPage]);

  useEffect(() => { void loadData(); }, [loadData]);

  // Auto-update PES override when builder fields change
  useEffect(() => {
    const problem = buildProblemText(builder);
    const etiology = buildEtiologyText(builder);
    const signs = buildSignsText(builder);
    if (
      !problem.includes("[") &&
      !etiology.includes("[") &&
      !signs.includes("[")
    ) {
      setBuilder(prev => ({ ...prev, pesOverride: buildPes(problem, etiology, signs) }));
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    builder.domain, builder.niDirection, builder.niNutrient,
    builder.ncProblems, builder.nbProblems, builder.problemOverride,
    builder.etiologyChecks, builder.etiologyNotes,
    builder.signChecks, builder.signNotes,
  ]);

  const updateBuilder = (patch: Partial<BuilderState>) => setBuilder(prev => ({ ...prev, ...patch }));

  const startNew = () => {
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
    if (!d.id) return;
    if (!confirm(`Delete this diagnosis?\n"${d.pes_statement ?? d.problem}"`)) return;
    try {
      setDeleting(d.id);
      await deleteDiagnosis(ncpId, d.id);
      if (diagnoses.length === 1 && diagnosesPage > 1) {
        setDiagnosesPage(diagnosesPage - 1);
      } else {
        await loadData();
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to delete.");
    } finally {
      setDeleting(null);
    }
  };

  const handleSave = async () => {
    const payload = buildPayload(builder);
    const problem = buildProblemText(builder);
    const etiology = buildEtiologyText(builder);
    const signs = buildSignsText(builder);

    if (problem.includes("[") || etiology.includes("[") || signs.includes("[")) {
      setError("Please complete the Problem, Etiology, and Signs & Symptoms before saving.");
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
        await loadData();
      } else {
        setDiagnosesPage(1);
      }
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  const handleAiSuggest = async () => {
    try {
      setAiLoading(true);
      setError(null);
      setAiUnavailable(null);
      const result = await aiSuggestDiagnoses(ncpId);
      setAiSuggestions(result.data);
      setAiDraftsPage(1);
      setAiMeta(result.meta);
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

  const handleAiAccept = async (s: AiSuggestion) => {
    try {
      await aiApproveDiagnosis(ncpId, {
        domain: s.domain,
        label: s.label,
        etiology: s.etiology,
        signs: s.signs,
      });
      if (diagnosesPage === 1) {
        await loadData();
      } else {
        setDiagnosesPage(1);
      }
      setAiSuggestions(prev => prev.filter(draft => draft.candidate_id !== s.candidate_id));
      setAiDraftsPage(1);
      setSuccess("PES draft accepted and saved.");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to accept diagnosis.");
    }
  };

  const handleAiDismiss = async (s: AiSuggestion) => {
    try {
      setAiLoading(true);
      setError(null);
      const result = await dismissPesSuggestion(ncpId, s.candidate_id);
      setAiSuggestions(result.data);
      setAiDraftsPage(1);
      setAiMeta(result.meta);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to dismiss PES draft.");
    } finally {
      setAiLoading(false);
    }
  };

  const handleAiEdit = (s: AiSuggestion) => {
    const b = defaultBuilder();
    b.domain = s.domain;
    const etiology = splitStoredComponent(s.etiology, getEtiologies(s.domain));
    const signs = splitStoredComponent(s.signs, getSigns(s.domain));
    b.etiologyChecks = etiology.checks;
    b.etiologyNotes = etiology.notes;
    b.signChecks = signs.checks;
    b.signNotes = signs.notes;
    const candidateSelections = candidateBuilderSelections(s.candidate_id);
    if (candidateSelections) {
      if (!b.etiologyChecks.includes(candidateSelections.etiology)) b.etiologyChecks.push(candidateSelections.etiology);
      if (!b.signChecks.includes(candidateSelections.sign)) b.signChecks.push(candidateSelections.sign);
    }
    b.pesOverride = `${s.label} related to ${s.etiology} as evidenced by ${s.signs}`;
    b.problemOverride = s.label;
    if (s.domain === "NI") {
      const match = s.label.match(/^(Inadequate|Excessive)\s+(.+)\s+Intake$/i);
      if (match) {
        b.niDirection = match[1] as "Inadequate" | "Excessive";
        b.niNutrient = match[2];
        b.problemOverride = "";
      }
    } else if (s.domain === "NC") {
      const matchedProblem = matchStoredOption(s.label, NC_PROBLEMS);
      b.ncProblems = matchedProblem ? [matchedProblem] : [];
      b.problemOverride = matchedProblem ? "" : s.label;
    } else {
      const matchedProblem = matchStoredOption(s.label, NB_PROBLEMS);
      b.nbProblems = matchedProblem ? [matchedProblem] : [];
      b.problemOverride = matchedProblem ? "" : s.label;
    }
    setBuilder(b);
    setActiveTab("problem");
  };

  // ─── Placeholder / Loading ────────────────────────────────────────────────

  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Diagnosis" />
        <div className="bg-white border border-warm-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
            <User className="h-8 w-8" />
          </div>
          <h3 className="text-base font-bold text-warm-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
          <p className="text-sm text-warm-500 mt-2 leading-relaxed">Navigate to the NCP Patients directory and select a patient.</p>
          <Link href="/ncp/patients" className="inline-flex mt-6 px-4 py-2.5 bg-forest-900 hover:bg-forest-900 text-white text-sm font-bold uppercase tracking-wider rounded-lg transition-colors">
            Go to Patients Directory
          </Link>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <NcpBreadcrumb step="Nutrition Diagnosis" />
        <div className="space-y-4">{[1, 2, 3].map(i => <div key={i} className="h-16 bg-warm-100 rounded-xl animate-pulse" />)}</div>
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
    { key: "etiology", label: "E — Etiology" },
    { key: "signs", label: "S — Signs & Symptoms" },
    { key: "pes", label: "PES Statement" },
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
          ]}
          value={domainFilter}
          onChange={(val) => setDomainFilter(val as "ALL" | "NI" | "NC" | "NB")}
          label="Filter:"
        />
        <button
          type="button"
          onClick={startNew}
          className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer shadow-sm"
        >
          <Plus className="h-3.5 w-3.5" />
          Add New Diagnosis
        </button>
      </div>

      {filteredDiagnoses.length === 0 ? (
        <div className="bg-white border border-warm-200 rounded-2xl p-10 text-center shadow-sm">
          <Stethoscope className="h-7 w-7 text-warm-300 mx-auto mb-3" />
          <p className="text-sm font-bold text-warm-600 uppercase tracking-wider">No diagnoses recorded</p>
          <p className="text-xs text-warm-400 mt-1">
            {domainFilter !== "ALL" ? `No ${DOMAIN_META[domainFilter].label} diagnoses yet.` : `Click "Add New Diagnosis" to begin the PES builder.`}
          </p>
        </div>
      ) : (
        <div className="bg-white border border-warm-200 rounded-2xl overflow-hidden shadow-sm">
          <div className="overflow-x-auto">
          <table className="w-full text-sm min-w-[480px]">
            <thead>
              <tr className="border-b border-warm-100 bg-warm-50/80">
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider w-8">#</th>
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Domain</th>
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Problem</th>
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider hidden xl:table-cell">Etiology</th>
                <th className="text-left px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider hidden xl:table-cell">S&S</th>
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
                  <td className="px-4 py-3 text-warm-800 font-semibold max-w-[160px]">
                    <span className="line-clamp-2">{d.problem}</span>
                  </td>
                  <td className="px-4 py-3 text-warm-600 hidden xl:table-cell max-w-[160px]">
                    <span className="line-clamp-2">{d.etiology}</span>
                  </td>
                  <td className="px-4 py-3 text-warm-600 hidden xl:table-cell max-w-[160px]">
                    <span className="line-clamp-2">{d.signs_symptoms}</span>
                  </td>
                  <td className="px-4 py-3 text-warm-700 max-w-[240px]">
                    <span className="line-clamp-3 italic text-xs">{d.pes_statement}</span>
                    {d.extra_notes && (
                      <span className="block mt-1 text-xs text-warm-400">{d.extra_notes}</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => startEdit(d)}
                        className="p-1.5 text-warm-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors cursor-pointer"
                        title="Edit"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </button>
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

  const renderProblemTab = () => (
    <div className="space-y-6 max-w-2xl">
      <div>
        <SectionLabel>Step 1: Select Diagnostic Domain</SectionLabel>
        <div className="flex gap-4 flex-wrap border-b border-warm-200">
          {(["NI", "NC", "NB"] as const).map(d => (
            <button
              key={d}
              type="button"
              onClick={() => updateBuilder({ domain: d, ncProblems: [], nbProblems: [], niNutrient: "", etiologyChecks: [], signChecks: [], problemOverride: "" })}
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

      {builder.domain === "NI" && (
        <div className="space-y-4">
          <div>
            <SectionLabel>Step 2: Select Direction</SectionLabel>
            <div className="flex gap-3">
              {(["Inadequate", "Excessive"] as const).map(dir => (
                <button
                  key={dir}
                  type="button"
                  onClick={() => updateBuilder({ niDirection: dir, problemOverride: "" })}
                  className={`px-4 py-2 text-sm font-bold rounded-xl border-2 transition-all cursor-pointer ${
                    builder.niDirection === dir
                      ? "bg-sky-50 text-sky-700 border-sky-400"
                      : "bg-white text-warm-500 border-warm-200 hover:border-zinc-400"
                  }`}
                >
                  {dir}
                </button>
              ))}
            </div>
          </div>
          <div>
            <SectionLabel>Step 3: Select Nutrient / Substance</SectionLabel>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
              {NI_NUTRIENTS.map(n => (
                <button
                  key={n}
                  type="button"
                  onClick={() => updateBuilder({ niNutrient: n, problemOverride: "" })}
                  className={`px-3 py-2 text-sm font-semibold rounded-lg border transition-all cursor-pointer text-left ${
                    builder.niNutrient === n
                      ? "bg-sky-50 text-sky-800 border-sky-300 font-bold"
                      : "bg-white text-warm-600 border-warm-200 hover:border-warm-300"
                  }`}
                >
                  {n}
                </button>
              ))}
            </div>
            {builder.niNutrient.includes("specify") && (
              <input
                type="text"
                value={builder.niNutrient.includes("specify") ? "" : builder.niNutrient}
                onChange={e => updateBuilder({ niNutrient: e.target.value, problemOverride: "" })}
                className="mt-2 w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-400/20 focus:border-sky-400"
              />
            )}
          </div>
        </div>
      )}

      {builder.domain === "NC" && (
        <div>
          <SectionLabel>Step 2: Select Clinical Problem(s)</SectionLabel>
          <div className="space-y-1 bg-warm-50/60 rounded-xl p-4 border border-warm-200">
            {NC_PROBLEMS.map(p => (
              <Checkbox
                key={p}
                checked={builder.ncProblems.includes(p)}
                onChange={checked => updateBuilder({
                  problemOverride: "",
                  ncProblems: checked
                    ? [...builder.ncProblems, p]
                    : builder.ncProblems.filter(x => x !== p)
                })}
                label={p}
              />
            ))}
          </div>
        </div>
      )}

      {builder.domain === "NB" && (
        <div>
          <SectionLabel>Step 2: Select Behavioral-Environmental Problem(s)</SectionLabel>
          <div className="space-y-1 bg-warm-50/60 rounded-xl p-4 border border-warm-200">
            {NB_PROBLEMS.map(p => (
              <Checkbox
                key={p}
                checked={builder.nbProblems.includes(p)}
                onChange={checked => updateBuilder({
                  problemOverride: "",
                  nbProblems: checked
                    ? [...builder.nbProblems, p]
                    : builder.nbProblems.filter(x => x !== p)
                })}
                label={p}
              />
            ))}
          </div>
        </div>
      )}

      <div>
        <SectionLabel>Extra Notes (optional)</SectionLabel>
        <CharacterCountTextarea
          value={builder.extraNotes}
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
          onClick={() => setActiveTab("etiology")}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
        >
          Next: Etiology <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );

  const renderEtiologyTab = () => (
    <div className="space-y-5 max-w-2xl">
      <div>
        <SectionLabel>Select Etiology Factors (Related to)</SectionLabel>
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
        </div>
      </div>
      <div>
        <SectionLabel>Additional Etiology Notes (free text)</SectionLabel>
        <CharacterCountTextarea
          value={builder.etiologyNotes}
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
        <div className="space-y-1 bg-warm-50/60 rounded-xl p-4 border border-warm-200">
          {getSigns(builder.domain).map(s => (
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
        </div>
      </div>
      <div>
        <SectionLabel>Additional Signs & Symptoms Notes (free text)</SectionLabel>
        <CharacterCountTextarea
          value={builder.signNotes}
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
    const isComplete = !problem.includes("[") && !etiology.includes("[") && !signs.includes("[");

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
              onChange={value => updateBuilder({ pesOverride: value })}
              rows={4}
              maxLength={500}
              className="w-full px-3 py-2 text-sm bg-white border border-warm-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-medium text-warm-900 placeholder:text-warm-400 resize-none"
            />
            <p className="text-xs text-warm-400 mt-1">The statement above is auto-generated from the builder. Edit manually if needed before saving.</p>
          </div>

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

  const renderAiTab = () => (
    <div className="space-y-5">
      <div className="bg-white border border-warm-200 rounded-2xl p-5">
        <div className="mb-2 flex items-center gap-1 text-sm font-bold uppercase tracking-wider text-emerald-600">
          <span>AI Suggestions</span>
          <InfoHint label="How PES drafts work" title="How PES drafts work">
            Uses only eligible, de-identified Assessment evidence. Review before saving; manual PES entry remains available.
          </InfoHint>
        </div>
        {!aiMeta && !aiUnavailable && (
          <button
            type="button"
            onClick={handleAiSuggest}
            disabled={aiLoading || !hasAssessment}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-warm-300 text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed"
          >
            {aiLoading && <RefreshCw className="h-3.5 w-3.5 animate-spin" />}
            {aiLoading ? "Generating suggestions..." : "Generate AI Suggestions"}
          </button>
        )}
        {aiUnavailable && (
          <p className="text-xs font-semibold text-amber-700">{aiUnavailable}</p>
        )}
        {!hasAssessment && (
          <p className="text-xs font-semibold text-amber-700">Save the Assessment before requesting PES drafts.</p>
        )}
      </div>

      {aiMeta?.message && aiSuggestions.length === 0 && (
        <div className="rounded-xl border border-warm-200 bg-warm-50 px-4 py-3 text-sm text-warm-700">
          {aiMeta.message || "No sufficiently supported PES draft was found."}
        </div>
      )}

      {aiSuggestions.length > 0 && (() => {
        const { items: visibleAiDrafts, meta: aiDraftsMeta } = paginateAiDrafts(aiSuggestions, aiDraftsPage);
        return (
        <div className="space-y-3">
          <h4 className="text-xs font-extrabold text-warm-700 uppercase tracking-wider">
            PES drafts ({aiSuggestions.length} pending)
          </h4>
          {visibleAiDrafts.map(s => {
            const pes = `${s.label} related to ${s.etiology} as evidenced by ${s.signs}`;
            return (
              <div key={s.candidate_id} className="bg-white border border-warm-200 rounded-2xl p-5 shadow-sm space-y-3">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <DomainBadge domain={s.domain} />
                  <span className="text-xs font-bold text-orange-600 uppercase tracking-wider">AI draft for RND review</span>
                </div>
                <div className="space-y-2 text-sm">
                  <div><span className="font-bold text-sky-600">P:</span> <span className="text-warm-700">{s.label}</span></div>
                  <div><span className="font-bold text-sky-600">E:</span> <span className="text-warm-700">{s.etiology}</span></div>
                  <div><span className="font-bold text-amber-600">S:</span> <span className="text-warm-700">{s.signs}</span></div>
                </div>
                <div className="p-3 bg-warm-50 border border-warm-100 rounded-lg">
                  <span className="text-xs font-bold text-warm-400 uppercase tracking-wider block mb-1">PES Statement</span>
                  <p className="text-xs font-medium text-warm-800 italic leading-relaxed">{pes}</p>
                </div>
                <div className="rounded-lg border border-warm-100 bg-warm-50 p-3 text-xs text-warm-600">
                  <div>
                    <span className="mb-1 block font-bold uppercase tracking-wider text-warm-500">Evidence used</span>
                    <ul className="list-disc space-y-1 pl-4">
                      {s.evidence_used.map(item => <li key={item}>{item}</li>)}
                    </ul>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-warm-100">
                  <button
                    type="button"
                    onClick={() => handleAiAccept(s)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
                  >
                    <CheckCheck className="h-3 w-3" /> Accept
                  </button>
                  <button
                    type="button"
                    onClick={() => handleAiEdit(s)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold uppercase tracking-wider rounded-lg border border-sky-200 transition-colors cursor-pointer"
                  >
                    <Pencil className="h-3 w-3" /> Edit
                  </button>
                  <button
                    type="button"
                    onClick={() => handleAiDismiss(s)}
                    disabled={aiLoading}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-warm-100 hover:bg-warm-200 text-warm-600 text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
                  >
                    <X className="h-3 w-3" /> Dismiss
                  </button>
                </div>
              </div>
            );
          })}
          <Pagination meta={aiDraftsMeta} page={aiDraftsMeta.current_page} onPageChange={setAiDraftsPage} />
        </div>
        );
      })()}
    </div>
  );

  const tabContent: Record<TabKey, React.ReactNode> = {
    table: renderTableTab(),
    problem: renderProblemTab(),
    etiology: renderEtiologyTab(),
    signs: renderSignsTab(),
    pes: renderPesTab(),
    ai: renderAiTab(),
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
      <div className="bg-white border border-warm-200 rounded-xl overflow-hidden shadow-sm">
        <div className="flex overflow-x-auto border-b border-warm-200 bg-warm-50/50">
          {TABS.map(tab => {
            const isActive = activeTab === tab.key;
            const isBuilderTab = ["problem", "etiology", "signs", "pes"].includes(tab.key);
            const isEditing = builder.editingId !== null;
            return (
              <button
                key={tab.key}
                type="button"
                onClick={() => setActiveTab(tab.key)}
                className={`relative flex items-center gap-1.5 px-4 py-3 text-xs font-bold uppercase tracking-wider whitespace-nowrap border-b-2 transition-all cursor-pointer ${
                  isActive
                    ? "text-emerald-700 border-emerald-600 bg-white"
                    : "text-warm-500 border-transparent hover:text-warm-700 hover:bg-white/50"
                }`}
              >
                {tab.label}
                {isBuilderTab && isEditing && (
                  <span className="absolute top-1 right-1 h-1.5 w-1.5 bg-amber-400 rounded-full" />
                )}
              </button>
            );
          })}
        </div>

        <div className="p-5">
          {tabContent[activeTab]}
        </div>
      </div>

      {/* Builder context bar */}
      {activeTab !== "table" && activeTab !== "ai" && (
        <div className="bg-white border border-warm-200 rounded-xl px-5 py-3.5 flex items-center justify-between shadow-sm">
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
