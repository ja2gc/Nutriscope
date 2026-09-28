"use client";

import { useState } from "react";
import { ChevronDown, ChevronUp, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  Collapsible,
  CollapsibleTrigger,
  CollapsibleContent,
} from "@/components/ui/collapsible";
import {
  MonitoringPayload,
  ComplianceStatus,
  GiToleranceStatus,
  ContinuationDecision,
  GOAL_LAB_FLAGS,
  CLINICAL_LAB_META,
  type ClinicalLabKey,
  buildInterventionRevisionPayload,
  calculateBmi,
} from "@/services/monitoringService";
import type { Intervention } from "@/services/interventionService";
import type { MonitoringPlan } from "@/services/monitoringPlan";
import { GOAL_MICRO_FLAGS, ALL_MICROS } from "@/lib/nutritionCalculations";
import { GOALS, visibleStagesForGoal } from "../../../intervention/[ncpId]/_components/goals";
import { InfoHint } from "@/components/ui/InfoHint";

// ─── Props ────────────────────────────────────────────────────────────────────

interface LogVisitFormProps {
  plan?: MonitoringPlan | null;
  heightCm: number | null;
  intervention: Intervention | null;
  onSubmit: (payload: MonitoringPayload, options: { openIntervention: boolean }) => Promise<void>;
  onCancel: () => void;
}

// ─── Toggle option configs ─────────────────────────────────────────────────

const COMPLIANCE_OPTIONS: { value: ComplianceStatus; label: string; active: string }[] = [
  { value: "compliant",     label: "Compliant",   active: "bg-emerald-600 text-white border-emerald-600" },
  { value: "partial",       label: "Partial",     active: "bg-amber-500 text-white border-amber-500" },
  { value: "non_compliant", label: "Non-compl.",  active: "bg-red-500 text-white border-red-500" },
];

const GI_OPTIONS: { value: GiToleranceStatus; label: string; active: string }[] = [
  { value: "tolerating",     label: "Tolerating",     active: "bg-emerald-600 text-white border-emerald-600" },
  { value: "not_tolerating", label: "Not Tolerating", active: "bg-red-500 text-white border-red-500" },
];

type NonNullDecision = NonNullable<ContinuationDecision>;

const DECISION_OPTIONS: { value: NonNullDecision; label: string; active: string }[] = [
  { value: "continue",    label: "Continue",    active: "bg-emerald-600 text-white border-emerald-600" },
  { value: "modify",      label: "Modify",      active: "bg-amber-500 text-white border-amber-500" },
  { value: "discontinue", label: "Discontinue", active: "bg-forest-700 text-white border-zinc-700" },
];

// ─── Macro fields metadata ────────────────────────────────────────────────────

const MACRO_FIELDS = [
  { key: "energy_kcal", label: "Energy",   unit: "kcal", targetKey: "energy_kcal" },
  { key: "protein_g",   label: "Protein",  unit: "g",    targetKey: "protein_g" },
  { key: "carbs_g",     label: "Carbs",    unit: "g",    targetKey: "carbs_g" },
  { key: "fat_g",       label: "Fat",      unit: "g",    targetKey: "fat_g" },
  { key: "fluid_ml",    label: "Fluid",    unit: "mL",   targetKey: "fluid_ml" },
] as const;

type MacroKey = typeof MACRO_FIELDS[number]["key"];

interface RevisionFormState {
  goal_type: string;
  disease_stage: string;
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  education_notes: string;
  counseling_goals: string;
  barriers: string;
  strategies: string;
  session_type: string;
  next_followup_date: string;
}

const fieldValue = (value: string | number | null | undefined) => value == null ? "" : String(value);

// ─── UnitInput — with optional target hint ────────────────────────────────────

