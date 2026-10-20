"use client";

import { useEffect, useImperativeHandle, useRef, useState, type Ref } from "react";
import { useEditorChanges, type EditorLeaveHandle } from "@/hooks/useUnsavedChanges";

import { Button } from "@/components/ui/Button";
import { numericStepBase } from "@/components/ui/NumericInput";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import { CharacterCountListInput } from "@/components/ui/CharacterCountListInput";
import { ASSESSMENT_TEXT_LIMITS, FOOD_DISLIKE_LIMIT, FOOD_DISLIKE_MAX_ENTRIES } from "@/lib/assessmentValidation";
import { ALLERGY_ENTRY_LIMIT, MONITORING_TEXT_LIMITS, monitoringTextError } from "@/lib/monitoringValidation";
import { InfoHint } from "@/components/ui/InfoHint";
import type { Intervention } from "@/services/interventionService";
import type { MonitoringPlan } from "@/services/monitoringPlan";
import {
  CLINICAL_LAB_META,
  fetchMonitoringAiBudget,
  requestMonitoringAiReview,
  type AiSummaryBudget,
  buildEffectiveMonitoringPayload,
  calculateBmi,
  type ClinicalLabKey,
  type ComplianceStatus,
  type ContinuationDecision,
  type GiToleranceStatus,
  type MonitoringContext,
  type MonitoringLabValues,
  type MonitoringPayload,
  type MonitoringVisitType,
  type PregnancyLactationStatus,
} from "@/services/monitoringService";

interface LogVisitFormProps {
  ncpId?: string;
  plan?: MonitoringPlan | null;
  context: MonitoringContext;
  intervention: Intervention | null;
  onSubmit: (payload: MonitoringPayload) => Promise<void>;
  onCancel: () => void;
  leaveRef?: Ref<EditorLeaveHandle>;
}

const VISIT_TYPES: { value: MonitoringVisitType; label: string }[] = [
  { value: "scheduled_follow_up", label: "Scheduled follow-up" },
  { value: "inpatient_review", label: "Inpatient review" },
  { value: "discharge_review", label: "Discharge review" },
  { value: "unscheduled_follow_up", label: "Unscheduled follow-up" },
];

const ACTIVITY_LEVELS = [
  { value: "sedentary", label: "Sedentary" },
  { value: "light", label: "Light" },
  { value: "moderate", label: "Moderate" },
  { value: "very_active", label: "Very active" },
  { value: "extra_active", label: "Extra active" },
];

const MATERNAL_STATUSES: { value: PregnancyLactationStatus; label: string }[] = [
  { value: "none", label: "None" },
  { value: "pregnant_t1", label: "Pregnant, first trimester" },
  { value: "pregnant_t2", label: "Pregnant, second trimester" },
  { value: "pregnant_t3", label: "Pregnant, third trimester" },
  { value: "pregnant_unspecified", label: "Pregnant, trimester not recorded" },
  { value: "lactating", label: "Lactating" },
];

type NonNullDecision = NonNullable<ContinuationDecision>;

const COMPLIANCE_OPTIONS: { value: ComplianceStatus; label: string }[] = [
  { value: "compliant", label: "Compliant" },
  { value: "partial", label: "Partial" },
  { value: "non_compliant", label: "Non-compliant" },
];

const GI_OPTIONS: { value: GiToleranceStatus; label: string }[] = [
  { value: "tolerating", label: "Tolerating" },
  { value: "not_tolerating", label: "Not tolerating" },
];

const DECISION_OPTIONS: { value: NonNullDecision; label: string }[] = [
  { value: "continue", label: "Continue" },
  { value: "modify", label: "Modify" },
  { value: "discontinue", label: "Discontinue" },
];

function todayValue(): string {
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 10);
}

function fieldValue(value: string | number | null | undefined): string {
  return value == null ? "" : String(value);
}

