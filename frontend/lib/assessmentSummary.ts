import type { Assessment } from "@/services/assessmentService";
import { ACTIVITY_FACTORS } from "./nutritionCalculations";
import { nutritionSupportLabel } from "./nutritionSupport";
import { ASSESSMENT_TEXT_LIMITS } from "./assessmentValidation";

export interface AssessmentSummaryInput {
  assessment: Partial<Assessment>;
  referral?: { diagnosis?: string | null; dietPrescription?: string | null };
  anthropometrics: {
    bmi?: number | null;
    nutritionalStatus?: string | null;
  };
  risk: {
    label: string;
  } | null;
}

const MATERNAL_STATUS_LABELS: Record<string, string> = {
  pregnant_t1: "pregnant — first trimester",
  pregnant_t2: "pregnant — second trimester",
  pregnant_t3: "pregnant — third trimester",
  pregnant_unspecified: "pregnant — trimester not confirmed",
  lactating: "lactating",
};

function cleanText(value: unknown): string {
  return typeof value === "string" ? value.replace(/\s+/g, " ").trim() : "";
}

function compactText(value: unknown): string {
  // Keep source sentences as clauses, leaving decimal measurements intact.
  const cleaned = cleanText(value).replace(/[.!?]+(?=\s|$)/g, ";").replace(/[;,\s]+$/, "");
  const words = cleaned.split(" ");
  const compact = words.slice(0, 20).join(" ").slice(0, ASSESSMENT_TEXT_LIMITS.medical_history);
  return compact.length < cleaned.length ? `${compact}…` : compact;
}

function sentence(parts: string[]): string {
  const text = parts.filter(Boolean).join("; ");
  return text ? `${text[0].toUpperCase()}${text.slice(1)}.` : "";
}

function symptom(value: unknown): boolean {
  const text = cleanText(value);
  return Boolean(text) && !/^(no|none|normal|unchanged|no change|good|not applicable|n\/a)$/i.test(text);
}

export function buildAssessmentSummary(input: AssessmentSummaryInput): string {
  const { assessment, anthropometrics, referral, risk } = input;
  const diagnosis = compactText(referral?.diagnosis);
  const prescribedDiet = compactText(referral?.dietPrescription);
  const history = compactText(assessment.medical_history);
  const referralSentence = sentence([
    diagnosis ? `Referral diagnosis: ${diagnosis}` : history ? `Medical history: ${history}` : "",
    prescribedDiet ? `prescribed diet: ${prescribedDiet}` : "",
  ]);

  const maternal = MATERNAL_STATUS_LABELS[assessment.pregnancy_lactation_status ?? ""];
  const bmi = anthropometrics.bmi;
  const classification = !maternal && !assessment.edema_present
    ? cleanText(anthropometrics.nutritionalStatus) : "";
  const pal = assessment.physical_activity_level;
  const contextSentence = sentence([
    bmi != null && Number.isFinite(bmi) && bmi > 0
      ? `BMI ${bmi}${classification ? ` (${classification})` : ""}` : "",
    maternal ?? "",
    pal && ACTIVITY_FACTORS[pal] ? `Activity estimate: ${ACTIVITY_FACTORS[pal].label}` : "",
    assessment.edema_present ? "edema present" : "",
  ]);

  const swallowing = symptom(assessment.chewing_swallowing_difficulties);
  const constipation = symptom(assessment.constipation);
  const diarrhea = symptom(assessment.diarrhea_notes);
  const barrier = swallowing
    ? `chewing/swallowing difficulty${compactText(assessment.chewing_swallowing_details) ? `: ${compactText(assessment.chewing_swallowing_details)}` : ""}`
    : symptom(assessment.appetite_changes) ? `appetite: ${compactText(assessment.appetite_changes?.replace(/_/g, " "))}`
    : diarrhea ? `diarrhea${compactText(assessment.diarrhea_details) ? `: ${compactText(assessment.diarrhea_details)}` : ""}`
    : constipation ? `constipation${compactText(assessment.constipation_details) ? `: ${compactText(assessment.constipation_details)}` : ""}`
    : symptom(assessment.food_intolerance) ? `food intolerance: ${compactText(assessment.food_intolerance)}`
    : assessment.functional_assessment && assessment.functional_assessment !== "Independent"
      ? `functional status: ${compactText(assessment.functional_assessment)}` : "";
  const allergies = assessment.allergies?.map(cleanText).filter(Boolean) ?? [];
  const allergyContext = allergies.length
    ? `allergies: ${allergies.slice(0, 2).map(compactText).join(", ")}${allergies.length > 2 ? ` (+${allergies.length - 2} more)` : ""}` : "";
  const intakeStatus = compactText(assessment.energy_intake_status);
  const restrictions = compactText(assessment.dietary_restrictions);
  const presentDiet = compactText(assessment.present_diet);
  const reportedIntake = compactText(assessment.dietary_intake);
  const intake = intakeStatus ? `energy intake: ${intakeStatus}`
    : restrictions ? `dietary restrictions: ${restrictions}`
    : presentDiet && !prescribedDiet ? `current diet: ${presentDiet}`
    : reportedIntake ? `reported intake: ${reportedIntake}` : "";
  const riskLabel = compactText(risk?.label);
  const careSentence = sentence([
    assessment.nutrition_support ? `nutrition support: ${nutritionSupportLabel(assessment.nutrition_support)}` : "",
    intake,
    barrier || allergyContext,
    riskLabel ? `nutrition screening: ${/risk$/i.test(riskLabel) ? riskLabel : `${riskLabel} risk`}` : "",
  ]);

  return [referralSentence, contextSentence, careSentence].filter(Boolean).join(" ");
}
