import {
  CLINICAL_LAB_META,
  type ClinicalLabKey,
  type MonitoringEntry,
  type MonitoringVisitType,
  type PregnancyLactationStatus,
} from "@/services/monitoringService";

interface MonitoringVisitDetailsProps {
  entry: MonitoringEntry;
  mode: "summary" | "full";
}

const VISIT_LABELS: Record<MonitoringVisitType, string> = {
  scheduled_follow_up: "Scheduled follow-up",
  inpatient_review: "Inpatient review",
  discharge_review: "Discharge review",
  unscheduled_follow_up: "Unscheduled follow-up",
};

const MATERNAL_LABELS: Record<PregnancyLactationStatus, string> = {
  none: "None",
  pregnant_t1: "Pregnant, first trimester",
  pregnant_t2: "Pregnant, second trimester",
  pregnant_t3: "Pregnant, third trimester",
  pregnant_unspecified: "Pregnant, trimester not recorded",
  lactating: "Lactating",
};

const INTAKE_META: Record<string, { label: string; unit: string }> = {
  energy_kcal: { label: "Energy intake", unit: "kcal" },
  protein_g: { label: "Protein intake", unit: "g" },
  carbs_g: { label: "Carbohydrate intake", unit: "g" },
  fat_g: { label: "Fat intake", unit: "g" },
  fluid_ml: { label: "Fluid intake", unit: "mL" },
};

function visitLabel(value: MonitoringVisitType | null): string {
  return value ? VISIT_LABELS[value] : "Follow-up";
}

