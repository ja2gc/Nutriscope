import { describe, expect, test } from "vitest";
import { buildPrescriptionCalculationTrace } from "./prescriptionCalculationTrace";
import type { CalculationSnapshot } from "@/services/interventionService";
import type { PrescriptionFormState } from "./interventionGoalState";

const snapshot: CalculationSnapshot = {
  version: 2, method: "Mifflin–St Jeor × confirmed adult activity estimate", source: "Mifflin 1990; local activity estimate",
  inputs: { weightKg: 70, heightCm: 170, ageYears: 40, sex: "Male", activityFactor: 1.2 },
  measurement_date: "2026-09-28", baseline_kind: "estimated_maintenance", baseline_energy_kcal: 1881,
  weight_basis: "measured", protein_per_kg: 1, protein_weight_kg: 70, fat_fraction: 0.25,
  signed_adjustment_kcal: -300, energy_adjustment_mode: "deficit", energy_adjustment_kcal: 300,
  fluid_formula: "Prescribed maximum; all sources", fluid_mode: "restricted",
  calculated: { energy_kcal: 1581, protein_g: 70, fat_g: 44, carbs_g: 226, fluid_ml: 1700 },
};
const prescription: PrescriptionFormState = {
  energy_kcal: "1581", protein_g: "70", fat_g: "44", carbs_g: "226", fluid_ml: "1700",
  nutrient_operators: { fluid_ml: "max" }, displayed_nutrients: [], micronutrient_limits: {},
};
const build = (changes: Partial<PrescriptionFormState> = {}, saved: CalculationSnapshot | null = snapshot) =>
  buildPrescriptionCalculationTrace({ goalType: "general_diet", stage: null,
    prescription: { ...prescription, ...changes }, snapshot: saved });

describe("persisted prescription calculation trace", () => {
  test("shows baseline and the selected fixed deficit exactly once", () => {
    const trace = build();
    expect(trace.context.find(row => row.label === "Baseline estimated maintenance")?.value).toBe("1,881 kcal/day");
    expect(trace.targets.find(row => row.key === "energy_kcal")).toMatchObject({
      status: "matches", calculation: "1,881 + 0 − 300 = 1,581 kcal",
    });
    expect(trace.targets.find(row => row.key === "fluid_ml")?.value?.text).toBe("≤ 1,700 mL");
    expect(JSON.stringify(trace)).not.toMatch(/caloric floor|starting dose|undefined/);
  });
  test("identifies manual overrides while retaining the calculated result", () => {
    const energy = build({ energy_kcal: "1600" }).targets.find(row => row.key === "energy_kcal");
    expect(energy?.status).toBe("modified");
    expect(energy?.value?.value).toBe(1600);
    expect(energy?.calculation).toContain("1,581 kcal");
  });
  test("never reconstructs legacy or custom calculations using current measurements", () => {
    const trace = buildPrescriptionCalculationTrace({ goalType: "custom", stage: null,
      metrics: { weightKg: 100, heightCm: 185, ageYears: 60, sex: "Male", isAdult: true, activityFactor: 1.9 },
      prescription });
    expect(trace.targets.find(row => row.key === "energy_kcal")?.status).toBe("manual");
    expect(trace.notes.join(" ")).toContain("without reconstructing");
    expect(trace.context).not.toContainEqual(expect.objectContaining({ value: "100 kg" }));
  });
  test("shows only confirmed nutrient limits and falls back to nutrient units", () => {
    const trace = build({ micronutrient_limits: { fiber: { min: 25 }, sodium: { max: 2000 } } as never });
    expect(trace.targets.find(row => row.key === "fiber")?.value?.text).toBe("≥ 25 g");
    expect(trace.targets.find(row => row.key === "sodium")?.value?.text).toBe("≤ 2,000 mg");
    expect(trace.targets.find(row => row.key === "potassium")).toBeUndefined();
  });
  test("renders the saved pediatric equation with normal growth and no adult activity multiplier", () => {
    const trace = build({}, { ...snapshot, method: "NASEM 2023 EER", baseline_kind: "pediatric_eer",
      inputs: { ageYears: 5, weightKg: 20, heightCm: 115, sex: "Male" },
      baseline_energy_kcal: 1345.04, eer_coefficients: [-447.51, 3.68, 13.01, 13.15],
      growth_energy_kcal: 15, protein_fraction: 0.1, fat_fraction: 0.25,
      calculated: { energy_kcal: 1345, protein_g: 34, fat_g: 37, carbs_g: 219, fluid_ml: 1500 },
    });
    expect(trace.context.find(row => row.label === "Baseline EER, growth included")?.formulaName).toContain("15 (normal growth)");
    expect(trace.targets.find(row => row.key === "protein_g")?.calculation).toBe("1345 × 0.1 ÷ 4 = 34 g (rounded)");
    expect(trace.notes.join(" ")).toContain("growth");
  });
  test("includes maternal additions once and keeps refeeding text out of arithmetic", () => {
    const trace = build({ calculation_options: { refeeding_risk: true } }, {
      ...snapshot, maternal_energy_kcal: 300, maternal_protein_g: 27, signed_adjustment_kcal: 0,
      calculated: { ...snapshot.calculated, energy_kcal: 2181, protein_g: 97 },
    });
    expect(trace.targets.find(row => row.key === "energy_kcal")?.calculation).toBe("1,881 + 300 + 0 = 2,181 kcal");
    expect(trace.targets.find(row => row.key === "protein_g")?.calculation).toBe("1 × 70 + 27 = 97 g (rounded)");
    expect(trace.notes.join(" ")).toContain("notes only");
  });
  test("does not fabricate zero measurements for manual plans", () => {
    const trace = build({}, { version: 2, method: "Clinician-entered prescription", inputs: {}, calculated: {} });
    expect(trace.context.find(row => row.label === "Age at measurement")?.value).toBe("Not recorded");
    expect(JSON.stringify(trace)).not.toContain("0 years");
  });
});
