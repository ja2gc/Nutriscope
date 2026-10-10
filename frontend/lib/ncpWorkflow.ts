export type NcpStep = "assessment" | "diagnosis" | "intervention" | "monitoring";

type SelectionStorage = Pick<Storage, "getItem" | "setItem" | "removeItem">;
type SelectedNcpCycle = { patientId: string; ncpId: string };
const selectionKey = (userId: number | string) => `ncp:selected-cycle:${userId}`;

export function readSelectedNcpCycle(storage: SelectionStorage, userId: number | string): SelectedNcpCycle | null {
  try {
    const value = storage.getItem(selectionKey(userId));
    if (!value) return null;
    const parsed: unknown = JSON.parse(value);
    if (!parsed || typeof parsed !== "object") return null;
    const { patientId, ncpId } = parsed as Partial<SelectedNcpCycle>;
    return typeof patientId === "string" && typeof ncpId === "string"
      && /^[a-zA-Z0-9-]+$/.test(patientId) && /^[a-zA-Z0-9-]+$/.test(ncpId)
      ? { patientId, ncpId }
      : null;
  } catch {
    return null;
  }
}

export function rememberSelectedNcpCycle(storage: SelectionStorage, userId: number | string, patientId: string, ncpId: string): void {
  try {
    storage.setItem(selectionKey(userId), JSON.stringify({ patientId, ncpId }));
  } catch {
    // Navigation still works when browser storage is unavailable.
  }
}

export function clearSelectedNcpCycle(storage: SelectionStorage, userId: number | string): void {
  try {
    storage.removeItem(selectionKey(userId));
  } catch {
    // Navigation still works when browser storage is unavailable.
  }
}

export interface NcpWorkflowRecord {
  id: number | string;
  patient_id: number | string;
  type?: string | null;
  assessment?: unknown | null;
  diagnoses?: unknown[] | null;
  intervention?: unknown | null;
}

export interface NcpStepState {
  step: NcpStep;
  label: string;
  href: string;
  available: boolean;
  reason: string | null;
}

const STEP_LABELS: Record<NcpStep, string> = {
  assessment: "Assessment",
  diagnosis: "Diagnosis",
  intervention: "Intervention",
  monitoring: "Monitoring",
};

export function getPlaceholderStepHref(step: NcpStep): string {
  return `/ncp/select-patient/${step}/select-ncp`;
}

export function getCycleStepHref(patientId: number | string, step: NcpStep, ncpId: number | string): string {
  return `/ncp/${patientId}/${step}/${ncpId}`;
}

export function getNcpStepState(record: NcpWorkflowRecord, step: NcpStep): NcpStepState {
  const hasAssessment = Boolean(record.assessment);
  const hasDiagnosis = (record.diagnoses?.length ?? 0) > 0;
  const hasIntervention = Boolean(record.intervention);
  const href = getCycleStepHref(record.patient_id, step, record.id);

  if (step === "assessment") {
    return { step, label: STEP_LABELS[step], href, available: true, reason: null };
  }

  if (step === "diagnosis" && !hasAssessment) {
    return {
      step,
      label: STEP_LABELS[step],
      href,
      available: false,
      reason: "Save the assessment before starting diagnosis.",
    };
  }

  if (step === "intervention" && !hasDiagnosis) {
    return {
      step,
      label: STEP_LABELS[step],
      href,
      available: false,
      reason: hasAssessment
        ? "Save at least one diagnosis before starting intervention."
        : "Save the assessment before starting intervention.",
    };
  }

  if (step === "monitoring" && !hasIntervention) {
    return {
      step,
      label: STEP_LABELS[step],
      href,
      available: false,
      reason: "Monitoring starts on follow-up or second visit after the care plan is saved.",
    };
  }

  return { step, label: STEP_LABELS[step], href, available: true, reason: null };
}