function UnitInput({
  label,
  unit,
  value,
  onChange,
  type = "number",
  disabled = false,
  target,
}: {
  label: string;
  unit: string;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  disabled?: boolean;
  target?: string | null;
}) {
  return (
    <div>
      <div className="flex items-end justify-between mb-1.5">
        <label className="text-xs font-bold text-warm-400 uppercase tracking-widest">
          {label}
        </label>
        {target && target !== "" && (
          <span className="text-xs font-mono font-semibold text-emerald-600 tabular-nums">
            target: {target} {unit}
          </span>
        )}
      </div>
      <div className="flex items-center border border-warm-200 rounded-xl overflow-hidden focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all">
        <input
          type={type}
          step="0.1"
          min="0"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          disabled={disabled}
          className="w-full px-3.5 py-2.5 text-base font-mono text-warm-900 bg-transparent focus:outline-none placeholder:text-warm-400 disabled:text-warm-400"
        />
        <span className="px-2.5 text-xs font-bold text-warm-400 bg-warm-50 border-l border-warm-200 whitespace-nowrap select-none">
          {unit}
        </span>
      </div>
    </div>
  );
}

// ─── ToggleGroup ──────────────────────────────────────────────────────────────

function ToggleGroup<T extends string>({
  label,
  options,
  value,
  onChange,
}: {
  label: string;
  options: { value: T; label: string; active: string }[];
  value: T | null;
  onChange: (v: T | null) => void;
}) {
  return (
    <div>
      <p className="text-xs font-bold text-warm-400 uppercase tracking-widest mb-2">{label}</p>
      <div className="flex flex-wrap gap-2">
        {options.map((opt) => (
          <button
            key={opt.value}
            type="button"
            onClick={() => onChange(value === opt.value ? null : opt.value)}
            className={`flex-1 min-w-[80px] py-2.5 text-sm font-semibold rounded-xl border transition-all ${
              value === opt.value
                ? opt.active
                : "bg-white text-warm-500 border-warm-200 hover:border-warm-300 hover:bg-warm-50"
            }`}
          >
            {opt.label}
          </button>
        ))}
      </div>
    </div>
  );
}

// ─── Collapsible section wrapper ──────────────────────────────────────────────

