import { ALL_MICROS, type PatientMetrics } from "./nutritionCalculations";
import type { PrescriptionFormState } from "./interventionGoalState";
import type { CalculationSnapshot } from "@/services/interventionService";

export type CalculationTargetStatus = "matches" | "modified" | "manual" | "missing";
export interface CalculationTraceValue { value?: number; unit?: string; text: string }
export interface CalculationContextItem { label: string; value: string; formulaName?: string }
export interface CalculationTargetRow {
  key: string; label: string; unit: string; value?: CalculationTraceValue;
  formulaName?: string; formula: string; calculation: string; status: CalculationTargetStatus;
}
export interface CalculationTrace {
  context: CalculationContextItem[]; targets: CalculationTargetRow[]; notes: string[];
}
export interface BuildPrescriptionCalculationTraceInput {
  goalType: string; stage: string | null; goalLabel?: string; stageLabel?: string;
  metrics?: PatientMetrics | null; prescription: PrescriptionFormState; requiredMicros?: string[];
  snapshot?: CalculationSnapshot | null;
  clinicalNotes?: string[];
}

const fields = [
  ["energy_kcal", "Energy", "kcal"], ["protein_g", "Protein", "g"],
  ["carbs_g", "Carbohydrates", "g"], ["fat_g", "Fat", "g"], ["fluid_ml", "Fluid", "mL"],
] as const;
const fmt = (n: number, decimals = 1) => n.toLocaleString("en-US", { maximumFractionDigits: decimals });
const numeric = (value: unknown): number | undefined => value === "" || value == null || !Number.isFinite(Number(value)) ? undefined : Number(value);

export function buildPrescriptionCalculationTrace(input: BuildPrescriptionCalculationTraceInput): CalculationTrace {
  const snapshot = input.snapshot ?? input.prescription.calculation_snapshot;
  const { prescription } = input;
  const context: CalculationContextItem[] = [
    { label: "Goal", value: input.goalLabel ?? input.goalType },
    ...(input.stageLabel ? [{ label: "Type", value: input.stageLabel }] : []),
  ];
  if (snapshot) {
    context.push(
      { label: "Method", value: snapshot.method },
      { label: "Measurement date", value: snapshot.measurement_date ?? "Not recorded" },
      { label: "Age", value: snapshot.inputs.ageYears == null ? "Not recorded" : `${fmt(snapshot.inputs.ageYears)} years` },
      ...(snapshot.inputs.sex ? [{ label: "Sex", value: snapshot.inputs.sex }] : []),
      { label: "Measured / dry weight", value: snapshot.inputs.weightKg == null ? "Not recorded" : `${fmt(snapshot.inputs.weightKg)} kg` },
      { label: "Height", value: snapshot.inputs.heightCm == null ? "Not recorded" : `${fmt(snapshot.inputs.heightCm)} cm` },
      { label: "Clinical weight basis", value: snapshot.weight_basis?.replaceAll("_", " ") ?? "Not recorded" },
    );
    if (snapshot.source) context.push({ label: "Reference", value: snapshot.source });
    if (snapshot.inputs.ageMonths != null && snapshot.inputs.ageYears != null && snapshot.inputs.ageYears < 3) context.push({ label: "Age in months", value: `${fmt(snapshot.inputs.ageMonths)} months` });
    if (snapshot.inputs.activityFactor != null && snapshot.baseline_kind === "estimated_maintenance") context.push({ label: "Activity factor", value: fmt(snapshot.inputs.activityFactor, 3) });
    if (snapshot.weight_kg != null && snapshot.weight_kg !== snapshot.inputs.weightKg) context.push({ label: "Calculation weight", value: `${fmt(snapshot.weight_kg)} kg` });
    if (snapshot.protein_weight_kg != null) context.push({ label: "Protein weight", value: `${fmt(snapshot.protein_weight_kg)} kg` });
    if (snapshot.protein_per_kg != null) context.push({ label: "Protein factor", value: `${fmt(snapshot.protein_per_kg, 3)} g/kg` });
    if (snapshot.energy_per_kg != null) context.push({ label: "Energy factor", value: `${fmt(snapshot.energy_per_kg, 3)} kcal/kg` });
    if (snapshot.protein_fraction != null) context.push({ label: "Protein share", value: `${fmt(snapshot.protein_fraction * 100)}%` });
    if (snapshot.fat_fraction != null) context.push({ label: "Fat share", value: `${fmt(snapshot.fat_fraction * 100)}%` });
    if (snapshot.growth_energy_kcal != null) context.push({ label: "Growth allowance", value: `${fmt(snapshot.growth_energy_kcal, 0)} kcal` });
    if (snapshot.inputs.pregnancyLactationStatus && snapshot.inputs.pregnancyLactationStatus !== 'none') context.push({ label: "Maternal status", value: snapshot.inputs.pregnancyLactationStatus.replaceAll('_', ' ') });
    if (snapshot.maternal_energy_kcal) context.push({ label: "Maternal energy", value: `${fmt(snapshot.maternal_energy_kcal, 0)} kcal` });
    if (snapshot.maternal_protein_g) context.push({ label: "Maternal protein", value: `${fmt(snapshot.maternal_protein_g)} g` });
    if (snapshot.pediatric_activity_category) context.push({ label: "Activity category", value: snapshot.pediatric_activity_category.replaceAll("_", " ") + " (assessment proxy; review)" });
    if (snapshot.baseline_energy_kcal != null) context.push({
      label: snapshot.baseline_kind === "clinical_target" ? "Baseline clinical target"
        : snapshot.baseline_kind === "pediatric_eer" ? "Baseline EER, growth included" : "Baseline estimated maintenance",
      value: `${fmt(snapshot.baseline_energy_kcal, 0)} kcal/day`,
      formulaName: baselineFormula(snapshot),
    });
    else if (snapshot.resting_energy_kcal != null) context.push({
      label: "Resting energy reference", value: `${fmt(snapshot.resting_energy_kcal, 0)} kcal/day`,
    });
  }
  const targets: CalculationTargetRow[] = fields.map(([key, label, unit]) => {
    const value = numeric(prescription[key]);
    const expected = snapshot?.calculated[key];
    const operator = prescription.nutrient_operators?.[key];
    const formula = targetFormula(key, snapshot);
    return {
      key, label, unit,
      value: value == null ? undefined : { value, unit, text: `${operator === "max" ? "≤ " : operator === "min" ? "≥ " : ""}${fmt(value, unit === "kcal" || unit === "mL" ? 0 : 1)} ${unit}` },
      status: value == null ? "missing" : expected == null ? "manual" : Math.abs(value - expected) < 0.005 ? "matches" : "modified",
      formula: formula.formula, calculation: formula.calculation,
    };
  });
  for (const [key, limit] of Object.entries(prescription.micronutrient_limits)) {
    const meta = ALL_MICROS.find(item => item.key === key);
    const value = limit.target ?? limit.max ?? limit.min;
    if (value == null) continue;
    const unit = limit.unit || meta?.unit || "";
    const text = limit.min != null && limit.max != null ? `${fmt(limit.min)}–${fmt(limit.max)} ${unit}`
      : `${limit.max != null ? "≤ " : limit.min != null ? "≥ " : ""}${fmt(value)} ${unit}`;
    targets.push({ key, label: meta?.label ?? key, unit, value: { value, unit, text },
      status: "manual", formula: "Confirmed nutrient target or limit", calculation: "Clinician-entered; no additional goal limit applied." });
  }
  return {
    context, targets, notes: input.clinicalNotes?.filter(Boolean) ?? [],
  };
}

