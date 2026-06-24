export type NcpStep = "assessment" | "diagnosis" | "intervention" | "monitoring";

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
