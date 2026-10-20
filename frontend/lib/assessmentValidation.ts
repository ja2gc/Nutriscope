import type { Assessment } from "@/services/assessmentService";
import { getWaistHipAssessment } from "./anthropometricSafety";

export const ASSESSMENT_TEXT_LIMITS = {
  present_diet: 250,
  dietary_intake: 250,
  dietary_restrictions: 100,
  supplements: 100,
  knowledge_notes: 250,
  nutrient_drug_interaction: 100,
  chewing_swallowing_details: 150,
  constipation_details: 150,
  diarrhea_details: 150,
  primary_diagnosis_other: 150,
  medical_history: 250,
  social_history: 250,
  religion: 100,
  food_intolerance: 100,
  rnd_summary: 3500,
} as const;

export const FOOD_DISLIKE_LIMIT = 50;
export const FOOD_DISLIKE_MAX_ENTRIES = 5;
export const MEDICATION_LIMIT = 100;
export const ABG_LIMIT = 6;

const labels: Record<keyof typeof ASSESSMENT_TEXT_LIMITS, string> = {
  present_diet: "Present diet", dietary_intake: "Dietary intake", dietary_restrictions: "Dietary restrictions",
  supplements: "Supplements", knowledge_notes: "Knowledge / beliefs notes", nutrient_drug_interaction: "Nutrient-drug interaction",
  chewing_swallowing_details: "Chewing / swallowing details", constipation_details: "Constipation details", diarrhea_details: "Diarrhea details",
  primary_diagnosis_other: "Specified category", medical_history: "Medical history", social_history: "Social history",
  religion: "Religion / dietary practices", food_intolerance: "Food intolerance", rnd_summary: "RND summary",
};

export function assessmentTextError(assessment: Partial<Assessment>): string | null {
  const circumferenceError = getWaistHipAssessment({ waistCm: assessment.waist_cm, hipCm: assessment.hip_cm, sex: null, ageYears: null }).error;
  if (circumferenceError) return circumferenceError;
  for (const [field, limit] of Object.entries(ASSESSMENT_TEXT_LIMITS)) {
    const key = field as keyof typeof ASSESSMENT_TEXT_LIMITS;
    const value = assessment[key];
    if (typeof value === "string" && Array.from(value).length > limit) {
      return `${labels[key]} must be ${limit} characters or fewer.`;
    }
  }

  if ((assessment.food_dislikes?.length ?? 0) > FOOD_DISLIKE_MAX_ENTRIES) {
    return `Food dislikes can contain at most ${FOOD_DISLIKE_MAX_ENTRIES} entries.`;
  }
  if (assessment.food_dislikes?.some((value) => Array.from(value).length > FOOD_DISLIKE_LIMIT)) {
    return `Each food dislike must be ${FOOD_DISLIKE_LIMIT} characters or fewer.`;
  }
  if (Array.from(assessment.medications?.join(", ") ?? "").length > MEDICATION_LIMIT) {
    return `Medications must be ${MEDICATION_LIMIT} characters or fewer in total.`;
  }
  const bp = assessment.biochemical_data?.bp;
  if (typeof bp === "string" && Array.from(bp).length > 20) return "Blood pressure must be 20 characters or fewer.";
  const abg = assessment.biochemical_data?.abg;
  if (abg != null && abg !== "") {
    const value = String(abg);
    if (value.length > ABG_LIMIT || !Number.isFinite(Number(value)) || !/^[+-]?\d*\.?\d{0,2}$/.test(value)) {
      return `ABG (pH) must be ${ABG_LIMIT} characters or fewer, with at most 2 decimal places.`;
    }
  }
  return null;
}
