import { describe, expect, test } from "vitest";
import { buildPrescriptionCalculationTrace } from "./prescriptionCalculationTrace";
import type { PatientMetrics } from "./nutritionCalculations";

const adultMetrics: PatientMetrics = {
  weightKg: 80,
  heightCm: 170,
  ageYears: 40,
  sex: "Male",
  isAdult: true,
  activityFactor: 1.2,
};

describe("buildPrescriptionCalculationTrace", () => {
  test("marks manually changed energy as modified while retaining calculated baseline", () => {
    const trace = buildPrescriptionCalculationTrace({
      goalType: "diabetic_control",
      stage: "stage_2",
      goalLabel: "Diabetic Control",
      stageLabel: "Stage 2",
      metrics: adultMetrics,
      prescription: {
        energy_kcal: "1400",
        protein_g: "",
        carbs_g: "",
        fat_g: "",
        fluid_ml: "",
        displayed_nutrients: ["fiber", "free_sugars"],
        micronutrient_limits: {},
      },
      requiredMicros: ["fiber", "sodium", "free_sugars"],
    });

    const energy = trace.targets.find((row) => row.key === "energy_kcal");

    expect(energy?.status).toBe("modified");
    expect(energy?.calculated?.value).toBeGreaterThan(1400);
    expect(energy?.prescribed?.value).toBe(1400);
    expect(energy?.formula).toContain("TEE");
  });

  test("includes refeeding monitoring micros without fake numeric targets", () => {
    const trace = buildPrescriptionCalculationTrace({
      goalType: "malnutrition",
      stage: "severe",
      goalLabel: "Malnutrition",
      stageLabel: "Severe",
      metrics: { ...adultMetrics, weightKg: 48, heightCm: 174 },
      prescription: {
        energy_kcal: "360",
        protein_g: "59",
        carbs_g: "",
        fat_g: "",
        fluid_ml: "",
        displayed_nutrients: ["potassium", "phosphate", "magnesium"],
        micronutrient_limits: {},
      },
      requiredMicros: ["potassium", "phosphate", "magnesium"],
    });

    expect(trace.targets.find((row) => row.key === "potassium")?.status).toBe("flagged");
    expect(trace.targets.find((row) => row.key === "phosphate")?.calculated?.text).toContain("monitoring");
    expect(trace.notes.join(" ")).toContain("Refeeding");
  });

  test("custom goal reports manual rows without formula", () => {
    const trace = buildPrescriptionCalculationTrace({
      goalType: "custom",
      stage: null,
      goalLabel: "Custom Plan",
      stageLabel: undefined,
      metrics: adultMetrics,
      prescription: {
        energy_kcal: "1800",
        protein_g: "",
        carbs_g: "",
        fat_g: "",
        fluid_ml: "",
        displayed_nutrients: ["sodium"],
        micronutrient_limits: { sodium: { max: 2000, unit: "mg" } },
      },
      requiredMicros: [],
    });

    expect(trace.targets.find((row) => row.key === "energy_kcal")?.status).toBe("manual");
    expect(trace.targets.find((row) => row.key === "sodium")?.prescribed?.text).toContain("2000");
    expect(trace.targets.find((row) => row.key === "sodium")?.formula).toBe("Manual target");
  });
});