function CollapsibleSection({
  title,
  subtitle,
  open,
  onOpenChange,
  children,
}: {
  title: string;
  subtitle?: string;
  open: boolean;
  onOpenChange: (v: boolean) => void;
  children: React.ReactNode;
}) {
  return (
    <Collapsible open={open} onOpenChange={onOpenChange}>
      <div className="border border-warm-200 rounded-xl overflow-hidden">
        <CollapsibleTrigger asChild>
          <button
            type="button"
            className="w-full flex items-center justify-between px-4 py-3 text-xs font-bold text-warm-400 uppercase tracking-widest hover:bg-warm-50 transition-colors"
          >
            <span>
              {title}{" "}
              {subtitle && (
                <span className="normal-case font-normal text-warm-300">({subtitle})</span>
              )}
            </span>
            {open
              ? <ChevronUp className="h-3.5 w-3.5 text-warm-400" />
              : <ChevronDown className="h-3.5 w-3.5 text-warm-400" />
            }
          </button>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <div className="px-4 pb-4 border-t border-warm-100 pt-4">
            {children}
          </div>
        </CollapsibleContent>
      </div>
    </Collapsible>
  );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function LogVisitForm({
  plan,
  heightCm,
  intervention,
  onSubmit,
  onCancel,
}: LogVisitFormProps) {

  // Lab fields: the patient's plan-tracked labs (intersected with fields the form
  // can render); fall back to goal_type defaults when no plan is available.
  const goalType = intervention?.goal_type ?? null;
  const knownLabKeys = Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[];
  const planLabKeys = plan
    ? (plan.indicators.filter((i) => i.category === "lab").map((i) => i.key) as ClinicalLabKey[])
        .filter((k) => knownLabKeys.includes(k))
    : null;
  const labKeys: ClinicalLabKey[] = planLabKeys && planLabKeys.length > 0
    ? planLabKeys
    : goalType
      ? (GOAL_LAB_FLAGS[goalType] ?? [])
      : knownLabKeys;

  // Derive which micro keys to show (deduped union of displayed_nutrients + flagged)
  const flaggedMicros: string[] = goalType ? (GOAL_MICRO_FLAGS[goalType] ?? []) : [];
  const displayedMicros: string[] = intervention?.displayed_nutrients ?? [];
  const microKeys = Array.from(new Set([...displayedMicros, ...flaggedMicros]));
  const microMeta = Object.fromEntries(ALL_MICROS.map((m) => [m.key, m]));

  // ─ Form state ───────────────────────────────────────────────────────────────
  const [weight, setWeight]         = useState("");
  const [bmi, setBmi]               = useState("");
  const [compliance, setCompliance] = useState<ComplianceStatus | null>(null);
  const [giTolerance, setGiTolerance] = useState<GiToleranceStatus | null>(null);
  const [decision, setDecision]     = useState<ContinuationDecision>(null);
  const [clinicalSummary, setClinicalSummary] = useState("");
  const [intakeNotes, setIntakeNotes] = useState("");
  const [symptoms, setSymptoms] = useState("");
  const [openIntervention, setOpenIntervention] = useState(false);

  const [macrosOpen, setMacrosOpen] = useState(true);
  const [labsOpen, setLabsOpen]     = useState(false);
  const [microsOpen, setMicrosOpen] = useState(false);
  const [revisionOpen, setRevisionOpen] = useState(false);
  const [revisionEffectiveDate, setRevisionEffectiveDate] = useState("");
  const [revisionReason, setRevisionReason] = useState("");
  const [revision, setRevision] = useState<RevisionFormState>(() => ({
    goal_type: intervention?.goal_type ?? "",
    disease_stage: intervention?.disease_stage ?? "",
    energy_kcal: fieldValue(intervention?.energy_kcal),
    protein_g: fieldValue(intervention?.protein_g),
    carbs_g: fieldValue(intervention?.carbs_g),
    fat_g: fieldValue(intervention?.fat_g),
    fluid_ml: fieldValue(intervention?.fluid_ml),
    education_notes: intervention?.education_notes ?? "",
    counseling_goals: intervention?.counseling_goals ?? "",
    barriers: intervention?.barriers ?? "",
    strategies: intervention?.strategies ?? "",
    session_type: intervention?.session_type ?? "",
    next_followup_date: intervention?.next_followup_date ?? "",
  }));

  const [macros, setMacros] = useState<Record<MacroKey, string>>({
    energy_kcal: "",
    protein_g: "",
    carbs_g: "",
    fat_g: "",
    fluid_ml: "",
  });

  const [labs, setLabs] = useState<Record<ClinicalLabKey, string>>(
    Object.fromEntries(
      (Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[]).map((k) => [k, ""])
    ) as Record<ClinicalLabKey, string>
  );

  const [micros, setMicros] = useState<Record<string, string>>(
    Object.fromEntries(microKeys.map((k) => [k, ""]))
  );

  const [submitting, setSubmitting] = useState(false);
  const [error, setError]           = useState<string | null>(null);

  function handleWeightChange(val: string) {
    setWeight(val);
    const w = parseFloat(val);
    if (!isNaN(w) && w > 0 && heightCm && heightCm > 0) {
      setBmi(String(calculateBmi(w, heightCm)));
    } else {
      setBmi("");
    }
  }

  function buildPayload(): MonitoringPayload {
    // Merge all numeric values into lab_values
    const labValues: Record<string, number | string | null> = {};

    // Clinical labs
    labKeys.forEach((key) => {
      const v = labs[key]?.trim() ?? "";
      if (v !== "") {
        labValues[key] = key === "bp" ? v : parseFloat(v);
      }
    });

    // Macro intake
    MACRO_FIELDS.forEach(({ key }) => {
      const v = macros[key].trim();
      if (v !== "") labValues[key] = parseFloat(v);
    });

    // Micro intake — stored as micro_{key} to avoid conflicts with clinical labs
    microKeys.forEach((key) => {
      const v = (micros[key] ?? "").trim();
      if (v !== "") labValues[`micro_${key}`] = parseFloat(v);
    });

    const goalAchievement: Record<string, string> = {};
    if (compliance) goalAchievement.compliance = compliance;
    if (giTolerance) goalAchievement.gi_tolerance = giTolerance;
    if (decision)    goalAchievement.continuation_decision = decision;

    const payload: MonitoringPayload = {
      weight:               weight ? parseFloat(weight) : null,
      bmi:                  bmi ? parseFloat(bmi) : null,
      lab_values:           Object.keys(labValues).length > 0
                              ? (labValues as MonitoringPayload["lab_values"])
                              : null,
      clinical_summary:     clinicalSummary.trim() || null,
      intake_notes:         intakeNotes.trim() || null,
      symptoms:             symptoms.trim() || null,
      goal_achievement:     Object.keys(goalAchievement).length > 0 ? goalAchievement : null,
    };

    if (revisionOpen && intervention) {
      payload.intervention_revision = buildInterventionRevisionPayload(intervention, {
        effectiveDate: revisionEffectiveDate,
        reason: revisionReason,
        changes: {
          goal_type: revision.goal_type,
          disease_stage: revision.disease_stage || null,
          energy_kcal: Number(revision.energy_kcal),
          protein_g: Number(revision.protein_g),
          carbs_g: Number(revision.carbs_g),
          fat_g: Number(revision.fat_g),
          fluid_ml: revision.fluid_ml ? Number(revision.fluid_ml) : null,
          education_notes: revision.education_notes || null,
          counseling_goals: revision.counseling_goals || null,
          barriers: revision.barriers || null,
          strategies: revision.strategies || null,
          session_type: revision.session_type || null,
          next_followup_date: revision.next_followup_date || null,
        },
      });
    }

    return payload;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await onSubmit(buildPayload(), { openIntervention });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to save visit.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="bg-white border border-warm-200 rounded-2xl shadow-sm overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-5 py-4 border-b border-warm-100">
        <h3 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">
          Log Follow-up Visit
        </h3>
        <button
          onClick={onCancel}
          type="button"
          className="text-warm-400 hover:text-warm-600 transition-colors p-1 rounded-lg hover:bg-warm-100"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <form onSubmit={handleSubmit} className="px-5 py-5 space-y-5">

        <h4 className="text-xs font-extrabold text-warm-500 uppercase tracking-widest">
          Follow-up Assessment
        </h4>

        {/* ── Weight + BMI ─────────────────────────────────────────────────── */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <UnitInput
            label="Weight"
            unit="kg"
            value={weight}
            onChange={handleWeightChange}
          />
          <UnitInput
            label={heightCm ? "BMI (auto-calculated)" : "BMI"}
            unit="kg/m²"
            value={bmi}
            onChange={setBmi}
            disabled={!!heightCm && !!weight}
          />
        </div>

        {/* ── Diet Compliance ──────────────────────────────────────────────── */}
        <ToggleGroup
          label="Diet Compliance"
          options={COMPLIANCE_OPTIONS}
          value={compliance}
          onChange={setCompliance}
        />

        {/* ── GI Tolerance ─────────────────────────────────────────────────── */}
        <ToggleGroup
          label="GI Tolerance"
          options={GI_OPTIONS}
          value={giTolerance}
          onChange={(v) => setGiTolerance(v as GiToleranceStatus | null)}
        />

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label className="text-xs font-bold text-warm-400 uppercase tracking-widest">
            Intake Notes
            <textarea
              value={intakeNotes}
              onChange={(event) => setIntakeNotes(event.target.value)}
              rows={3}
              className="mt-1.5 w-full px-3.5 py-2.5 text-base font-normal normal-case tracking-normal border border-warm-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white resize-none"
            />
          </label>
          <label className="text-xs font-bold text-warm-400 uppercase tracking-widest">
            Symptoms
            <textarea
              value={symptoms}
              onChange={(event) => setSymptoms(event.target.value)}
              rows={3}
              className="mt-1.5 w-full px-3.5 py-2.5 text-base font-normal normal-case tracking-normal border border-warm-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white resize-none"
            />
          </label>
        </div>

        {/* ── Care Decision ────────────────────────────────────────────────── */}
        <ToggleGroup<NonNullDecision>
          label="Care Decision"
          options={DECISION_OPTIONS}
          value={decision}
          onChange={(v) => setDecision(v)}
        />

        {/* ── Macronutrient Intake ─────────────────────────────────────────── */}
        <CollapsibleSection
          title="Actual Intake"
          subtitle="macronutrients"
          open={macrosOpen}
          onOpenChange={setMacrosOpen}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {MACRO_FIELDS.map(({ key, label, unit, targetKey }) => {
              const target = intervention
                ? (intervention[targetKey as keyof Intervention] as string | null)
                : null;
              return (
                <UnitInput
                  key={key}
                  label={label}
                  unit={unit}
                  value={macros[key]}
                  onChange={(v) => setMacros((prev) => ({ ...prev, [key]: v }))}
                  target={target}
                />
              );
            })}
          </div>
        </CollapsibleSection>

        {/* ── Clinical Labs ────────────────────────────────────────────────── */}
        {labKeys.length > 0 && (
          <CollapsibleSection
            title="Lab Results"
            subtitle="optional"
            open={labsOpen}
            onOpenChange={setLabsOpen}
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {labKeys.map((key) => {
                const meta = CLINICAL_LAB_META[key];
                return (
                  <UnitInput
                    key={key}
                    label={meta.label}
                    unit={meta.unit}
                    type={meta.type}
                    value={labs[key]}
                    onChange={(v) => setLabs((prev) => ({ ...prev, [key]: v }))}
                  />
                );
              })}
            </div>
          </CollapsibleSection>
        )}

        {/* ── Micronutrient Intake ─────────────────────────────────────────── */}
        {microKeys.length > 0 && (
          <CollapsibleSection
            title="Micronutrient Intake"
            subtitle="optional"
            open={microsOpen}
            onOpenChange={setMicrosOpen}
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {microKeys.map((key) => {
                const meta = microMeta[key];
                const limits = intervention?.micronutrient_limits?.[key];
                // Show target as max if available, min otherwise
                const limitStr = limits?.max != null
                  ? `≤ ${limits.max}`
                  : limits?.min != null
                    ? `≥ ${limits.min}`
                    : null;
                return (
                  <UnitInput
                    key={key}
                    label={meta?.label ?? key}
                    unit={meta?.unit ?? ""}
                    value={micros[key] ?? ""}
                    onChange={(v) => setMicros((prev) => ({ ...prev, [key]: v }))}
                    target={limitStr}
                  />
                );
              })}
            </div>
            <p className="text-xs text-warm-300 mt-3">
              Target values from the nutrition prescription.
            </p>
          </CollapsibleSection>
        )}

        {/* ── Progress Assessment ──────────────────────────────────────────── */}
        <div>
          <label className="block text-xs font-bold text-warm-400 uppercase tracking-widest mb-1.5">
            Progress Assessment
          </label>
          <textarea
            value={clinicalSummary}
            onChange={(e) => setClinicalSummary(e.target.value)}
            rows={3}
            className="w-full px-3.5 py-2.5 text-base border border-warm-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white transition-all placeholder:text-warm-400 resize-none"
          />
        </div>

        {intervention && (
          <CollapsibleSection
            title="Update care plan"
            subtitle="optional"
            open={revisionOpen}
            onOpenChange={setRevisionOpen}
          >
            <div className="space-y-4">
              <InfoHint label="How care plan updates are saved" title="Care plan history">
                Use this only when treatment changes. Saving creates a new version and keeps the previous care plan read-only.
              </InfoHint>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label className="text-sm font-semibold text-warm-600">
                  Effective date
                  <input
                    type="date"
                    required={revisionOpen}
                    value={revisionEffectiveDate}
                    onChange={(event) => setRevisionEffectiveDate(event.target.value)}
                    className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base"
                  />
                </label>
                <label className="text-sm font-semibold text-warm-600">
                  Clinical reason
                  <input
                    type="text"
                    required={revisionOpen}
                    maxLength={500}
                    value={revisionReason}
                    onChange={(event) => setRevisionReason(event.target.value)}
                    className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base"
                  />
                </label>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label className="text-sm font-semibold text-warm-600">
                  Goal
                  <select
                    required={revisionOpen}
                    value={revision.goal_type}
                    onChange={(event) => setRevision((current) => ({
                      ...current,
                      goal_type: event.target.value,
                      disease_stage: "",
                    }))}
                    className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base"
                  >
                    <option value="">Select goal</option>
                    {GOALS.map((goal) => <option key={goal.value} value={goal.value}>{goal.label}</option>)}
                  </select>
                </label>
                {visibleStagesForGoal(revision.goal_type) && (
                  <label className="text-sm font-semibold text-warm-600">
                    Stage
                    <select
                      required={revisionOpen}
                      value={revision.disease_stage}
                      onChange={(event) => setRevision((current) => ({ ...current, disease_stage: event.target.value }))}
                      className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base"
                    >
                      <option value="">Select stage</option>
                      {visibleStagesForGoal(revision.goal_type)?.map((stage) => (
                        <option key={stage.value} value={stage.value}>{stage.label}</option>
                      ))}
                    </select>
                  </label>
                )}
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {([
                  ["energy_kcal", "Energy", "kcal"],
                  ["protein_g", "Protein", "g"],
                  ["carbs_g", "Carbohydrate", "g"],
                  ["fat_g", "Fat", "g"],
                  ["fluid_ml", "Fluid guidance", "mL"],
                ] as const).map(([key, label, unit]) => (
                  <UnitInput
                    key={key}
                    label={label}
                    unit={unit}
                    value={revision[key]}
                    onChange={(value) => setRevision((current) => ({ ...current, [key]: value }))}
                  />
                ))}
              </div>

              {([
                ["education_notes", "Education"],
                ["counseling_goals", "Counseling"],
                ["barriers", "Barriers"],
                ["strategies", "Strategies"],
              ] as const).map(([key, label]) => (
                <label key={key} className="block text-sm font-semibold text-warm-600">
                  {label}
                  <textarea
                    rows={2}
                    value={revision[key]}
                    onChange={(event) => setRevision((current) => ({ ...current, [key]: event.target.value }))}
                    className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base resize-y"
                  />
                </label>
              ))}

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label className="text-sm font-semibold text-warm-600">
                  Session type
                  <input
                    type="text"
                    value={revision.session_type}
                    onChange={(event) => setRevision((current) => ({ ...current, session_type: event.target.value }))}
                    className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base"
                  />
                </label>
                <label className="text-sm font-semibold text-warm-600">
                  Next follow-up
                  <input
                    type="date"
                    value={revision.next_followup_date}
                    onChange={(event) => setRevision((current) => ({ ...current, next_followup_date: event.target.value }))}
                    className="mt-1.5 w-full px-3.5 py-2.5 border border-warm-200 rounded-xl bg-white text-base"
                  />
                </label>
              </div>
            </div>
          </CollapsibleSection>
        )}

        <label className="flex min-h-11 items-center gap-2 text-sm font-semibold text-warm-600">
          <input
            type="checkbox"
            checked={openIntervention}
            onChange={(event) => setOpenIntervention(event.target.checked)}
            className="h-4 w-4 accent-emerald-600"
          />
          Open Intervention after saving to review or create a meal plan
        </label>

        {/* ── Next Follow-up Date ──────────────────────────────────────────── */}

        {/* ── Error ────────────────────────────────────────────────────────── */}
        {error && (
          <div className="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-xl">
            <p className="text-sm text-red-700">{error}</p>
          </div>
        )}

        {/* ── Actions ──────────────────────────────────────────────────────── */}
        <div className="flex flex-col sm:flex-row gap-3 pt-1">
          <Button type="submit" variant="primary" loading={submitting} className="sm:flex-1">
            Save Visit
          </Button>
          <Button type="button" variant="ghost" onClick={onCancel} className="sm:!w-auto">
            Cancel
          </Button>
        </div>
      </form>
    </div>
  );
}
