"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { fetchActiveAppointment, type NcpAppointment } from "@/services/ncpAppointmentService";
import { personDisplayName } from "@/lib/personName";

export function ActiveVisitBanner() {
  const [visit, setVisit] = useState<NcpAppointment | null>(null);
  const load = useCallback(() => {
    void fetchActiveAppointment().then(setVisit).catch(() => setVisit(null));
  }, []);

  useEffect(() => {
    load();
    window.addEventListener("ncp-visit-changed", load);
    window.addEventListener("focus", load);
    return () => {
      window.removeEventListener("ncp-visit-changed", load);
      window.removeEventListener("focus", load);
    };
  }, [load]);

  if (!visit) return null;
  const href = visit.ncp_record_id
    ? `/ncp/${visit.patient_id}/assessment/${visit.ncp_record_id}`
    : `/ncp/patients/${visit.patient_id}`;

  return <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-center text-sm text-emerald-900">Visit in progress for <strong>{personDisplayName(visit.patient, "patient")}</strong> · {visit.purpose} <Link href={href} className="ml-2 font-extrabold underline">Resume</Link></div>;
}
