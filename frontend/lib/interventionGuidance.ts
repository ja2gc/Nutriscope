export const INTERVENTION_GUIDANCE_FIELD_MAX = 1200;
export const INTERVENTION_GUIDANCE_TOTAL_MAX = 2200;

export function interventionGuidanceCharacters(values: string[]): number {
  return values.reduce((total, value) => total + Array.from(value).length, 0);
}

type GuidanceText = { education_notes: string; counseling_goals: string; strategies: string };
export function mergeGeneratedGuidance(current: GuidanceText, previous: Partial<GuidanceText>, next: GuidanceText) {
  const values = { ...current };
  const pending: Partial<GuidanceText> = {};
  for (const field of ["education_notes", "counseling_goals", "strategies"] as const) {
    if (!current[field].trim() || current[field] === previous[field]) values[field] = next[field];
    else if (current[field] !== next[field]) pending[field] = next[field];
  }
  return { values, pending };
}
