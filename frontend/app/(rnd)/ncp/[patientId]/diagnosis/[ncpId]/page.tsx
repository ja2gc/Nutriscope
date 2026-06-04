"use client";

import React, { use, useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  Stethoscope, Sparkles, User, ChevronRight, Heart,
  Plus, Trash2, Pencil, AlertTriangle, CheckCircle2,
  RefreshCw, X, CheckCheck, Brain,
} from "lucide-react";
import { fetchPatientById, Patient } from "@/services/patientService";
import {
  fetchDiagnoses, storeDiagnosis, updateDiagnosis, deleteDiagnosis,
  aiSuggestDiagnoses, aiApproveDiagnosis,
  Diagnosis, StoreDiagnosisPayload, AiSuggestion,
} from "@/services/diagnosisService";
import { fetchAssessment } from "@/services/assessmentService";

// ─── Domain Metadata ─────────────────────────────────────────────────────────

const DOMAIN_META = {
  NI: { label: "Intake (NI)", color: "bg-blue-50 text-blue-700 border-blue-200" },
  NC: { label: "Clinical (NC)", color: "bg-purple-50 text-purple-700 border-purple-200" },
  NB: { label: "Behavioral-Env (NB)", color: "bg-amber-50 text-amber-700 border-amber-200" },
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
  };
}

function buildProblemText(b: BuilderState): string {
  if (b.domain === "NI") {
    return `${b.niDirection} ${b.niNutrient || "[Nutrient]"} Intake`;
  }
  if (b.domain === "NC") {
    return b.ncProblems.length > 0 ? b.ncProblems.join("; ") : "[Select problem]";
  }
  return b.nbProblems.length > 0 ? b.nbProblems.join("; ") : "[Select problem]";
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
    extra_notes: b.extraNotes || null,
    ai_generated: false,
  };
}

function loadBuilderFromDiagnosis(d: Diagnosis): BuilderState {
  const b = defaultBuilder();
  b.editingId = d.id ?? null;
  b.domain = d.domain;
  b.etiologyNotes = d.etiology;
  b.signNotes = d.signs_symptoms;
  b.extraNotes = d.extra_notes ?? "";
  b.pesOverride = d.pes_statement ?? "";

  if (d.domain === "NI") {
    const match = d.problem.match(/^(Inadequate|Excessive)\s+(.+)\s+Intake$/i);
    if (match) {
      b.niDirection = (match[1] as "Inadequate" | "Excessive");
      b.niNutrient = match[2];
    }
  } else if (d.domain === "NC") {
    b.ncProblems = [d.problem];
  } else {
    b.nbProblems = [d.problem];
  }
  return b;
}

// ─── Small UI Helpers ─────────────────────────────────────────────────────────

