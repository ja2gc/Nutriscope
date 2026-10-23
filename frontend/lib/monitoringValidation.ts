import { ABG_LIMIT, ASSESSMENT_TEXT_LIMITS, FOOD_DISLIKE_LIMIT, FOOD_DISLIKE_MAX_ENTRIES } from "./assessmentValidation";
import type { MonitoringPayload } from "@/services/monitoringService";

export const MONITORING_TEXT_LIMITS = { intake_notes: 400, symptoms: 400, clinical_summary: 700 } as const;
export const ALLERGY_ENTRY_LIMIT = 160;

export function monitoringTextError(value: Partial<MonitoringPayload>): string | null {
  const limits = { ...MONITORING_TEXT_LIMITS, dietary_restrictions: ASSESSMENT_TEXT_LIMITS.dietary_restrictions };
  for (const [key, limit] of Object.entries(limits)) {
    const text = value[key as keyof typeof limits];
    if (typeof text === "string" && Array.from(text).length > limit) return `${key.replaceAll("_", " ")} must be ${limit} characters or fewer.`;
  }
  if ((value.food_dislikes?.length ?? 0) > FOOD_DISLIKE_MAX_ENTRIES) return `Food dislikes can contain at most ${FOOD_DISLIKE_MAX_ENTRIES} entries.`;
  if (value.food_dislikes?.some((item) => Array.from(item).length > FOOD_DISLIKE_LIMIT)) return `Each food dislike must be ${FOOD_DISLIKE_LIMIT} characters or fewer.`;
  if (value.allergies?.some((item) => Array.from(item).length > ALLERGY_ENTRY_LIMIT)) return `Each allergy must be ${ALLERGY_ENTRY_LIMIT} characters or fewer.`;
  const bp = value.lab_values?.bp;
  if (bp != null && Array.from(String(bp)).length > 20) return "Blood pressure must be 20 characters or fewer.";
  const abg = value.lab_values?.abg;
  if (abg != null && abg !== "" && (String(abg).length > ABG_LIMIT || !Number.isFinite(Number(abg)) || !/^[+-]?(?:\d+(?:\.\d{0,2})?|\.\d{1,2})$/.test(String(abg)))) return `ABG (pH) must be ${ABG_LIMIT} characters or fewer, with at most 2 decimal places.`;
  return null;
}
