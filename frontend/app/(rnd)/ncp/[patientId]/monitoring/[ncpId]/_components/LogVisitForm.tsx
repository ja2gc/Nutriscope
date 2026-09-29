"use client";

import { useMemo, useState } from "react";

import { Button } from "@/components/ui/Button";
import { InfoHint } from "@/components/ui/InfoHint";
import { GOAL_MICRO_FLAGS, ALL_MICROS } from "@/lib/nutritionCalculations";
import type { Intervention } from "@/services/interventionService";
import type { MonitoringPlan } from "@/services/monitoringPlan";
import {
  CLINICAL_LAB_META,
  GOAL_LAB_FLAGS,
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
  plan?: MonitoringPlan | null;
  context: MonitoringContext;
  intervention: Intervention | null;
  onSubmit: (payload: MonitoringPayload) => Promise<void>;
  onCancel: () => void;
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

const MACRO_FIELDS = [
  { key: "energy_kcal", label: "Energy", unit: "kcal" },
  { key: "protein_g", label: "Protein", unit: "g" },
  { key: "carbs_g", label: "Carbohydrate", unit: "g" },
  { key: "fat_g", label: "Fat", unit: "g" },
  { key: "fluid_ml", label: "Fluid", unit: "mL" },
] as const;

type MacroKey = typeof MACRO_FIELDS[number]["key"];
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

function parseList(value: string): string[] {
  return value
    .split(/[,\n]/)
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

function NumberField({ label, unit, value, onChange, required = false, readOnly = false, target }: {
  label: string;
  unit: string;
  value: string;
  onChange: (value: string) => void;
  required?: boolean;
  readOnly?: boolean;
  target?: string | number | null;
}) {
  return (
    <label>
      <span className="mb-1.5 flex items-end justify-between gap-2">
        <span className="text-xs font-bold uppercase tracking-widest text-warm-500">{label}</span>
        {target != null && target !== "" && <span className="text-xs font-semibold text-warm-500">Target {target} {unit}</span>}
      </span>
      <span className="flex overflow-hidden rounded-lg border border-warm-200 bg-white focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-500/20">
        <input
          type="number"
          step="0.1"
          min="0"
          required={required}
          readOnly={readOnly}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="min-w-0 flex-1 bg-transparent px-3 py-2.5 font-mono text-base text-warm-900 outline-none read-only:bg-warm-50 read-only:text-warm-600"
        />
        <span className="flex items-center border-l border-warm-200 bg-warm-50 px-2.5 text-xs font-bold text-warm-500">{unit}</span>
      </span>
    </label>
  );
}

function ToggleGroup<T extends string>({ label, options, value, onChange }: {
  label: string;
  options: { value: T; label: string }[];
  value: T | null;
  onChange: (value: T | null) => void;
}) {
  return (
    <div>
      <FieldLabel>{label}</FieldLabel>
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

export default function LogVisitForm({ plan, context, intervention, onSubmit, onCancel }: LogVisitFormProps) {
  const goalType = intervention?.goal_type ?? null;
  const knownLabKeys = Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[];
  const planLabKeys = plan
    ? (plan.indicators.filter((indicator) => indicator.category === "lab").map((indicator) => indicator.key) as ClinicalLabKey[])
        .filter((key) => knownLabKeys.includes(key))
    : [];
  const labKeys = planLabKeys.length > 0 ? planLabKeys : goalType ? GOAL_LAB_FLAGS[goalType] ?? [] : knownLabKeys;

  const microKeys = useMemo(() => {
    const flagged = goalType ? GOAL_MICRO_FLAGS[goalType] ?? [] : [];
    return Array.from(new Set([...(intervention?.displayed_nutrients ?? []), ...flagged]));
  }, [goalType, intervention?.displayed_nutrients]);
  const microMeta = Object.fromEntries(ALL_MICROS.map((item) => [item.key, item]));

  const [observedAt, setObservedAt] = useState(todayValue);
  const [visitType, setVisitType] = useState<MonitoringVisitType>("scheduled_follow_up");
  const [weight, setWeight] = useState(fieldValue(context.weight));
  const [height, setHeight] = useState(fieldValue(context.height));
  const [edemaPresent, setEdemaPresent] = useState(context.edema_present);
  const [dryWeight, setDryWeight] = useState(fieldValue(context.dry_weight_kg));
  const [activityLevel, setActivityLevel] = useState(context.physical_activity_level ?? "");
  const [maternalStatus, setMaternalStatus] = useState<PregnancyLactationStatus>(context.pregnancy_lactation_status ?? "none");
  const [allergies, setAllergies] = useState(context.allergies.join(", "));
  const [dietaryRestrictions, setDietaryRestrictions] = useState(context.dietary_restrictions ?? "");
  const [foodDislikes, setFoodDislikes] = useState(context.food_dislikes.join(", "));
  const [compliance, setCompliance] = useState<ComplianceStatus | null>(null);
  const [giTolerance, setGiTolerance] = useState<GiToleranceStatus | null>(null);
  const [decision, setDecision] = useState<ContinuationDecision>(null);
  const [clinicalSummary, setClinicalSummary] = useState("");
  const [intakeNotes, setIntakeNotes] = useState("");
  const [symptoms, setSymptoms] = useState("");
  const [nextMonitoringDate, setNextMonitoringDate] = useState("");
  const [labs, setLabs] = useState<Record<ClinicalLabKey, string>>(
    Object.fromEntries(knownLabKeys.map((key) => [key, ""])) as Record<ClinicalLabKey, string>,
  );
  const [macros, setMacros] = useState<Record<MacroKey, string>>({ energy_kcal: "", protein_g: "", carbs_g: "", fat_g: "", fluid_ml: "" });
  const [micros, setMicros] = useState<Record<string, string>>(Object.fromEntries(microKeys.map((key) => [key, ""])));
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const bmi = Number(weight) > 0 && Number(height) > 0 ? String(calculateBmi(Number(weight), Number(height))) : "";

  function buildPayload(): MonitoringPayload {
    const labValues: Record<string, number | string | null> = {};
    labKeys.forEach((key) => {
      const value = labs[key]?.trim();
      if (value && key === "bp") {
        labValues.bp = value;
      } else if (value) {
        labValues[key] = Number(value);
      }
    });
    MACRO_FIELDS.forEach(({ key }) => {
      const value = macros[key].trim();
      if (value) labValues[key] = Number(value);
    });
    microKeys.forEach((key) => {
      const value = micros[key]?.trim();
      if (value) labValues[`micro_${key}`] = Number(value);
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
      next_monitoring_date: nextMonitoringDate || null,
    });
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await onSubmit(buildPayload());
    } catch (submissionError) {
      setError(submissionError instanceof Error ? submissionError.message : "Failed to save visit.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-warm-200 bg-white shadow-sm">
      <div className="flex items-center justify-between border-b border-warm-100 px-5 py-4">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-warm-700">Log Monitoring Visit</h3>
        <button type="button" onClick={onCancel} className="min-h-10 px-2 text-sm font-semibold text-warm-500 hover:text-warm-800">Close</button>
      </div>

      <form onSubmit={handleSubmit} className="space-y-5 px-4 py-5 sm:px-5">
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
                  <input type="text" value={labs[key]} onChange={(event) => setLabs((current) => ({ ...current, [key]: event.target.value }))} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
                </label>
              ) : (
                <NumberField key={key} label={meta.label} unit={meta.unit} value={labs[key]} onChange={(value) => setLabs((current) => ({ ...current, [key]: value }))} />
              );
            })}
          </div>
        </Section>

        <Section title="Meal Safety, Intake, and Tolerance">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label>
              <span className="mb-1.5 flex items-center gap-1.5">
                <span className="text-xs font-bold uppercase tracking-widest text-warm-500">Allergies</span>
                <InfoHint label="How allergies affect meal plans" title="Allergy exclusions">Allergies are hard exclusions in generated meal plans. Confirm this list before saving.</InfoHint>
              </span>
              <textarea value={allergies} onChange={(event) => setAllergies(event.target.value)} rows={2} className="w-full resize-y rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
            <label>
              <FieldLabel>Dietary Restrictions</FieldLabel>
              <textarea value={dietaryRestrictions} onChange={(event) => setDietaryRestrictions(event.target.value)} rows={2} className="w-full resize-y rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
            <label>
              <FieldLabel>Food Dislikes</FieldLabel>
              <textarea value={foodDislikes} onChange={(event) => setFoodDislikes(event.target.value)} rows={2} className="w-full resize-y rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {MACRO_FIELDS.map(({ key, label, unit }) => (
              <NumberField key={key} label={`${label} Intake`} unit={unit} value={macros[key]} onChange={(value) => setMacros((current) => ({ ...current, [key]: value }))} target={intervention?.[key] as string | number | null | undefined} />
            ))}
            {microKeys.map((key) => {
              const meta = microMeta[key];
              if (!meta) return null;
              const limit = intervention?.micronutrient_limits?.[key];
              return <NumberField key={key} label={`${meta.label} Intake`} unit={meta.unit} value={micros[key] ?? ""} onChange={(value) => setMicros((current) => ({ ...current, [key]: value }))} target={limit?.max ?? limit?.min ?? null} />;
            })}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <ToggleGroup label="Diet Compliance" options={COMPLIANCE_OPTIONS} value={compliance} onChange={setCompliance} />
            <ToggleGroup label="GI Tolerance" options={GI_OPTIONS} value={giTolerance} onChange={setGiTolerance} />
            <label>
              <FieldLabel>Intake Notes</FieldLabel>
              <textarea value={intakeNotes} onChange={(event) => setIntakeNotes(event.target.value)} rows={3} className="w-full resize-y rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
            <label>
              <FieldLabel>Symptoms and Tolerance</FieldLabel>
              <textarea value={symptoms} onChange={(event) => setSymptoms(event.target.value)} rows={3} className="w-full resize-y rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
            </label>
          </div>
        </Section>

        <Section title="Clinical Progress and Decision">
          <label>
            <FieldLabel>Progress Assessment</FieldLabel>
            <textarea value={clinicalSummary} onChange={(event) => setClinicalSummary(event.target.value)} rows={3} className="w-full resize-y rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
          </label>
          <ToggleGroup<NonNullDecision> label="Care Decision" options={DECISION_OPTIONS} value={decision} onChange={setDecision} />
        </Section>

        <Section title="Follow-up">
          <label className="block max-w-sm">
            <FieldLabel>Next Monitoring Date</FieldLabel>
            <input type="date" min={observedAt} value={nextMonitoringDate} onChange={(event) => setNextMonitoringDate(event.target.value)} className="w-full rounded-lg border border-warm-200 bg-white px-3 py-2.5 text-base text-warm-900" />
          </label>
        </Section>

        {error && <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
        <div className="flex flex-col gap-3 sm:flex-row">
          <Button type="submit" variant="primary" loading={submitting} className="sm:flex-1">Save Visit</Button>
          <Button type="button" variant="ghost" onClick={onCancel} className="sm:!w-auto">Cancel</Button>
        </div>
      </form>
    </div>
  );
}
