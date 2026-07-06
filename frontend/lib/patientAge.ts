export type PatientAgeParts = {
  years: number;
  months: number;
  totalMonths: number;
  totalDays: number;
};

const MS_PER_DAY = 24 * 60 * 60 * 1000;

function parseDate(value?: string | null): Date | null {
  if (!value) {
    return null;
  }

  const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (dateOnly) {
    const [, year, month, day] = dateOnly;
    const parsed = new Date(Number(year), Number(month) - 1, Number(day));
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatDateInputValue(value?: string | null): string {
  const dateOnly = value?.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (dateOnly) {
    return `${dateOnly[1]}-${dateOnly[2]}-${dateOnly[3]}`;
  }

  const parsed = parseDate(value);
  if (!parsed) {
    return "";
  }

  const year = parsed.getFullYear();
  const month = String(parsed.getMonth() + 1).padStart(2, "0");
  const day = String(parsed.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function atLocalDate(value: Date) {
  return new Date(value.getFullYear(), value.getMonth(), value.getDate());
}

function utcDateValue(value: Date) {
  return Date.UTC(value.getFullYear(), value.getMonth(), value.getDate());
}

export function getPatientAgeParts(dob?: string | null, now = new Date()): PatientAgeParts | null {
  const birthDate = parseDate(dob);
  if (!birthDate) {
    return null;
  }

  const birth = atLocalDate(birthDate);
  const reference = atLocalDate(now);
  if (birth.getTime() > reference.getTime()) {
    return null;
  }

  let totalMonths = (reference.getFullYear() - birth.getFullYear()) * 12
    + reference.getMonth() - birth.getMonth();
  if (reference.getDate() < birth.getDate()) {
    totalMonths -= 1;
  }

  const totalDays = Math.floor((utcDateValue(reference) - utcDateValue(birth)) / MS_PER_DAY);
  return {
    years: Math.floor(totalMonths / 12),
    months: totalMonths % 12,
    totalMonths,
    totalDays,
  };
}

export function formatPatientAge(dob?: string | null, now = new Date()): string {
  const age = getPatientAgeParts(dob, now);
  if (!age) {
    return "N/A";
  }

  if (age.totalMonths < 1) {
    return age.totalDays === 1 ? "1 day" : `${age.totalDays} days`;
  }

  if (age.totalMonths < 12) {
    return age.totalMonths === 1 ? "1 month" : `${age.totalMonths} months`;
  }

  return age.years === 1 ? "1 year" : `${age.years} years`;
}
