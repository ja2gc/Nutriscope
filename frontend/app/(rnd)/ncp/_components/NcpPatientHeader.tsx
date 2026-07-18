"use client";

import Link from "next/link";
import { ArrowLeftRight, Heart } from "lucide-react";
import type { ReactNode } from "react";
import type { Patient } from "@/services/patientService";
import { personDisplayName } from "@/lib/personName";

type Props = {
  patient: Patient | null;
  patientId: number | string;
  ncpId: number | string;
  stepLabel: string;
  badges?: ReactNode;
  onChangePatientClick?: () => void;
};

function formatSystemId(id: number | string) {
  return `NS-${String(id).padStart(5, "0")}`;
}

function formatCycleId(id: number | string) {
  return `NCP-${String(id).padStart(5, "0")}`;
}

function compactRecordId(id: string) {
  const [prefix, value] = id.split("-", 2);
  return value && id.length > 18 ? `${prefix}-${value.slice(0, 8)}…` : id;
}

export default function NcpPatientHeader({
  patient,
  patientId,
  ncpId,
  stepLabel,
  badges,
  onChangePatientClick,
}: Props) {
  const systemId = formatSystemId(patient?.id ?? patientId);
  const cycleId = formatCycleId(ncpId);
  const visibleIds = `${compactRecordId(systemId)} / ${compactRecordId(cycleId)}`;
  const actionClass =
    "inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-1.5 rounded-lg border border-warm-200 bg-white px-3 py-2 text-xs font-extrabold uppercase tracking-wider text-warm-600 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 sm:w-auto";

  return (
    <div className="rounded-xl border border-warm-200 bg-white px-4 py-2.5 shadow-sm">
      <div className="flex flex-wrap items-center gap-2.5 lg:flex-nowrap">
        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2.5 lg:flex-nowrap">
          <div className="flex min-w-0 items-center gap-2.5">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-emerald-100 bg-emerald-50 text-emerald-700">
              <Heart className="h-4 w-4" />
            </div>
            <div className="flex min-w-0 flex-1 flex-nowrap items-baseline gap-x-2 overflow-hidden">
              <p className="shrink-0 text-xs font-extrabold uppercase tracking-wider text-warm-400">
                {stepLabel}
              </p>
              <h2 className="shrink-0 truncate text-base font-extrabold tracking-tight text-warm-900">
                {personDisplayName(patient, "Loading patient...")}
              </h2>
              <p
                title={`${systemId} / ${cycleId}`}
                className="min-w-0 max-w-72 flex-1 truncate text-xs font-mono text-warm-400"
              >
                {visibleIds}
              </p>
            </div>
          </div>

          <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2 text-xs lg:flex-nowrap lg:whitespace-nowrap">
            {patient?.ward && (
              <span className="shrink-0 rounded bg-warm-100 px-2 py-0.5 font-bold text-warm-700">
                Ward: {patient.ward}
              </span>
            )}
            {patient?.medical_diagnosis && (
              <details className="relative shrink-0">
                <summary
                  title={patient.medical_diagnosis}
                  className="flex min-h-11 cursor-pointer list-none items-center rounded border border-sky-100 bg-sky-50 px-2 font-bold text-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                >
                  Dx
                </summary>
                <div className="absolute left-0 top-full z-20 mt-1 w-72 max-w-[80vw] whitespace-normal rounded-lg border border-sky-100 bg-white p-3 text-xs font-semibold leading-relaxed text-warm-700 shadow-lg">
                  {patient.medical_diagnosis}
                </div>
              </details>
            )}
            {badges}
          </div>
        </div>

        {onChangePatientClick ? (
          <button type="button" onClick={onChangePatientClick} className={actionClass}>
            <ArrowLeftRight className="h-3.5 w-3.5" />
            Change Patient
          </button>
        ) : (
          <Link href="/ncp/patients" className={actionClass}>
            <ArrowLeftRight className="h-3.5 w-3.5" />
            Change Patient
          </Link>
        )}
      </div>
    </div>
  );
}
