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
    expect(energy?.value?.value).toBe(1400);
    expect(energy?.formula).toBe("max((BMR × PAL) − calorie deficit, caloric floor)");
    expect(energy?.calculation).toContain("= 1501 kcal");
  });

  test("uses flat severe targets and omits valueless micronutrients", () => {
    const trace = buildPrescriptionCalculationTrace({
      goalType: "malnutrition",
      stage: "severe",
      goalLabel: "Malnutrition",
      stageLabel: "Severe",
      metrics: { ...adultMetrics, weightKg: 52, heightCm: 170, ageYears: 38, activityFactor: 1.4 },
      prescription: {
        energy_kcal: "1690",
        protein_g: "67",
        carbs_g: "239",
        fat_g: "52",
        fluid_ml: "1690",
        displayed_nutrients: ["potassium", "phosphate", "magnesium"],
        micronutrient_limits: {},
      },
      requiredMicros: ["potassium", "phosphate", "magnesium"],
    });

    const energy = trace.targets.find((row) => row.key === "energy_kcal");
    expect(energy?.formula).toBe("Working Weight × kcal/kg factor");
    expect(energy?.calculation).toBe("52 × 32.5 = 1690 kcal");
    expect(trace.context.find((row) => row.label === "BMR")).toEqual({
      label: "BMR",
      value: "1,397.5 kcal",
      formulaName: "Mifflin-St Jeor",
    });
    expect(trace.targets.find((row) => row.key === "potassium")).toBeUndefined();
    expect(trace.targets.find((row) => row.key === "phosphate")).toBeUndefined();
    expect(trace.targets.find((row) => row.key === "magnesium")).toBeUndefined();
    expect(trace.notes.join(" ")).not.toMatch(/refeeding/i);
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
    expect(trace.targets.find((row) => row.key === "sodium")?.value?.text).toContain("2000");
    expect(trace.targets.find((row) => row.key === "sodium")?.formula).toBe("Manual target");
  });

  test("uses pediatric fluid and fat formulas from the authoritative engine", () => {
    const trace = buildPrescriptionCalculationTrace({
      goalType: "diabetic_control",
      stage: "stage_1",
      metrics: {
        weightKg: 20,
        heightCm: 115,
        ageYears: 7,
        sex: "Male",
        isAdult: false,
        activityFactor: 1.2,
      },
      prescription: {
        energy_kcal: "",
        protein_g: "",
        carbs_g: "",
        fat_g: "",
        fluid_ml: "",
        displayed_nutrients: [],
        micronutrient_limits: {},
      },
    });

    const fat = trace.targets.find((row) => row.key === "fat_g");
    const fluid = trace.targets.find((row) => row.key === "fluid_ml");

    expect(fat?.formula).toBe("Energy × fat% ÷ 9");
    expect(fluid?.formulaName).toBe("Holliday-Segar");
    expect(fluid?.value?.value).toBe(1500);
    expect(fluid?.calculation).toContain("1500 mL");
  });

  test("shows pregnancy add-ons in context and substituted formulas", () => {
    const metrics: PatientMetrics = {
      weightKg: 60,
      heightCm: 160,
      ageYears: 30,
      sex: "Female",
      isAdult: true,
      activityFactor: 1.3,
      pregnancyLactationStatus: "pregnant",
    };
    const calculated = buildPrescriptionCalculationTrace({
      goalType: "diabetic_control",
      stage: "stage_1",
      metrics,
      prescription: {
        energy_kcal: "",
        protein_g: "",
        carbs_g: "",
        fat_g: "",
        fluid_ml: "",
        displayed_nutrients: [],
        micronutrient_limits: {},
      },
    });

    expect(calculated.context).toContainEqual({ label: "Pregnancy / Lactation", value: "Pregnant" });
    expect(calculated.targets.find((row) => row.key === "energy_kcal")?.formula).toContain("energy add-on");
    expect(calculated.targets.find((row) => row.key === "energy_kcal")?.calculation).toContain("+ 300");
    expect(calculated.targets.find((row) => row.key === "protein_g")?.calculation).toContain("+ 27");
  });
});