function parseList(value: string[]): string[] {
  return value
    .map((item) => item.trim())
    .filter(Boolean);
}

function optionalNumber(value: string): number | null {
  return value.trim() === "" ? null : Number(value);
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <fieldset className="space-y-4 rounded-xl border border-warm-200 p-4">
      <legend className="px-1 text-sm font-extrabold text-warm-700">{title}</legend>
      {children}
    </fieldset>
  );
}

function FieldLabel({ children }: { children: React.ReactNode }) {
  return <span className="mb-1.5 block text-xs font-bold uppercase tracking-widest text-warm-500">{children}</span>;
}

function NumberField({ label, unit, value, onChange, required = false, readOnly = false, target, reference }: {
  label: string;
  unit: string;
  value: string;
  onChange: (value: string) => void;
  required?: boolean;
  readOnly?: boolean;
  target?: string | number | null;
  reference?: { min?: number | null; max?: number | null } | null;
}) {
  const targetText = target != null && target !== "" ? `${target} ${unit}`
    : reference?.min != null && reference?.max != null ? `${reference.min}–${reference.max} ${unit}`
    : reference?.min != null ? `≥ ${reference.min} ${unit}`
    : reference?.max != null ? `≤ ${reference.max} ${unit}` : null;
  return (
    <label>
      <span className="mb-1.5 flex flex-wrap items-end justify-between gap-2">
        <span className="text-xs font-bold uppercase tracking-widest text-warm-500">{label}</span>
        {targetText && <span className="text-xs font-semibold text-warm-500">{reference ? 'Limit' : 'Target'} {targetText}</span>}
      </span>
      <span className="flex items-center gap-2">
        <input
          type="number"
          step="0.1"
          min={numericStepBase(value)}
          required={required}
          readOnly={readOnly}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="min-w-0 w-full flex-1 rounded-lg border border-warm-200 bg-white px-3 py-2.5 font-mono text-base text-warm-900 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 read-only:bg-warm-50 read-only:text-warm-600"
        />
        {unit && <span className="shrink-0 text-xs text-warm-400">{unit}</span>}
      </span>
    </label>
  );
}

function ToggleGroup<T extends string>({ label, options, value, onChange, required = false }: {
  label: string;
  options: { value: T; label: string }[];
  value: T | null;
  onChange: (value: T | null) => void;
  required?: boolean;
}) {
  return (
    <div role="group" aria-label={label}>
      <FieldLabel>{label}{required && <span aria-hidden="true"> *</span>}</FieldLabel>
      <div className="flex flex-wrap gap-2">
        {options.map((option) => (
          <button
            key={option.value}
            type="button"
            aria-pressed={value === option.value}
            onClick={() => onChange(value === option.value ? null : option.value)}
            className={`min-h-10 flex-1 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors ${
              value === option.value
                ? "border-warm-800 bg-warm-100 text-warm-900"
                : "border-warm-200 bg-white text-warm-600 hover:bg-warm-50"
            }`}
          >
            {option.label}
          </button>
        ))}
      </div>
    </div>
  );
}

