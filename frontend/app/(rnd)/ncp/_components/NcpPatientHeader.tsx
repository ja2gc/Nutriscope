"use client";

import Link from "next/link";
import { ArrowLeftRight, Heart } from "lucide-react";
import type { ReactNode } from "react";
import type { Patient } from "@/services/patientService";

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
  const actionClass =
    "inline-flex items-center gap-1.5 rounded-lg border border-warm-200 bg-white px-3 py-2 text-[10px] font-extrabold uppercase tracking-wider text-warm-600 transition-colors hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700";

  return (
    <div className="bg-white border border-warm-200 rounded-xl px-5 py-3.5 shadow-sm">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
          <div className="flex items-center gap-2.5">
            <div className="h-8 w-8 bg-emerald-50 border border-emerald-100 rounded-lg flex items-center justify-center text-emerald-700">
              <Heart className="h-4 w-4" />
            </div>
            <div>
              <p className="text-[9px] font-extrabold uppercase tracking-wider text-warm-400">
                Active Patient - {stepLabel}
              </p>
              <h2 className="text-sm font-extrabold text-warm-900 tracking-tight">
                {patient?.name ?? "Loading patient..."}
              </h2>
              <p className="text-[10px] font-mono text-warm-400">
                {systemId} / {cycleId}
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 text-[10px]">
            {patient?.ward && (
              <span className="px-2 py-0.5 bg-warm-100 text-warm-700 rounded font-bold">
                Ward: {patient.ward}
              </span>
            )}
            {patient?.medical_diagnosis && (
              <span className="px-2 py-0.5 bg-sky-50 text-sky-700 border border-sky-100 rounded font-bold">
                Dx: {patient.medical_diagnosis}
              </span>
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
