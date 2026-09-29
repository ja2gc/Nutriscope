export const INTERVENTION_GUIDANCE_FIELD_MAX = 1200;
export const INTERVENTION_GUIDANCE_TOTAL_MAX = 2200;

export function interventionGuidanceCharacters(values: string[]): number {
  return values.reduce((total, value) => total + value.length, 0);
}
