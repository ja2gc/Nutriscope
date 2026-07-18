import type { Assessment } from "@/services/assessmentService";

export type SummaryLabStatus = "low" | "high" | "normal";

export interface SummaryLab {
  label: string;
  value: number;
  unit?: string;
  status: SummaryLabStatus;
}

export interface AssessmentSummaryInput {
  assessment: Partial<Assessment>;
  anthropometrics: {
    bmi?: number | null;
    ibwKg?: number | null;
    percentIbw?: number | null;
    weightChangePercent?: number | null;
    weightChangeDirection?: "loss" | "gain" | "none" | null;
    muacClassification?: string | null;
    whr?: number | null;
    whrRisk?: string | null;
    nutritionalStatus?: string | null;
  };
  labs: SummaryLab[];
  risk: {
    label: string;
    score: number;
    mode: "automatic" | "manual";
    factors: string[];
  } | null;
}

const INTAKE_METHODS: Record<string, string> = {
  "24_hour_recall": "24-hour recall",
  food_frequency: "food-frequency review",
  "3_day_record": "3-day food record",
  other: "another documented method",
};

function cleanText(value: unknown): string {
  return typeof value === "string" ? value.replace(/\s+/g, " ").trim() : "";
}

function compactText(value: unknown, maxWords = 20): string {
  const cleaned = cleanText(value);
  if (!cleaned) return "";
  const words = cleaned.split(" ");
  return words.length > maxWords ? `${words.slice(0, maxWords).join(" ")}…` : cleaned;
}

