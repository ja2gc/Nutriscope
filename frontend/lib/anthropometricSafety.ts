import { formatPatientAge, getPatientAgeParts } from "./patientAge";

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