export default function LogVisitForm({ ncpId, plan, context, onSubmit, onCancel, leaveRef }: LogVisitFormProps) {
  const knownLabKeys = Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[];
  const planLabKeys = plan
    ? (plan.indicators.filter((indicator) => indicator.category === "lab").map((indicator) => indicator.key) as ClinicalLabKey[])
        .filter((key) => knownLabKeys.includes(key))
    : [];
  const labKeys = planLabKeys;
  const intakeFields = plan?.indicators.filter((indicator) => indicator.category === "intake") ?? [];

  const [observedAt, setObservedAt] = useState(todayValue);
  const [visitType, setVisitType] = useState<MonitoringVisitType>("scheduled_follow_up");
  const [weight, setWeight] = useState(fieldValue(context.weight));
  const [height, setHeight] = useState(fieldValue(context.height));
  const [edemaPresent, setEdemaPresent] = useState(context.edema_present);
  const [dryWeight, setDryWeight] = useState(fieldValue(context.dry_weight_kg));
  const [activityLevel, setActivityLevel] = useState(context.physical_activity_level ?? "");
  const [maternalStatus, setMaternalStatus] = useState<PregnancyLactationStatus>(context.pregnancy_lactation_status ?? "none");
  const [allergies, setAllergies] = useState(context.allergies ?? []);
  const [dietaryRestrictions, setDietaryRestrictions] = useState(context.dietary_restrictions ?? "");
  const [foodDislikes, setFoodDislikes] = useState(context.food_dislikes ?? []);
  const [compliance, setCompliance] = useState<ComplianceStatus | null>(null);
  const [giTolerance, setGiTolerance] = useState<GiToleranceStatus | null>(null);
  const [decision, setDecision] = useState<ContinuationDecision>(null);
  const [clinicalSummary, setClinicalSummary] = useState("");
  const [intakeNotes, setIntakeNotes] = useState("");
  const [symptoms, setSymptoms] = useState("");
  const [nextMonitoringAt, setNextMonitoringAt] = useState("");
  const [nextMonitoringPurpose, setNextMonitoringPurpose] = useState("");
  const [labs, setLabs] = useState<Record<ClinicalLabKey, string>>(
    Object.fromEntries(knownLabKeys.map((key) => [key, ""])) as Record<ClinicalLabKey, string>,
  );
  const [intakes, setIntakes] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [budget, setBudget] = useState<AiSummaryBudget | null>(null);
  const [aiLoading, setAiLoading] = useState(false);
  const [aiError, setAiError] = useState<string | null>(null);
  const [suggestion, setSuggestion] = useState<{ text: string; inputs: string } | null>(null);
  const [generatedFor, setGeneratedFor] = useState<string | null>(null);
  const latestInputs = useRef("");
  const latestText = useRef("");

  useEffect(() => {
    if (!ncpId) return;
    let active = true;
    fetchMonitoringAiBudget(ncpId).then((value) => { if (active) setBudget(value); })
      .catch((err) => { if (active) setAiError(err instanceof Error ? err.message : "Could not load AI allowance."); });
    return () => { active = false; };
  }, [ncpId]);

  const bmi = Number(weight) > 0 && Number(height) > 0 ? String(calculateBmi(Number(weight), Number(height))) : "";

  function buildPayload(): MonitoringPayload {
    const labValues: Record<string, number | string | null> = {};
    labKeys.forEach((key) => {
      const value = labs[key]?.trim();
      if (value && key === "bp") {
        labValues[key] = value;
      } else if (value) {
        labValues[key] = Number(value);
      }
    });
    intakeFields.forEach(({ key }) => {
      const value = intakes[key]?.trim();
      if (value) labValues[key] = Number(value);
    });

    const goalAchievement: Record<string, string> = {};
    if (compliance) goalAchievement.compliance = compliance;
    if (giTolerance) goalAchievement.gi_tolerance = giTolerance;
    if (decision) goalAchievement.continuation_decision = decision;

    return buildEffectiveMonitoringPayload(context, {
      observed_at: observedAt,
      visit_type: visitType,
      weight: Number(weight),
      height: Number(height),
      edema_present: edemaPresent,
      dry_weight_kg: edemaPresent ? optionalNumber(dryWeight) : null,
      physical_activity_level: activityLevel,
      pregnancy_lactation_status: maternalStatus,
      allergies: parseList(allergies),
      dietary_restrictions: dietaryRestrictions.trim() || null,
      food_dislikes: parseList(foodDislikes),
      lab_values: Object.keys(labValues).length > 0 ? labValues as MonitoringLabValues : null,
      intake_notes: intakeNotes.trim() || null,
      symptoms: symptoms.trim() || null,
      goal_achievement: Object.keys(goalAchievement).length > 0 ? goalAchievement : null,
      clinical_summary: clinicalSummary.trim() || null,
      next_monitoring_date: nextMonitoringAt ? nextMonitoringAt.slice(0, 10) : null,
      next_monitoring_at: nextMonitoringAt || null,
      next_monitoring_purpose: nextMonitoringAt ? nextMonitoringPurpose.trim() : null,
    });
  }

  const comparisonInputs = JSON.stringify({ ...buildPayload(), clinical_summary: undefined });
  useEffect(() => {
    latestInputs.current = comparisonInputs;
    latestText.current = clinicalSummary;
  }, [comparisonInputs, clinicalSummary]);

  async function generateSummary() {
    if (!ncpId || !budget || budget.remaining <= 0 || aiLoading) return;
    const payload = JSON.parse(comparisonInputs) as Omit<MonitoringPayload, "clinical_summary">;
    const validationError = monitoringTextError(payload);
    if (validationError) { setAiError(validationError); return; }
    setAiLoading(true);
    setAiError(null);
    setSuggestion(null);
    try {
      const result = await requestMonitoringAiReview(ncpId, { ...payload, entry_key: budget.entry_key });
      setBudget({ ...budget, remaining: result.remaining });
      if (latestInputs.current !== comparisonInputs) {
        setAiError(result.remaining > 0
          ? "Entry values changed during generation. Generate again using the current values."
          : "Entry values changed during generation. Write or review your summary manually; this entry has no generations left.");
      } else if (latestText.current.trim()) {
        setSuggestion({ text: result.narrative, inputs: comparisonInputs });
      } else {
        setClinicalSummary(result.narrative);
        setGeneratedFor(comparisonInputs);
      }
    } catch (err) {
      setAiError(err instanceof Error ? err.message : "Summary generation failed. You can write it manually.");
      fetchMonitoringAiBudget(ncpId).then(setBudget).catch(() => undefined);
    } finally {
      setAiLoading(false);
    }
  }

  const form = useRef<HTMLFormElement>(null);

  async function handleSubmit(event?: React.FormEvent) {
    event?.preventDefault();
    if (!event && !form.current?.reportValidity()) return false;
    setError(null);
    const payload = buildPayload();
    if (!decision) { setError("Care Decision is required."); return false; }
    if (!payload.clinical_summary?.trim()) { setError("Progress Assessment is required."); return false; }
    const validationError = monitoringTextError(payload);
    if (validationError) { setError(validationError); return false; }
    setSubmitting(true);
    try {
      await onSubmit(payload);
      return true;
    } catch (submissionError) {
      setError(submissionError instanceof Error ? submissionError.message : "Failed to save visit.");
      return false;
    } finally {
      setSubmitting(false);
    }
  }

  const guard = useEditorChanges(JSON.stringify({ observedAt, visitType, weight, height, edemaPresent, dryWeight, activityLevel, maternalStatus, allergies, dietaryRestrictions, foodDislikes, compliance, giTolerance, decision, clinicalSummary, intakeNotes, symptoms, nextMonitoringAt, nextMonitoringPurpose, labs, intakes }), true, () => handleSubmit());
  useImperativeHandle(leaveRef, () => ({ requestLeave: guard.requestLeave }), [guard.requestLeave]);

  return (
    <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
      {guard.dialog}
      <div className="flex items-center justify-between border-b border-warm-100 px-5 py-4">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Log Monitoring Visit</h3>
        <button type="button" onClick={() => guard.requestLeave(onCancel)} className="min-h-10 px-2 text-sm font-semibold text-warm-500 hover:text-warm-800">Close</button>
      </div>

      <form ref={form} onSubmit={handleSubmit} className="space-y-5 px-4 py-5 sm:px-5">
        <Section title="Visit Context">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label>
              <FieldLabel>Observed Date</FieldLabel>
              <input type="date" required max={todayValue()} value={observedAt} onChange={(event) => setObservedAt(event.target.value)} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
            <label>
              <FieldLabel>Visit Type</FieldLabel>
              <select required value={visitType} onChange={(event) => setVisitType(event.target.value as MonitoringVisitType)} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900">
                {VISIT_TYPES.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
            </label>
          </div>
        </Section>

        <Section title="Recalculation Measurements and Factors">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <NumberField label="Weight" unit="kg" value={weight} onChange={setWeight} required />
            <NumberField label="Height" unit="cm" value={height} onChange={setHeight} required />
            <NumberField label="BMI" unit="kg/m²" value={bmi} onChange={() => undefined} readOnly />
            <label>
              <FieldLabel>Edema</FieldLabel>
              <select value={edemaPresent ? "yes" : "no"} onChange={(event) => setEdemaPresent(event.target.value === "yes")} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900">
                <option value="no">Not present</option><option value="yes">Present</option>
              </select>
            </label>
            {edemaPresent && <NumberField label="Dry Weight" unit="kg" value={dryWeight} onChange={setDryWeight} required />}
            <label>
              <FieldLabel>Physical Activity</FieldLabel>
              <select required value={activityLevel} onChange={(event) => setActivityLevel(event.target.value)} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900">
                <option value="">Select activity level</option>
                {ACTIVITY_LEVELS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
            </label>
            <label>
              <FieldLabel>Pregnancy / Lactation</FieldLabel>
              <select required value={maternalStatus} onChange={(event) => setMaternalStatus(event.target.value as PregnancyLactationStatus)} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900">
                {MATERNAL_STATUSES.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </select>
            </label>
          </div>
        </Section>

        <Section title="Goal-relevant Labs">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {labKeys.map((key) => {
              const meta = CLINICAL_LAB_META[key];
              return meta.type === "text" ? (
                <label key={key}>
                  <FieldLabel>{meta.label}</FieldLabel>
                  <input type="text" maxLength={20} value={labs[key]} onChange={(event) => setLabs((current) => ({ ...current, [key]: event.target.value }))} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
                </label>
              ) : (
                <NumberField key={key} label={meta.label} unit={meta.unit} value={labs[key]} onChange={(value) => setLabs((current) => ({ ...current, [key]: value }))} />
              );
            })}
          </div>
        </Section>

        <Section title="Meal Safety, Intake, and Tolerance">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <span className="mb-1.5 flex items-center gap-1.5">
                <span className="text-xs font-bold uppercase tracking-widest text-warm-500">Allergies</span>
                <InfoHint label="How allergies affect meal plans" title="Allergy exclusions">Allergies are hard exclusions in generated meal plans. Confirm this list before saving.</InfoHint>
              </span>
              <CharacterCountListInput value={allergies} onChange={setAllergies} maxLength={ALLERGY_ENTRY_LIMIT} label="Allergy" />
            </div>
            <label>
              <FieldLabel>Dietary Restrictions</FieldLabel>
              <CharacterCountTextarea aria-label="Dietary Restrictions" autoGrow value={dietaryRestrictions} onChange={setDietaryRestrictions} maxLength={ASSESSMENT_TEXT_LIMITS.dietary_restrictions} rows={2} className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
            <div>
              <FieldLabel>Food Dislikes</FieldLabel>
              <CharacterCountListInput value={foodDislikes} onChange={setFoodDislikes} maxLength={FOOD_DISLIKE_LIMIT} maxEntries={FOOD_DISLIKE_MAX_ENTRIES} label="Food dislike" />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {intakeFields.map(({ key, label, unit, target, reference }) => (
              <NumberField key={key} label={label} unit={unit} value={intakes[key] ?? ""} onChange={(value) => setIntakes((current) => ({ ...current, [key]: value }))} target={target} reference={reference} />
            ))}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <ToggleGroup label="Diet Compliance" options={COMPLIANCE_OPTIONS} value={compliance} onChange={setCompliance} />
            <ToggleGroup label="GI Tolerance" options={GI_OPTIONS} value={giTolerance} onChange={setGiTolerance} />
            <label>
              <FieldLabel>Intake Notes</FieldLabel>
              <CharacterCountTextarea aria-label="Intake Notes" autoGrow value={intakeNotes} onChange={setIntakeNotes} maxLength={MONITORING_TEXT_LIMITS.intake_notes} rows={3} className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
            <label>
              <FieldLabel>Symptoms and Tolerance</FieldLabel>
              <CharacterCountTextarea aria-label="Symptoms and Tolerance" autoGrow value={symptoms} onChange={setSymptoms} maxLength={MONITORING_TEXT_LIMITS.symptoms} rows={3} className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
          </div>
        </Section>

        <Section title="Clinical Progress and Decision">
          <label>
            <FieldLabel>Progress Assessment *</FieldLabel>
            <CharacterCountTextarea aria-label="Progress Assessment" autoGrow required value={clinicalSummary} onChange={setClinicalSummary} maxLength={MONITORING_TEXT_LIMITS.clinical_summary} rows={3} className="w-full resize-none overflow-hidden rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
          </label>
          <div className="flex flex-wrap items-center gap-3">
            <Button type="button" variant="ghost" disabled={!budget || budget.remaining <= 0 || submitting} loading={aiLoading} onClick={generateSummary}>Generate AI Summary</Button>
            {budget && <span className="text-xs text-warm-500" role="status">{budget.remaining} generations left for this entry</span>}
          </div>
          {aiError && <p className="text-sm text-red-700" role="alert">{aiError}</p>}
          {suggestion && (
            <div className="space-y-3 rounded-lg border border-warm-200 p-3">
              <p className="text-sm text-warm-800">{suggestion.text}</p>
              <Button type="button" variant="ghost" disabled={suggestion.inputs !== comparisonInputs} onClick={() => { setClinicalSummary(suggestion.text); setGeneratedFor(suggestion.inputs); setSuggestion(null); }}>Use this summary</Button>
              {suggestion.inputs !== comparisonInputs && <p className="text-xs text-amber-800">Entry values changed. Generate again or edit your summary manually.</p>}
            </div>
          )}
          {generatedFor && generatedFor !== comparisonInputs && (
            <div className="space-y-2 text-sm text-amber-800" role="status">
              <p>Entry values changed. Review your summary or generate again.</p>
              <Button type="button" variant="ghost" onClick={() => setGeneratedFor(comparisonInputs)}>Mark reviewed</Button>
            </div>
          )}
          <ToggleGroup<NonNullDecision> label="Care Decision" required options={DECISION_OPTIONS} value={decision} onChange={setDecision} />
        </Section>

        <Section title="Follow-up">
          <div className="max-w-2xl">
            <FieldLabel>Schedule Follow-up</FieldLabel>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="text-xs font-bold text-warm-600">Date and time<input type="datetime-local" min={`${observedAt}T00:00`} value={nextMonitoringAt} onChange={(event) => setNextMonitoringAt(event.target.value)} className="mt-1 w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" /></label>
              <label className="text-xs font-bold text-warm-600">Purpose<input value={nextMonitoringPurpose} onChange={(event) => setNextMonitoringPurpose(event.target.value)} maxLength={255} required={Boolean(nextMonitoringAt)} disabled={!nextMonitoringAt} className="mt-1 w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900 disabled:bg-warm-50" /></label>
            </div>
          </div>
        </Section>

        {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        <div className="flex flex-col gap-3 sm:flex-row">
          <Button type="submit" variant="primary" loading={submitting} disabled={aiLoading} className="sm:flex-1">Save Visit</Button>
          <Button type="button" variant="ghost" onClick={() => guard.requestLeave(onCancel)} className="sm:!w-auto">Cancel</Button>
        </div>
      </form>
    </div>
  );
}