function numberValue(value: unknown): number | null {
  if (value === "" || value === null || value === undefined) return null;
  const parsed = typeof value === "number" ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function formatNumber(value: unknown): string | null {
  const parsed = numberValue(value);
  return parsed === null ? null : String(parsed);
}

function lowerFirst(value: string): string {
  return value ? `${value[0].toLowerCase()}${value.slice(1)}` : value;
}

function sentence(value: string): string {
  return /[.!?]$/.test(value) ? value : `${value}.`;
}

function category(label: string, parts: string[]): string | null {
  const content = parts.filter(Boolean).join(" ");
  return content ? `${label}: ${content}` : null;
}

export function buildAssessmentSummary(input: AssessmentSummaryInput): string {
  const { assessment, anthropometrics, labs, risk } = input;
  const categories: Array<string | null> = [];

  const anthropometricParts: string[] = [];
  const weight = formatNumber(assessment.weight);
  const dryWeight = formatNumber(assessment.dry_weight_kg);
  if (weight) anthropometricParts.push(`${weight} kg`);
  const nutritionalStatus = cleanText(anthropometrics.nutritionalStatus ?? assessment.nutritional_status);
  if (anthropometrics.bmi !== null && anthropometrics.bmi !== undefined) {
    anthropometricParts.push(`BMI ${anthropometrics.bmi}${nutritionalStatus ? ` (${nutritionalStatus})` : ""}`);
  }
  if (anthropometrics.percentIbw !== null && anthropometrics.percentIbw !== undefined
    && (anthropometrics.percentIbw < 85 || anthropometrics.percentIbw > 130)) {
    anthropometricParts.push(`${anthropometrics.percentIbw}% IBW`);
  }
  if (anthropometrics.weightChangePercent !== null && anthropometrics.weightChangePercent !== undefined) {
    const direction = anthropometrics.weightChangeDirection === "gain" ? "gain" : anthropometrics.weightChangeDirection === "loss" ? "loss" : "change";
    const period = cleanText(assessment.weight_loss_period);
    anthropometricParts.push(`${anthropometrics.weightChangePercent}% weight ${direction}${period ? ` over ${period}` : ""}`);
  }
  const muac = formatNumber(assessment.muac_mm);
  if (muac && anthropometrics.muacClassification && !/^normal$/i.test(anthropometrics.muacClassification)) {
    anthropometricParts.push(`MUAC ${muac} mm (${anthropometrics.muacClassification})`);
  }
  if (anthropometrics.whr !== null && anthropometrics.whr !== undefined && /high/i.test(anthropometrics.whrRisk ?? "")) {
    anthropometricParts.push(`WHR ${anthropometrics.whr}${anthropometrics.whrRisk ? ` (${anthropometrics.whrRisk})` : ""}`);
  }
  if (anthropometricParts.length) categories.push(`Anthropometrics: ${anthropometricParts.join("; ")}.`);

  const dietaryParts: string[] = [];
  const presentDiet = compactText(assessment.present_diet);
  const intakeStatus = cleanText(assessment.energy_intake_status);
  const intakeMethod = cleanText(assessment.dietary_intake_method);
  const dietaryIntake = compactText(assessment.dietary_intake);
  const appetite = cleanText(assessment.appetite_changes);
  const restrictions = compactText(assessment.dietary_restrictions);
  const supplements = compactText(assessment.supplements);
  const interaction = compactText(assessment.nutrient_drug_interaction);
  const chewing = compactText(assessment.chewing_swallowing_difficulties);
  const constipation = compactText(assessment.constipation);
  const diarrhea = compactText(assessment.diarrhea_notes);
  const intolerance = compactText(assessment.food_intolerance);
  if (presentDiet) dietaryParts.push(sentence(`Current diet: ${presentDiet}`));
  if (intakeStatus) dietaryParts.push(sentence(`Energy intake is ${lowerFirst(intakeStatus)}`));
  if (intakeMethod) dietaryParts.push(sentence(`Intake assessed using ${INTAKE_METHODS[intakeMethod] ?? lowerFirst(intakeMethod)}`));
  if (dietaryIntake) dietaryParts.push(sentence(`Reported intake: ${dietaryIntake}`));
  if (appetite) dietaryParts.push(sentence(`Appetite is ${lowerFirst(appetite.replace(/_/g, " "))}`));
  if (restrictions) dietaryParts.push(sentence(`Dietary restrictions: ${restrictions}`));
  if (supplements) dietaryParts.push(sentence(`Supplements: ${supplements}`));
  if (interaction) dietaryParts.push(sentence(`Nutrient-drug interaction: ${interaction}`));
  if (chewing) dietaryParts.push(sentence(`Chewing/swallowing: ${chewing}`));
  if (constipation) dietaryParts.push(sentence(`Constipation: ${constipation}`));
  if (diarrhea) dietaryParts.push(sentence(`Diarrhea: ${diarrhea}`));
  if (intolerance) dietaryParts.push(sentence(`Food intolerance: ${intolerance}`));
  categories.push(category("Dietary / GI", dietaryParts));

  if (labs.length) {
    const labParts = labs
      .filter((lab) => Number.isFinite(lab.value) && lab.status !== "normal")
      .map((lab) => `${lab.label} ${lab.value}${lab.unit ? ` ${lab.unit}` : ""}${lab.status === "normal" ? "" : ` (${lab.status.toUpperCase()})`}`);
    categories.push(labParts.length
      ? `Biochemical: ${labParts.join("; ")}.`
      : "Biochemical: Entered results are within listed reference ranges.");
  }

  const clinicalParts: string[] = [];
  const medicalHistory = compactText(assessment.medical_history);
  const functional = cleanText(assessment.functional_assessment);
  const pregnancy = cleanText(assessment.pregnancy_lactation_status);
  if (medicalHistory) clinicalParts.push(sentence(`Medical history: ${medicalHistory}`));
  if (functional) clinicalParts.push(sentence(`Functional status: ${functional}`));
  if (pregnancy && pregnancy !== "none") clinicalParts.push(sentence(`Pregnancy/lactation: ${pregnancy}`));
  if (assessment.edema_present) clinicalParts.push("Edema is present.");
  if (assessment.edema_present && dryWeight) clinicalParts.push(`Dry weight is ${dryWeight} kg.`);
  if (assessment.allergies?.length) clinicalParts.push(`Allergies: ${assessment.allergies.join(", ")}.`);
  if (assessment.food_dislikes?.length) clinicalParts.push(`Food dislikes: ${assessment.food_dislikes.join(", ")}.`);
  if (assessment.medications?.length) clinicalParts.push(`Medications: ${assessment.medications.join(", ")}.`);
  categories.push(category("Clinical context", clinicalParts));

  if (risk) {
    const visibleFactors = risk.factors.slice(0, 3);
    const remaining = risk.factors.length - visibleFactors.length;
    const factors = visibleFactors.length
      ? `; ${visibleFactors.join(", ")}${remaining > 0 ? ` +${remaining} more` : ""}`
      : "";
    const label = /risk$/i.test(risk.label.trim()) ? risk.label.trim() : `${risk.label.trim()} risk`;
    categories.push(`Nutrition risk: ${label} (${risk.score} point${risk.score === 1 ? "" : "s"}, ${risk.mode})${factors}.`);
  }

  return categories.filter((value): value is string => Boolean(value)).join("\n\n");
}
