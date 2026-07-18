"use client";

import Link from "next/link";
import type { Patient } from "@/services/patientService";
import { personDisplayName } from "@/lib/personName";

type Props = {
  patient: Patient | null;
  physician?: string | null;
  riskScore?: number | null;
  foodDetails?: Array<string | null | undefined> | null;
  interventionGoal?: string | null;
  medicalDiagnosis?: string | null;
  onChangePatientClick?: () => void;
};

function clean(value?: string | null) {
  return value?.trim() || null;
}

function formatGoal(value?: string | null) {
  const normalized = clean(value);
  if (!normalized) return null;
  return normalized
    .replace(/_/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatRisk(score?: number | null) {
  if (score === null || score === undefined || !Number.isFinite(score)) return null;
  const label = score > 3 ? "High" : score >= 2 ? "Moderate" : "Low";
  return `${label} (${score})`;
}

export default function NcpPatientHeader({
  patient,
  physician,
  riskScore,
  foodDetails,
  interventionGoal,
  medicalDiagnosis,
  onChangePatientClick,
}: Props) {
  const context = [
    { label: "Physician", value: clean(physician) ?? clean(patient?.physician) },
    { label: "Risk", value: formatRisk(riskScore) },
    {
      label: "Foods",
      value: Array.from(new Set((foodDetails ?? []).map(clean).filter(Boolean))).join(", ") || null,
    },
    { label: "Goal", value: formatGoal(interventionGoal) },
    { label: "Medical diagnosis", value: clean(medicalDiagnosis) ?? clean(patient?.medical_diagnosis) },
  ].filter((item): item is { label: string; value: string } => Boolean(item.value));

  const actionClass =
    "inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg border border-warm-200 bg-white px-3 py-2 text-xs font-extrabold uppercase tracking-wider text-warm-600 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto";

  return (
    <section aria-label="Active patient" className="rounded-xl border border-warm-200 bg-white px-4 py-3 shadow-sm">
      <div className="flex flex-wrap items-start gap-x-4 gap-y-3 lg:flex-nowrap lg:items-center">
        <div className="min-w-0 flex-1">
          <h2 className="break-words text-base font-extrabold tracking-tight text-warm-900">
            {personDisplayName(patient, "Loading patient...")}
          </h2>
          {context.length > 0 && (
            <dl className="mt-1.5 flex min-w-0 flex-wrap gap-x-4 gap-y-1 text-xs leading-5">
              {context.map((item) => (
                <div key={item.label} className="min-w-0 break-words text-warm-600">
                  <dt className="inline font-extrabold text-warm-500">{item.label}: </dt>
                  <dd className="inline font-semibold text-warm-800">{item.value}</dd>
                </div>
              ))}
            </dl>
          )}
        </div>

        {onChangePatientClick ? (
          <button type="button" onClick={onChangePatientClick} className={actionClass}>
            Change Patient
          </button>
        ) : (
          <Link href="/ncp/patients" className={actionClass}>
            Change Patient
          </Link>
        )}
      </div>
    </section>
  );
}
