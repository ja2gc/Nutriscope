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
}

const fields = [
  ["energy_kcal", "Energy", "kcal"], ["protein_g", "Protein", "g"],
  ["carbs_g", "Carbohydrates", "g"], ["fat_g", "Fat", "g"], ["fluid_ml", "Fluid", "mL"],
] as const;
const fmt = (n: number) => n.toLocaleString("en-US", { maximumFractionDigits: 4 });
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
      { label: "Calculation version", value: String(snapshot.version) },
      { label: "Measurement date", value: snapshot.measurement_date ?? "Not recorded" },
      { label: "Age at measurement", value: snapshot.inputs.ageYears == null ? "Not recorded" : `${fmt(snapshot.inputs.ageYears)} years` },
      { label: "Measured / dry weight", value: snapshot.inputs.weightKg == null ? "Not recorded" : `${fmt(snapshot.inputs.weightKg)} kg` },
      { label: "Height", value: snapshot.inputs.heightCm == null ? "Not recorded" : `${fmt(snapshot.inputs.heightCm)} cm` },
      { label: "Clinical weight basis", value: snapshot.weight_basis?.replaceAll("_", " ") ?? "Not recorded" },
    );
    if (snapshot.pediatric_activity_category) context.push({ label: "Activity category", value: snapshot.pediatric_activity_category.replaceAll("_", " ") + " (assessment proxy; review)" });
    if (snapshot.baseline_energy_kcal != null) context.push({
      label: snapshot.baseline_kind === "clinical_target" ? "Baseline clinical target"
        : snapshot.baseline_kind === "pediatric_eer" ? "Baseline EER, growth included" : "Baseline estimated maintenance",
      value: `${fmt(snapshot.baseline_energy_kcal)} kcal/day`,
      formulaName: baselineFormula(snapshot),
    });
    else if (snapshot.resting_energy_kcal != null) context.push({
      label: "Resting energy reference", value: `${fmt(snapshot.resting_energy_kcal)} kcal/day`,
    });
  }
  const targets: CalculationTargetRow[] = fields.map(([key, label, unit]) => {
    const value = numeric(prescription[key]);
    const expected = snapshot?.calculated[key];
    const operator = prescription.nutrient_operators?.[key];
    const formula = targetFormula(key, snapshot);
    return {
      key, label, unit,
      value: value == null ? undefined : { value, unit, text: `${operator === "max" ? "≤ " : operator === "min" ? "≥ " : ""}${fmt(value)} ${unit}` },
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
    context, targets, notes: [
      ...(snapshot?.source ? [snapshot.source] : ["Calculation details were not recorded. Saved values are shown without reconstructing a historical formula."]),
      ...(snapshot ? ["Whole-number energy and macro rounding can produce a small difference in macro energy."] : []),
      ...(snapshot?.baseline_kind === "pediatric_eer" ? ["Pediatric general-reference estimate; review clinical needs and growth. Adult disease doses are not applied."] : []),
      ...(prescription.calculation_options?.refeeding_risk ? ["The final energy is an eventual target. Refeeding and gradual-change instructions are notes only."] : []),
    ],
  };
}

function baselineFormula(s: CalculationSnapshot): string {
  if (s.baseline_kind === "pediatric_eer") {
    const terms = s.eer_coefficients;
    if (terms) return `${terms[0]} + ${terms[1]} × age(y) + ${terms[2]} × height(cm) + ${terms[3]} × weight(kg) + ${s.growth_energy_kcal} (normal growth)`;
  }
  if (s.energy_per_kg != null) return `${s.energy_per_kg} kcal/kg × ${s.weight_kg} kg`;
  const i = s.inputs;
  return `(10 × ${i.weightKg} + 6.25 × ${i.heightCm} − 5 × ${i.ageYears} ${i.sex === "Male" ? "+ 5" : "− 161"}) × ${i.activityFactor}`;
}

function targetFormula(key: string, s?: CalculationSnapshot | null): { formula: string; calculation: string } {
  if (!s || s.calculated[key] == null) return { formula: "Clinician-entered target", calculation: "No calculated target recorded." };
  const calculated = s.calculated;
  switch (key) {
    case "energy_kcal": return {
      formula: "round(baseline + maternal addition + selected fixed adjustment)",
      calculation: `${fmt(s.baseline_energy_kcal ?? 0)} + ${s.maternal_energy_kcal ?? 0} ${(s.signed_adjustment_kcal ?? 0) < 0 ? "−" : "+"} ${Math.abs(s.signed_adjustment_kcal ?? 0)} = ${fmt(calculated.energy_kcal!)} kcal`,
    };
    case "protein_g": return s.protein_fraction != null ? {
      formula: "round(final energy × selected protein fraction ÷ 4)",
      calculation: `${calculated.energy_kcal} × ${s.protein_fraction} ÷ 4 = ${calculated.protein_g} g (rounded)`,
    } : {
      formula: "round(confirmed g/kg × protein weight + applicable maternal protein)",
      calculation: `${s.protein_per_kg} × ${s.protein_weight_kg} + ${s.maternal_protein_g ?? 0} = ${calculated.protein_g} g (rounded)`,
    };
    case "fat_g": return { formula: "round(final energy × selected fat fraction ÷ 9)",
      calculation: `${calculated.energy_kcal} × ${s.fat_fraction} ÷ 9 = ${calculated.fat_g} g (rounded)` };
    case "carbs_g": return { formula: "round((final energy − 4 × protein − 9 × fat) ÷ 4)",
      calculation: `(${calculated.energy_kcal} − 4 × ${calculated.protein_g} − 9 × ${calculated.fat_g}) ÷ 4 = ${calculated.carbs_g} g (rounded)` };
    default: return { formula: s.fluid_formula ?? "Confirmed fluid reference", calculation: `${calculated.fluid_ml} mL/day${s.fluid_mode === "restricted" ? " maximum" : " estimated; individualize"}` };
  }
}
