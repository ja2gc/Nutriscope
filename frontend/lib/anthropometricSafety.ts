import { formatPatientAge, getPatientAgeParts } from "./patientAge";

export const CIRCUMFERENCE_BOUNDS = { min: 20, max: 250 } as const;

/** WHO 6–59-month MUAC screening only; not a complete nutrition assessment. */
export function getMuacScreening(muacMm: number | null, ageMonths: number | null): { label: string; color: string } | null {
  if (muacMm === null || !Number.isFinite(muacMm) || muacMm <= 0 || ageMonths === null || !Number.isFinite(ageMonths) || ageMonths < 6 || ageMonths >= 60) return null;
  if (muacMm < 115) return { label: 'Very low MUAC', color: 'text-red-600' };
  if (muacMm < 125) return { label: 'Low MUAC', color: 'text-amber-600' };
  return { label: 'Above screening cutoff', color: 'text-warm-500' };
}

export function getWaistHipAssessment({ waistCm, hipCm, sex, ageYears, pregnancyLactationStatus }: {
  waistCm?: number | string | null;
  hipCm?: number | string | null;
  sex: string | null;
  ageYears: number | null;
  pregnancyLactationStatus?: string | null;
}): { ratio: number | null; risk: "Low Risk" | "High Risk" | null; error: string | null } {
  for (const [label, value] of [["Waist", waistCm], ["Hip", hipCm]] as const) {
    if (value == null || value === "") continue;
    const number = finiteNumber(value);
    if (number === null || number < CIRCUMFERENCE_BOUNDS.min || number > CIRCUMFERENCE_BOUNDS.max) {
      return { ratio: null, risk: null, error: `${label} circumference must be between ${CIRCUMFERENCE_BOUNDS.min} and ${CIRCUMFERENCE_BOUNDS.max} cm.` };
    }
  }
  const waist = finiteNumber(waistCm);
  const hip = finiteNumber(hipCm);
  if (waist === null || hip === null) return { ratio: null, risk: null, error: null };
  const ratio = waist / hip;
  // WHO waist–hip ratio report: adult cutoffs; keep full precision for classification.
  const canClassify = ageYears !== null && ageYears >= 18 && (sex === "Male" || sex === "Female")
    && !pregnancyLactationStatus?.startsWith("pregnant");
  return { ratio, risk: canClassify ? (ratio >= (sex === "Female" ? 0.85 : 0.9) ? "High Risk" : "Low Risk") : null, error: null };
}

type AnthropometricSafetyInput = {
  dob?: string | null;
  weightKg?: number | string | null;
  heightCm?: number | string | null;
  now?: Date;
};

function finiteNumber(value?: number | string | null): number | null {
  if (value === null || value === undefined || value === "") {
    return null;
  }

  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function formatMeasurement(value: number | null, unit: string) {
  if (value === null) {
    return null;
  }

  return `${Number.isInteger(value) ? value : value.toFixed(1)} ${unit}`;
}

export function getAnthropometricSafetyWarning({
  dob,
  weightKg,
  heightCm,
  now = new Date(),
}: AnthropometricSafetyInput): string | null {
  const age = getPatientAgeParts(dob, now);
  if (!age || age.totalMonths >= 24) {
    return null;
  }

  const weight = finiteNumber(weightKg);
  const height = finiteNumber(heightCm);
  const isSuspiciousInfant = age.totalMonths < 12 && ((weight !== null && weight >= 30) || (height !== null && height >= 110));
  const isSuspiciousToddler = age.totalMonths >= 12 && ((weight !== null && weight >= 35) || (height !== null && height >= 120));

  if (!isSuspiciousInfant && !isSuspiciousToddler) {
    return null;
  }

  const measurements = [
    formatMeasurement(weight, "kg"),
    formatMeasurement(height, "cm"),
  ].filter(Boolean).join(" and ");

  return `Confirm patient details before saving. This assessment is for a patient aged ${formatPatientAge(dob, now)}, but anthropometrics are ${measurements}. Confirm date of birth, weight, and height before continuing.`;
}