function DomainBadge({ domain }: { domain: "NI" | "NC" | "NB" }) {
  const meta = DOMAIN_META[domain];
  return (
    <span className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${meta.color}`}>
      {meta.label}
    </span>
  );
}

function Checkbox({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <label className="flex items-start gap-2 text-[11px] text-zinc-700 cursor-pointer hover:bg-zinc-50 p-1.5 rounded-lg transition-colors">
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
    <span className="block text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1.5">{children}</span>
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
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [domainFilter, setDomainFilter] = useState<"ALL" | "NI" | "NC" | "NB">("ALL");
  const [builder, setBuilder] = useState<BuilderState>(defaultBuilder());
  const [aiSuggestions, setAiSuggestions] = useState<AiSuggestion[]>([]);
  const [aiLoading, setAiLoading] = useState(false);
  const [aiDismissed, setAiDismissed] = useState<Set<number>>(new Set());
  const [assessmentIbw, setAssessmentIbw] = useState<number | null>(null);

  const loadData = useCallback(async () => {
    if (isPlaceholder) { setLoading(false); return; }
    try {
      setLoading(true);
      const [p, d, a] = await Promise.allSettled([
        fetchPatientById(patientId),
        fetchDiagnoses(ncpId),
        fetchAssessment(ncpId),
      ]);
      if (p.status === "fulfilled") setPatient(p.value);
      if (d.status === "fulfilled") setDiagnoses(d.value);
      if (a.status === "fulfilled") {
        const ibw = a.value.ibw_percentage;
        setAssessmentIbw(typeof ibw === "number" ? ibw : ibw ? Number(ibw) : null);
      }
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, [patientId, ncpId, isPlaceholder]);

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
    builder.ncProblems, builder.nbProblems,
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
      setDiagnoses(prev => prev.filter(x => x.id !== d.id));
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
        const updated = await updateDiagnosis(ncpId, builder.editingId, payload);
        setDiagnoses(prev => prev.map(x => x.id === updated.id ? updated : x));
        setSuccess("Diagnosis updated.");
      } else {
        const created = await storeDiagnosis(ncpId, payload);
        setDiagnoses(prev => [...prev, created]);
        setSuccess("Diagnosis saved.");
      }
      setBuilder(defaultBuilder());
      setActiveTab("table");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to save.");
    } finally {
      setSaving(false);
    }
  };

  const handleAiSuggest = async () => {
    const conditions: string[] = [];
    if (patient?.medical_diagnosis) conditions.push(patient.medical_diagnosis);
    diagnoses.forEach(d => conditions.push(d.problem));
    if (conditions.length === 0) conditions.push("General nutritional assessment");

    try {
      setAiLoading(true);
      setError(null);
      const suggestions = await aiSuggestDiagnoses(ncpId, {
        conditions,
        ibw_percentage: assessmentIbw,
      });
      setAiSuggestions(suggestions);
      setAiDismissed(new Set());
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "AI suggestion failed.");
    } finally {
      setAiLoading(false);
    }
  };

  const handleAiAccept = async (s: AiSuggestion, idx: number) => {
    try {
      const created = await aiApproveDiagnosis(ncpId, {
        domain: s.domain,
        label: s.label,
        etiology: s.etiology,
        signs: s.signs,
        priority: s.priority,
      });
      setDiagnoses(prev => [...prev, created]);
      setAiDismissed(prev => new Set([...prev, idx]));
      setSuccess("AI diagnosis accepted and saved.");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to accept diagnosis.");
    }
  };

  const handleAiEdit = (s: AiSuggestion) => {
    const b = defaultBuilder();
    b.domain = s.domain;
    b.etiologyNotes = s.etiology;
    b.signNotes = s.signs;
    b.pesOverride = `${s.label} related to ${s.etiology} as evidenced by ${s.signs}`;
    if (s.domain === "NI") {
      const match = s.label.match(/^(Inadequate|Excessive)\s+(.+)\s+Intake$/i);
      if (match) { b.niDirection = match[1] as "Inadequate" | "Excessive"; b.niNutrient = match[2]; }
    } else if (s.domain === "NC") {
      b.ncProblems = [s.label];
    } else {
      b.nbProblems = [s.label];
    }
    setBuilder(b);
    setActiveTab("problem");
  };

  // ─── Placeholder / Loading ────────────────────────────────────────────────

  if (isPlaceholder) {
    return (
      <div className="space-y-6 font-sans">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <ChevronRight className="h-3 w-3" />
          <span className="text-zinc-600 font-bold">Diagnosis</span>
        </div>
        <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
          <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
            <User className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
          <p className="text-xs text-zinc-500 mt-2 leading-relaxed">Navigate to the NCP Patients directory and select a patient.</p>
          <Link href="/ncp/patients" className="inline-flex mt-6 px-4 py-2.5 bg-zinc-950 hover:bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
            Go to Patients Directory
          </Link>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400">
          <span>Directory</span><ChevronRight className="h-3 w-3" /><span>Loading...</span>
        </div>
        <div className="space-y-4">{[1, 2, 3].map(i => <div key={i} className="h-16 bg-zinc-100 rounded-xl animate-pulse" />)}</div>
      </div>
    );
  }

  const systemId = patient ? `NS-${String(patient.id).padStart(5, "0")}` : "";
  const filteredDiagnoses = domainFilter === "ALL" ? diagnoses : diagnoses.filter(d => d.domain === domainFilter);
  const pesPreview = builder.pesOverride || buildPes(buildProblemText(builder), buildEtiologyText(builder), buildSignsText(builder));

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
        <div className="flex items-center gap-2">
          <span className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Filter:</span>
          {(["ALL", "NI", "NC", "NB"] as const).map(f => (
            <button
              key={f}
              type="button"
              onClick={() => setDomainFilter(f)}
              className={`px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider rounded-lg border transition-all cursor-pointer ${
                domainFilter === f
                  ? "bg-zinc-950 text-white border-zinc-950"
                  : "bg-white text-zinc-500 border-zinc-200 hover:border-zinc-400"
              }`}
            >
              {f}
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={startNew}
          className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer shadow-sm"
        >
          <Plus className="h-3.5 w-3.5" />
          Add New Diagnosis
        </button>
      </div>

      {filteredDiagnoses.length === 0 ? (
        <div className="bg-white border border-zinc-200 rounded-2xl p-10 text-center shadow-sm">
          <Stethoscope className="h-7 w-7 text-zinc-300 mx-auto mb-3" />
          <p className="text-xs font-bold text-zinc-600 uppercase tracking-wider">No diagnoses recorded</p>
          <p className="text-[11px] text-zinc-400 mt-1">
            {domainFilter !== "ALL" ? `No ${DOMAIN_META[domainFilter].label} diagnoses yet.` : `Click "Add New Diagnosis" to begin the PES builder.`}
          </p>
        </div>
      ) : (
        <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-sm">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b border-zinc-100 bg-zinc-50/80">
                <th className="text-left px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider w-8">#</th>
                <th className="text-left px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Domain</th>
                <th className="text-left px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Problem</th>
                <th className="text-left px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider hidden xl:table-cell">Etiology</th>
                <th className="text-left px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider hidden xl:table-cell">S&S</th>
                <th className="text-left px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">PES Statement</th>
                <th className="text-right px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredDiagnoses.map((d, i) => (
                <tr key={d.id} className="border-b border-zinc-50 hover:bg-zinc-50/60 transition-colors">
                  <td className="px-4 py-3 text-zinc-400 font-mono font-bold">{i + 1}</td>
                  <td className="px-4 py-3">
                    <div className="space-y-1">
                      <DomainBadge domain={d.domain} />
                      {d.ai_generated && (
                        <span className="block mt-1 text-[9px] font-bold text-orange-600 uppercase tracking-wider">AI</span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-zinc-800 font-semibold max-w-[160px]">
                    <span className="line-clamp-2">{d.problem}</span>
                  </td>
                  <td className="px-4 py-3 text-zinc-600 hidden xl:table-cell max-w-[160px]">
                    <span className="line-clamp-2">{d.etiology}</span>
                  </td>
                  <td className="px-4 py-3 text-zinc-600 hidden xl:table-cell max-w-[160px]">
                    <span className="line-clamp-2">{d.signs_symptoms}</span>
                  </td>
                  <td className="px-4 py-3 text-zinc-700 max-w-[240px]">
                    <span className="line-clamp-3 italic text-[11px]">{d.pes_statement}</span>
                    {d.extra_notes && (
                      <span className="block mt-1 text-[10px] text-zinc-400">{d.extra_notes}</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => startEdit(d)}
                        className="p-1.5 text-zinc-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors cursor-pointer"
                        title="Edit"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleDelete(d)}
                        disabled={deleting === d.id}
                        className="p-1.5 text-zinc-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer disabled:opacity-50"
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
      )}
    </div>
  );

  const renderProblemTab = () => (
    <div className="space-y-6 max-w-2xl">
      <div>
        <SectionLabel>Step 1: Select Diagnostic Domain</SectionLabel>
        <div className="flex gap-3 flex-wrap">
          {(["NI", "NC", "NB"] as const).map(d => (
            <button
              key={d}
              type="button"
              onClick={() => updateBuilder({ domain: d, ncProblems: [], nbProblems: [], niNutrient: "", etiologyChecks: [], signChecks: [] })}
              className={`px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider rounded-xl border-2 transition-all cursor-pointer ${
                builder.domain === d
                  ? `${DOMAIN_META[d].color} border-current`
                  : "bg-white text-zinc-500 border-zinc-200 hover:border-zinc-400"
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
                  onClick={() => updateBuilder({ niDirection: dir })}
                  className={`px-4 py-2 text-xs font-bold rounded-xl border-2 transition-all cursor-pointer ${
                    builder.niDirection === dir
                      ? "bg-blue-50 text-blue-700 border-blue-400"
                      : "bg-white text-zinc-500 border-zinc-200 hover:border-zinc-400"
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
                  onClick={() => updateBuilder({ niNutrient: n })}
                  className={`px-3 py-2 text-xs font-semibold rounded-lg border transition-all cursor-pointer text-left ${
                    builder.niNutrient === n
                      ? "bg-blue-50 text-blue-800 border-blue-300 font-bold"
                      : "bg-white text-zinc-600 border-zinc-200 hover:border-zinc-300"
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
                onChange={e => updateBuilder({ niNutrient: e.target.value })}
                placeholder="Specify nutrient or mineral..."
                className="mt-2 w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400/20 focus:border-blue-400"
              />
            )}
          </div>
        </div>
      )}

      {builder.domain === "NC" && (
        <div>
          <SectionLabel>Step 2: Select Clinical Problem(s)</SectionLabel>
          <div className="space-y-1 bg-zinc-50/60 rounded-xl p-4 border border-zinc-200">
            {NC_PROBLEMS.map(p => (
              <Checkbox
                key={p}
                checked={builder.ncProblems.includes(p)}
                onChange={checked => updateBuilder({
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
          <div className="space-y-1 bg-zinc-50/60 rounded-xl p-4 border border-zinc-200">
            {NB_PROBLEMS.map(p => (
              <Checkbox
                key={p}
                checked={builder.nbProblems.includes(p)}
                onChange={checked => updateBuilder({
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
        <textarea
          value={builder.extraNotes}
          onChange={e => updateBuilder({ extraNotes: e.target.value })}
          placeholder="Additional context or notes for this diagnosis..."
          rows={2}
          className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 placeholder:text-zinc-400 resize-none"
        />
      </div>

      <div className="p-4 bg-zinc-950 rounded-xl border border-zinc-800">
        <span className="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">Problem preview (P)</span>
        <span className="text-xs font-semibold text-emerald-300">{buildProblemText(builder)}</span>
      </div>

      <div className="flex justify-end">
        <button
          type="button"
          onClick={() => setActiveTab("etiology")}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
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
        <p className="text-[11px] text-zinc-400 mb-3">Select all that apply for the <span className="font-bold text-zinc-600">{DOMAIN_META[builder.domain].label}</span> domain.</p>
        <div className="space-y-1 bg-zinc-50/60 rounded-xl p-4 border border-zinc-200">
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
        <textarea
          value={builder.etiologyNotes}
          onChange={e => updateBuilder({ etiologyNotes: e.target.value })}
          placeholder="Specify any additional etiology not listed above..."
          rows={3}
          className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 placeholder:text-zinc-400 resize-none"
        />
      </div>
      <div className="p-4 bg-zinc-950 rounded-xl border border-zinc-800">
        <span className="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">Etiology preview (E)</span>
        <span className="text-xs font-semibold text-emerald-300">{buildEtiologyText(builder)}</span>
      </div>
      <div className="flex justify-between">
        <button type="button" onClick={() => setActiveTab("problem")} className="px-4 py-2 text-[10px] font-bold text-zinc-600 bg-white border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors cursor-pointer">
          ← Back
        </button>
        <button type="button" onClick={() => setActiveTab("signs")} className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
          Next: Signs & Symptoms <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );

  const renderSignsTab = () => (
    <div className="space-y-5 max-w-2xl">
      <div>
        <SectionLabel>Select Signs & Symptoms (As Evidenced By)</SectionLabel>
        <p className="text-[11px] text-zinc-400 mb-3">Select all that apply for the <span className="font-bold text-zinc-600">{DOMAIN_META[builder.domain].label}</span> domain.</p>
        <div className="space-y-1 bg-zinc-50/60 rounded-xl p-4 border border-zinc-200">
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
        <textarea
          value={builder.signNotes}
          onChange={e => updateBuilder({ signNotes: e.target.value })}
          placeholder="Specify additional signs and symptoms with values, e.g. albumin 2.8 g/dL..."
          rows={3}
          className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 placeholder:text-zinc-400 resize-none"
        />
      </div>
      <div className="p-4 bg-zinc-950 rounded-xl border border-zinc-800">
        <span className="text-[9px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">Signs & Symptoms preview (S)</span>
        <span className="text-xs font-semibold text-emerald-300">{buildSignsText(builder)}</span>
      </div>
      <div className="flex justify-between">
        <button type="button" onClick={() => setActiveTab("etiology")} className="px-4 py-2 text-[10px] font-bold text-zinc-600 bg-white border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors cursor-pointer">
          ← Back
        </button>
        <button type="button" onClick={() => setActiveTab("pes")} className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
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
          <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl space-y-3">
            <div>
              <span className="text-[9px] font-bold text-blue-600 uppercase tracking-wider block mb-0.5">Problem (P)</span>
              <span className="text-xs font-semibold text-zinc-800">{problem}</span>
            </div>
            <div>
              <span className="text-[9px] font-bold text-purple-600 uppercase tracking-wider block mb-0.5">Etiology (E)</span>
              <span className="text-xs font-semibold text-zinc-800">{etiology}</span>
            </div>
            <div>
              <span className="text-[9px] font-bold text-amber-600 uppercase tracking-wider block mb-0.5">Signs & Symptoms (S)</span>
              <span className="text-xs font-semibold text-zinc-800">{signs}</span>
            </div>
          </div>

          <div>
            <SectionLabel>PES Statement (Auto-Generated — Editable)</SectionLabel>
            <textarea
              value={builder.pesOverride}
              onChange={e => updateBuilder({ pesOverride: e.target.value })}
              rows={4}
              className="w-full px-3 py-2 text-xs bg-white border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-medium text-zinc-900 placeholder:text-zinc-400 resize-none"
              placeholder="PES statement will auto-populate when P, E, and S are complete..."
            />
            <p className="text-[10px] text-zinc-400 mt-1">The statement above is auto-generated from the builder. Edit manually if needed before saving.</p>
          </div>

          {!isComplete && (
            <div className="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-700 font-semibold">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
              Please complete Problem, Etiology, and Signs & Symptoms first.
            </div>
          )}
        </div>

        <div className="flex justify-between">
          <button type="button" onClick={() => setActiveTab("signs")} className="px-4 py-2 text-[10px] font-bold text-zinc-600 bg-white border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors cursor-pointer">
            ← Back
          </button>
          <button
            type="button"
            onClick={handleSave}
            disabled={saving || !isComplete}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-zinc-300 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed shadow-sm"
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
      <div className="bg-zinc-950 border border-zinc-800 rounded-2xl p-5 text-zinc-100">
        <div className="flex items-center gap-2 text-orange-400 font-bold text-xs uppercase tracking-wider mb-2">
          <Brain className="h-4 w-4" />
          AI Clinical Reasoning Engine
        </div>
        <p className="text-[11px] text-zinc-400 leading-relaxed mb-4">
          Analyzes patient medical diagnosis, existing assessments, and nutritional data to generate draft G-NCP PES statements for review. Using patient: <span className="text-zinc-200 font-semibold">{patient?.medical_diagnosis ?? "No diagnosis on file"}</span>
        </p>
        <button
          type="button"
          onClick={handleAiSuggest}
          disabled={aiLoading}
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-700 text-white text-[10px] font-extrabold uppercase tracking-wider rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed"
        >
          {aiLoading ? <RefreshCw className="h-3.5 w-3.5 animate-spin" /> : <Sparkles className="h-3.5 w-3.5" />}
          {aiLoading ? "Generating suggestions..." : "Generate AI Suggestions"}
        </button>
      </div>

      {aiSuggestions.length > 0 && (
        <div className="space-y-3">
          <h4 className="text-[10px] font-extrabold text-zinc-700 uppercase tracking-wider">
            AI Suggestions ({aiSuggestions.filter((_, i) => !aiDismissed.has(i)).length} pending)
          </h4>
          {aiSuggestions.map((s, i) => {
            if (aiDismissed.has(i)) return null;
            const pes = `${s.label} related to ${s.etiology} as evidenced by ${s.signs}`;
            return (
              <div key={i} className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-3">
                <div className="flex items-start justify-between gap-3">
                  <div className="space-y-1">
                    <DomainBadge domain={s.domain} />
                    <span className="block text-[9px] font-bold text-orange-500 uppercase tracking-wider">AI Generated</span>
                  </div>
                  {s.confidence !== undefined && (
                    <span className={`text-[9px] font-bold px-2 py-0.5 rounded-full border ${
                      s.confidence > 0.8 ? "bg-emerald-50 text-emerald-700 border-emerald-200" :
                      s.confidence >= 0.5 ? "bg-amber-50 text-amber-700 border-amber-200" :
                      "bg-red-50 text-red-700 border-red-200"
                    }`}>
                      {Math.round(s.confidence * 100)}% confidence
                    </span>
                  )}
                </div>
                <div className="space-y-2 text-xs">
                  <div><span className="font-bold text-blue-600">P:</span> <span className="text-zinc-700">{s.label}</span></div>
                  <div><span className="font-bold text-purple-600">E:</span> <span className="text-zinc-700">{s.etiology}</span></div>
                  <div><span className="font-bold text-amber-600">S:</span> <span className="text-zinc-700">{s.signs}</span></div>
                </div>
                <div className="p-3 bg-zinc-50 border border-zinc-100 rounded-lg">
                  <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">PES Statement</span>
                  <p className="text-[11px] font-medium text-zinc-800 italic leading-relaxed">{pes}</p>
                </div>
                {s.reasoning && (
                  <p className="text-[10px] text-zinc-500 leading-relaxed">{s.reasoning}</p>
                )}
                <div className="flex items-center gap-2 pt-1 border-t border-zinc-100">
                  <button
                    type="button"
                    onClick={() => handleAiAccept(s, i)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
                  >
                    <CheckCheck className="h-3 w-3" /> Accept
                  </button>
                  <button
                    type="button"
                    onClick={() => handleAiEdit(s)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-blue-200 transition-colors cursor-pointer"
                  >
                    <Pencil className="h-3 w-3" /> Edit then Accept
                  </button>
                  <button
                    type="button"
                    onClick={() => setAiDismissed(prev => new Set([...prev, i]))}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-600 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer"
                  >
                    <X className="h-3 w-3" /> Reject
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
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
      <div className="flex items-center gap-1.5 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
        <ChevronRight className="h-3 w-3" />
        <Link href={`/ncp/patients/${patientId}`} className="hover:text-emerald-700 transition-colors">
          {patient?.name ?? systemId}
        </Link>
        <ChevronRight className="h-3 w-3" />
        <span className="text-zinc-700 font-bold">Diagnosis</span>
      </div>

      {/* Patient Header */}
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
              <span className="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-100 rounded font-bold">
                Dx: {patient.medical_diagnosis}
              </span>
            )}
            <span className="px-2 py-0.5 bg-stethoscope-50 text-zinc-500 rounded font-bold">
              {diagnoses.length} {diagnoses.length !== 1 ? "diagnoses" : "diagnosis"} recorded
            </span>
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
          <CheckCircle2 className="h-3.5 w-3.5 shrink-0" /> {success}
        </div>
      )}

      {/* Tab Navigation */}
      <div className="bg-white border border-zinc-200 rounded-xl overflow-hidden shadow-sm">
        <div className="flex overflow-x-auto border-b border-zinc-200 bg-zinc-50/50">
          {TABS.map(tab => {
            const isActive = activeTab === tab.key;
            const isBuilderTab = ["problem", "etiology", "signs", "pes"].includes(tab.key);
            const isEditing = builder.editingId !== null;
            return (
              <button
                key={tab.key}
                type="button"
                onClick={() => setActiveTab(tab.key)}
                className={`relative flex items-center gap-1.5 px-4 py-3 text-[10px] font-bold uppercase tracking-wider whitespace-nowrap border-b-2 transition-all cursor-pointer ${
                  isActive
                    ? "text-emerald-700 border-emerald-600 bg-white"
                    : "text-zinc-500 border-transparent hover:text-zinc-700 hover:bg-white/50"
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
        <div className="bg-white border border-zinc-200 rounded-xl px-5 py-3.5 flex items-center justify-between shadow-sm">
          <div className="text-[10px] text-zinc-500 font-semibold select-none">
            {builder.editingId ? `Editing diagnosis #${builder.editingId}` : "New diagnosis"} · NCP Cycle #{ncpId}
          </div>
          <button
            type="button"
            onClick={() => { setBuilder(defaultBuilder()); setActiveTab("table"); }}
            className="text-[10px] font-bold text-zinc-400 hover:text-zinc-700 transition-colors cursor-pointer"
          >
            Cancel
          </button>
        </div>
      )}
    </div>
  );
}