function formatDate(value: string | null): string {
  if (!value) return "Not recorded";
  return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function titleCase(value: string | null | undefined): string {
  if (!value) return "Not recorded";
  return value.replaceAll("_", " ").replace(/\b\w/g, (character) => character.toUpperCase());
}

function hasValue(value: unknown): boolean {
  if (Array.isArray(value)) return value.length > 0;
  return value !== null && value !== undefined && value !== "";
}

function Fact({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs font-bold uppercase tracking-wider text-warm-400">{label}</dt>
      <dd className="mt-1 break-words text-sm font-semibold text-warm-800">{value}</dd>
    </div>
  );
}

function DetailSection({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="space-y-3 border-t border-warm-100 pt-4 first:border-t-0 first:pt-0">
      <h4 className="text-xs font-extrabold uppercase tracking-wider text-warm-600">{title}</h4>
      {children}
    </section>
  );
}

export default function MonitoringVisitDetails({ entry, mode }: MonitoringVisitDetailsProps) {
  const decision = entry.goal_achievement?.continuation_decision ?? null;
  const observedDate = entry.observed_at ?? entry.created_at;
  const allergies = entry.allergies ?? [];
  const foodDislikes = entry.food_dislikes ?? [];

  if (mode === "summary") {
    return (
      <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-left sm:grid-cols-4">
        <Fact label="Date" value={formatDate(observedDate)} />
        <Fact label="Visit type" value={visitLabel(entry.visit_type)} />
        <Fact label="Weight" value={entry.weight == null ? "Not recorded" : `${entry.weight} kg`} />
        <Fact label="Decision" value={titleCase(decision)} />
      </dl>
    );
  }

  const clinicalLabs = Object.entries(entry.lab_values ?? {}).filter(([key, value]) => (
    key in CLINICAL_LAB_META && hasValue(value)
  )) as [ClinicalLabKey, string | number][];
  const intakeValues = Object.entries(entry.lab_values ?? {}).filter(([key, value]) => (
    (key in INTAKE_META || key.startsWith("micro_")) && hasValue(value)
  ));
  const hasMealDetails = allergies.length > 0
    || hasValue(entry.dietary_restrictions)
    || foodDislikes.length > 0
    || intakeValues.length > 0
    || hasValue(entry.goal_achievement?.compliance)
    || hasValue(entry.goal_achievement?.gi_tolerance)
    || hasValue(entry.intake_notes)
    || hasValue(entry.symptoms);
  const hasClinicalDetails = hasValue(entry.clinical_summary)
    || hasValue(decision)
    || hasValue(entry.ai_decision);

  return (
    <div className="space-y-4">
      <DetailSection title="Visit Context">
        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Fact label="Observed date" value={formatDate(observedDate)} />
          <Fact label="Visit type" value={visitLabel(entry.visit_type)} />
        </dl>
      </DetailSection>

      <DetailSection title="Recalculation Measurements and Factors">
        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {entry.weight != null && <Fact label="Weight" value={`${entry.weight} kg`} />}
          {entry.height != null && <Fact label="Height" value={`${entry.height} cm`} />}
          {entry.bmi != null && <Fact label="BMI" value={entry.bmi} />}
          {entry.edema_present != null && <Fact label="Edema" value={entry.edema_present ? "Present" : "Not present"} />}
          {entry.edema_present && <Fact label="Dry weight" value={entry.dry_weight_kg == null ? "Not recorded" : `${entry.dry_weight_kg} kg`} />}
          {entry.physical_activity_level && <Fact label="Physical activity" value={titleCase(entry.physical_activity_level)} />}
          {entry.pregnancy_lactation_status && <Fact label="Pregnancy / lactation" value={MATERNAL_LABELS[entry.pregnancy_lactation_status]} />}
        </dl>
      </DetailSection>

      {clinicalLabs.length > 0 && (
        <DetailSection title="Goal-relevant Labs">
          <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {clinicalLabs.map(([key, value]) => (
              <Fact key={key} label={CLINICAL_LAB_META[key].label} value={`${value} ${CLINICAL_LAB_META[key].unit}`} />
            ))}
          </dl>
        </DetailSection>
      )}

      {hasMealDetails && (
        <DetailSection title="Meal Safety, Intake, and Tolerance">
          <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {allergies.length > 0 && <Fact label="Allergies" value={allergies.join(", ")} />}
            {hasValue(entry.dietary_restrictions) && <Fact label="Dietary restrictions" value={entry.dietary_restrictions} />}
            {foodDislikes.length > 0 && <Fact label="Food dislikes" value={foodDislikes.join(", ")} />}
            {intakeValues.map(([key, value]) => {
              const meta = INTAKE_META[key];
              const label = meta?.label ?? `${titleCase(key.replace(/^micro_/, ""))} intake`;
              return <Fact key={key} label={label} value={`${value}${meta ? ` ${meta.unit}` : ""}`} />;
            })}
            {hasValue(entry.goal_achievement?.compliance) && <Fact label="Diet compliance" value={titleCase(entry.goal_achievement?.compliance)} />}
            {hasValue(entry.goal_achievement?.gi_tolerance) && <Fact label="GI tolerance" value={titleCase(entry.goal_achievement?.gi_tolerance)} />}
            {hasValue(entry.intake_notes) && <Fact label="Intake notes" value={entry.intake_notes} />}
            {hasValue(entry.symptoms) && <Fact label="Symptoms and tolerance" value={entry.symptoms} />}
          </dl>
        </DetailSection>
      )}

      {hasClinicalDetails && (
        <DetailSection title="Clinical Progress and Decision">
          <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {hasValue(entry.clinical_summary) && <Fact label="Progress assessment" value={entry.clinical_summary} />}
            {hasValue(decision) && <Fact label="Care decision" value={titleCase(decision)} />}
            {hasValue(entry.ai_decision) && <Fact label="Clinical review" value={entry.ai_decision} />}
          </dl>
        </DetailSection>
      )}

      {entry.next_monitoring_date && (
        <DetailSection title="Follow-up">
          <dl><Fact label="Next monitoring date" value={formatDate(entry.next_monitoring_date)} /></dl>
        </DetailSection>
      )}
    </div>
  );
}