function baselineFormula(s: CalculationSnapshot): string {
  if (s.baseline_kind === "pediatric_eer") {
    const terms = s.eer_coefficients;
    if (terms) return `${terms.map(term => fmt(term, 3)).map((term, index) => index === 0 ? term : `${term} × ${['', 'age(y)', 'height(cm)', 'weight(kg)'][index]}`).join(' + ')} + ${fmt(s.growth_energy_kcal ?? 0, 0)} (normal growth)`;
  }
  if (s.energy_per_kg != null) return `${fmt(s.energy_per_kg, 3)} kcal/kg × ${fmt(s.weight_kg ?? s.inputs.weightKg ?? 0)} kg`;
  const i = s.inputs;
  return `(10 × ${fmt(i.weightKg ?? 0)} + 6.25 × ${fmt(i.heightCm ?? 0)} − 5 × ${fmt(i.ageYears ?? 0)} ${i.sex === "Male" ? "+ 5" : "− 161"}) × ${fmt(i.activityFactor ?? 1, 3)}`;
}

function targetFormula(key: string, s?: CalculationSnapshot | null): { formula: string; calculation: string } {
  if (!s || s.calculated[key] == null) return { formula: "Clinician-entered target", calculation: "No calculated target recorded." };
  const calculated = s.calculated;
  switch (key) {
    case "energy_kcal": return {
      formula: "round(baseline + maternal addition + selected fixed adjustment)",
      calculation: `${fmt(s.baseline_energy_kcal ?? 0, 0)} + ${fmt(s.maternal_energy_kcal ?? 0, 0)} ${(s.signed_adjustment_kcal ?? 0) < 0 ? "−" : "+"} ${fmt(Math.abs(s.signed_adjustment_kcal ?? 0), 0)} = ${fmt(calculated.energy_kcal!, 0)} kcal`,
    };
    case "protein_g": return s.protein_fraction != null ? {
      formula: "round(final energy × selected protein fraction ÷ 4)",
      calculation: `${fmt(calculated.energy_kcal!, 0)} × ${fmt(s.protein_fraction, 3)} ÷ 4 = ${fmt(calculated.protein_g!)} g`,
    } : {
      formula: "round(confirmed g/kg × protein weight + applicable maternal protein)",
      calculation: `${fmt(s.protein_per_kg ?? 0, 3)} × ${fmt(s.protein_weight_kg ?? 0)} + ${fmt(s.maternal_protein_g ?? 0)} = ${fmt(calculated.protein_g!)} g`,
    };
    case "fat_g": return { formula: "round(final energy × selected fat fraction ÷ 9)",
      calculation: `${fmt(calculated.energy_kcal!, 0)} × ${fmt(s.fat_fraction ?? 0, 3)} ÷ 9 = ${fmt(calculated.fat_g!)} g` };
    case "carbs_g": return { formula: "round((final energy − 4 × protein − 9 × fat) ÷ 4)",
      calculation: `(${fmt(calculated.energy_kcal!, 0)} − 4 × ${fmt(calculated.protein_g!)} − 9 × ${fmt(calculated.fat_g!)}) ÷ 4 = ${fmt(calculated.carbs_g!)} g` };
    default: return { formula: s.fluid_formula ?? "Confirmed fluid reference", calculation: `${fmt(calculated.fluid_ml!, 0)} mL/day${s.fluid_mode === "restricted" ? " maximum" : " estimated; individualize"}` };
  }
}
