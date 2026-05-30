"use client";

import React, { use, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  createNcpRecord,
  fetchPatientById,
  fetchPatientNcpRecords,
  NcpRecord,
  Patient,
} from "@/services/patientService";
import { Button } from "@/components/ui/Button";
import {
  Activity,
  HeartHandshake,
} from "lucide-react";

type TabKey = "overview" | "adime-records";

function formatSystemId(id: number) {
  return `NS-${String(id).padStart(5, "0")}`;
}

function formatCycleId(id: number) {
  return `NCP-${String(id).padStart(5, "0")}`;
}

function formatAge(dob?: string | null) {
  if (!dob) {
    return "N/A";
  }

  const birthDate = new Date(dob);
  if (Number.isNaN(birthDate.getTime())) {
    return "N/A";
  }

  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDelta = today.getMonth() - birthDate.getMonth();

  if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < birthDate.getDate())) {
    age -= 1;
  }

  return age;
}

function formatAbsoluteDate(value?: string | null) {
  if (!value) {
    return "Not yet completed";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "Not yet completed";
  }

  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatRelativeDate(value?: string | null) {
  if (!value) {
    return "Not yet completed";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "Not yet completed";
  }

  const diffDays = Math.round((date.getTime() - Date.now()) / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return "Today";
  }

  if (diffDays > 0) {
    return diffDays === 1 ? "In 1 day" : `In ${diffDays} days`;
  }

  const absDays = Math.abs(diffDays);
  return absDays === 1 ? "1 day ago" : `${absDays} days ago`;
}

function formatRiskLabel(score?: number | string | null) {
  if (score === null || score === undefined || score === "") {
    return {
      label: "Unscored",
      className: "bg-zinc-50 text-zinc-600 border-zinc-200",
    };
  }

  const numericScore = Number(score);
  if (!Number.isFinite(numericScore)) {
    return {
      label: "Unscored",
      className: "bg-zinc-50 text-zinc-600 border-zinc-200",
    };
  }

  if (numericScore >= 4) {
    return {
      label: `High · ${numericScore.toFixed(1)}`,
      className: "bg-red-50 text-red-700 border-red-100",
    };
  }

  if (numericScore >= 2) {
    return {
      label: `Medium · ${numericScore.toFixed(1)}`,
      className: "bg-amber-50 text-amber-700 border-amber-100",
    };
  }

  return {
    label: `Low · ${numericScore.toFixed(1)}`,
    className: "bg-emerald-50 text-emerald-700 border-emerald-100",
  };
}

function formatStatus(status?: string | null) {
  switch ((status || "").toLowerCase()) {
    case "active":
      return { label: "Active", className: "bg-emerald-50 text-emerald-700 border-emerald-100" };
    case "completed":
      return { label: "Completed", className: "bg-zinc-100 text-zinc-600 border-zinc-200" };
    case "discharged":
      return { label: "Discharged", className: "bg-orange-50 text-orange-700 border-orange-100" };
    default:
      return { label: "Draft", className: "bg-zinc-50 text-zinc-500 border-zinc-200" };
  }
}

function resolveLatestRecord(records: NcpRecord[]) {
  return records[0] ?? null;
}

export default function PatientProfilePage({
  params,
}: {
  params: Promise<{ patientId: string }>;
}) {
  const router = useRouter();
  const { patientId } = use(params);

  const [patient, setPatient] = useState<Patient | null>(null);
  const [records, setRecords] = useState<NcpRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabKey>("overview");
  const [startingCycle, setStartingCycle] = useState(false);

  useEffect(() => {
    async function loadData() {
      try {
        setLoading(true);
        setError(null);

        const [patientData, recordsData] = await Promise.all([
          fetchPatientById(patientId),
          fetchPatientNcpRecords(patientId),
        ]);

        setPatient(patientData);
        setRecords(recordsData);
      } catch (err: any) {
        setError(err.message || "Failed to load patient profile.");
      } finally {
        setLoading(false);
      }
    }

    void loadData();
  }, [patientId]);

  const latestRecord = resolveLatestRecord(records);
  const allergies = latestRecord?.assessment?.allergies ?? [];
  const riskMeta = formatRiskLabel(patient?.risk_score);
  const latestAssessment = latestRecord?.assessment?.rnd_summary?.trim();
  const latestDiagnosis = latestRecord?.diagnoses?.[0]?.pes_statement?.trim();
  const latestIntervention = latestRecord?.intervention?.goal_type?.trim();
  const latestMonitoring = latestRecord?.intervention?.next_followup_date;

  const handleStartCycle = async () => {
    if (!patient) {
      return;
    }

    try {
      setStartingCycle(true);
      const created = await createNcpRecord(patient.id);
      if (!created?.id) {
        throw new Error("The new NCP cycle did not return an ID.");
      }

      router.push(`/ncp/${patient.id}/assessment/${created.id}`);
    } catch (err: any) {
      setError(err.message || "Failed to start NCP cycle.");
    } finally {
      setStartingCycle(false);
    }
  };

  if (loading) {
    return (
      <div className="space-y-6 font-sans">
        <div className="h-4 w-48 bg-zinc-200 rounded-lg animate-pulse" />
        <div className="h-32 bg-zinc-200 rounded-2xl animate-pulse" />
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 h-96 bg-zinc-200 rounded-2xl animate-pulse" />
          <div className="h-96 bg-zinc-200 rounded-2xl animate-pulse" />
        </div>
      </div>
    );
  }

  if (error || !patient) {
    return (
      <div className="space-y-6 font-sans max-w-3xl mx-auto py-12 select-none">
        <div className="bg-red-50 border border-red-100 p-6 rounded-2xl text-center space-y-4">
          <span className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-red-200 text-lg font-black text-red-600 mx-auto">
            !
          </span>
          <h3 className="text-sm font-bold text-zinc-900 uppercase tracking-wider">
            Patient Profile Error
          </h3>
          <p className="text-xs text-zinc-500 max-w-sm mx-auto leading-relaxed">
            {error || "The requested patient record could not be found."}
          </p>
          <Link
            href="/ncp/patients"
            className="inline-flex px-4 py-2 bg-zinc-950 hover:bg-zinc-800 text-white font-semibold text-xs rounded-lg transition-all"
          >
            Return to Directory
          </Link>
        </div>
      </div>
    );
  }

  const systemId = formatSystemId(patient.id);
  const age = formatAge(patient.dob);

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">
          Patients
        </Link>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">{patient.name}</span>
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl p-5.5 shadow-sm space-y-4">
        <div className="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2.5">
              <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight">
                {patient.name}
              </h2>
              <span className="text-xs font-mono px-2 py-0.5 bg-zinc-100 text-zinc-600 border border-zinc-200 rounded-lg">
                {systemId}
              </span>
              <span
                className={`px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${formatStatus(patient.status).className}`}
              >
                {formatStatus(patient.status).label}
              </span>
            </div>

            <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-zinc-500 font-semibold">
              <span>{age} years / {patient.sex}</span>
              <span>{patient.ward || "No ward assigned"}</span>
              <span>{patient.physician || "Unassigned physician"}</span>
            </div>

            <div className="text-xs text-zinc-700 font-medium">
              <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block mb-1">
                Primary medical diagnosis
              </span>
              <span className="text-zinc-800 leading-relaxed font-semibold">
                {patient.medical_diagnosis || "Not recorded"}
              </span>
            </div>
          </div>

          <div className="min-w-[240px] shrink-0 space-y-2">
            <div className="flex flex-wrap gap-2">
              <span className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${riskMeta.className}`}>
                {riskMeta.label}
              </span>
              {patient.risk_score !== null &&
                patient.risk_score !== undefined &&
                patient.risk_score !== "" && (
                  <span className="inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-zinc-50 text-zinc-500 border-zinc-200">
                    System {Number(patient.risk_score).toFixed(1)}
                  </span>
                )}
            </div>

            {allergies.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {allergies.map((allergy) => (
                  <span
                    key={allergy}
                    className="inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-red-50 text-red-700 border-red-100"
                  >
                    Allergy: {allergy}
                  </span>
                ))}
              </div>
            )}

              <Button
                variant="primary"
                onClick={() => void handleStartCycle()}
                loading={startingCycle}
                className="w-full px-4 py-2.5 text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer transition-all duration-150"
              >
                <Activity className="h-4 w-4" />
                Start NCP Cycle
              </Button>
          </div>
        </div>
      </div>

      <div className="border-b border-zinc-200 select-none">
        <nav className="flex space-x-6">
          <button
            onClick={() => setActiveTab("overview")}
            className={`pb-4 text-xs font-extrabold uppercase tracking-wider border-b-2 transition-all ${
              activeTab === "overview"
                ? "border-emerald-600 text-emerald-800"
                : "border-transparent text-zinc-400 hover:text-zinc-600"
            }`}
          >
            Overview
          </button>
          <button
            onClick={() => setActiveTab("adime-records")}
            className={`pb-4 text-xs font-extrabold uppercase tracking-wider border-b-2 transition-all ${
              activeTab === "adime-records"
                ? "border-emerald-600 text-emerald-800"
                : "border-transparent text-zinc-400 hover:text-zinc-600"
            }`}
          >
            ADIME Records
          </button>
        </nav>
      </div>

      {activeTab === "overview" ? (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          <div className="lg:col-span-2 space-y-6">
            <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden">
              <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50">
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                  <HeartHandshake className="h-4.5 w-4.5 text-emerald-600" />
                  Patient Profile
                </h3>
              </div>

              <div className="p-5.5 grid grid-cols-1 sm:grid-cols-2 gap-y-4.5 gap-x-6 text-xs">
                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Date of Birth
                  </span>
                  <span className="text-zinc-800 font-semibold">
                    {formatAbsoluteDate(patient.dob)}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Admission Date
                  </span>
                  <span className="text-zinc-800 font-semibold">
                    {formatAbsoluteDate(patient.admission_date)}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Contact Number
                  </span>
                  <span className="text-zinc-800 font-mono font-semibold">
                    {patient.contact || "Not documented"}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Religion
                  </span>
                  <span className="text-zinc-800 font-semibold">
                    {patient.religion || "Not documented"}
                  </span>
                </div>

                <div className="sm:col-span-2 space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Home Address
                  </span>
                  <span className="text-zinc-700 font-semibold leading-relaxed">
                    {patient.address || "Not documented"}
                  </span>
                </div>
              </div>
            </div>

            <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden">
              <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50">
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                  NCP Entry Notes
                </h3>
              </div>

              <div className="p-5.5 space-y-4 text-xs text-zinc-600 leading-relaxed">
                <p>
                  This profile is the entry portal for the Nutrition Care Process. Review the demographics above, then start or continue a cycle from the header button.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl">
                    <span className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider block">
                      Referring Physician
                    </span>
                    <span className="mt-1 block font-semibold text-zinc-800">
                      {patient.physician || "Unassigned"}
                    </span>
                  </div>
                  <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl">
                    <span className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider block">
                      Latest Assessment
                    </span>
                    <span className="mt-1 block font-semibold text-zinc-800">
                      {latestAssessment ? formatRelativeDate(latestRecord?.updated_at) : "No assessment yet"}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white border border-zinc-200 rounded-2xl p-6">
              <h3 className="text-xs font-extrabold text-zinc-900 uppercase tracking-wider mb-3">
                Current Cycle Snapshot
              </h3>
              {latestRecord ? (
                <div className="space-y-3.5 pt-2">
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-semibold text-zinc-500">Latest NCP Cycle</span>
                    <span className="px-2 py-0.5 rounded-lg text-[9px] font-extrabold uppercase tracking-wider border bg-zinc-50 text-zinc-600 border-zinc-200">
                      {formatStatus(latestRecord.status).label}
                    </span>
                  </div>
                  <div className="text-xs text-zinc-600">
                    <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">
                      Cycle ID
                    </span>
                    <span className="font-mono font-semibold text-zinc-800">
                      {formatCycleId(latestRecord.id)}
                    </span>
                  </div>
                  <div className="text-xs text-zinc-600">
                    <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block mb-1">
                      Next Follow-up
                    </span>
                    <span className="font-semibold text-zinc-800">
                      {formatAbsoluteDate(latestMonitoring)}
                    </span>
                  </div>
                </div>
              ) : (
                <p className="text-xs text-zinc-500 leading-relaxed">
                  No NCP cycles have been started for this patient yet.
                </p>
              )}
            </div>
          </div>
        </div>
      ) : (
        <div className="space-y-5">
          {records.length === 0 ? (
            <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center select-none">
              <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
                <HeartHandshake className="h-8 w-8" />
              </div>
              <h3 className="text-sm font-bold text-zinc-800 mt-4">No NCP cycles initiated</h3>
              <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
                Start the first cycle from the patient header to begin assessment, diagnosis, intervention, and monitoring.
              </p>
              <Button
                variant="primary"
                onClick={() => void handleStartCycle()}
                loading={startingCycle}
                className="mt-6 w-auto px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5 cursor-pointer rounded-lg mx-auto"
              >
                Initialize First NCP Cycle
              </Button>
            </div>
          ) : (
            <div className="space-y-4">
              {records.map((record) => {
                const cycleStatus = formatStatus(record.status);
                const cycleId = formatCycleId(record.id);
                const assessmentSummary = record.assessment?.rnd_summary?.trim() || "Not yet completed";
                const diagnosisSummary =
                  record.diagnoses?.[0]?.pes_statement?.trim() || "Not yet completed";
                const interventionSummary = record.intervention?.goal_type?.trim() || "Not yet completed";
                const monitoringSummary = record.intervention?.next_followup_date
                  ? formatAbsoluteDate(record.intervention.next_followup_date)
                  : "Not yet completed";

                return (
                  <div key={record.id} className="bg-white border border-zinc-200 rounded-2xl overflow-hidden">
                    <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between gap-4 bg-zinc-50">
                      <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="text-sm font-extrabold text-zinc-950 tracking-tight">
                            {cycleId}
                          </span>
                          <span className={`px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${cycleStatus.className}`}>
                            {cycleStatus.label}
                          </span>
                        </div>
                        <p className="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">
                          Created {formatAbsoluteDate(record.created_at)}
                        </p>
                      </div>

                      <div className="text-right text-xs text-zinc-500">
                        <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                          Referring Physician
                        </span>
                        <span className="font-semibold text-zinc-700">
                          {patient.physician || "Unassigned"}
                        </span>
                      </div>
                    </div>

                    <div className="p-5.5 grid grid-cols-1 lg:grid-cols-2 gap-5">
                      <div className="space-y-4">
                        <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl">
                          <span className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider block">
                            Assessment Summary
                          </span>
                          <p className="mt-1 text-xs text-zinc-700 leading-relaxed">
                            {assessmentSummary}
                          </p>
                        </div>
                        <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl">
                          <span className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider block">
                            Diagnosis Summary
                          </span>
                          <p className="mt-1 text-xs text-zinc-700 leading-relaxed">
                            {diagnosisSummary}
                          </p>
                        </div>
                      </div>

                      <div className="space-y-4">
                        <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl">
                          <span className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider block">
                            Intervention Summary
                          </span>
                          <p className="mt-1 text-xs text-zinc-700 leading-relaxed">
                            {interventionSummary}
                          </p>
                        </div>
                        <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl">
                          <span className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-wider block">
                            Monitoring Summary
                          </span>
                          <p className="mt-1 text-xs text-zinc-700 leading-relaxed">
                            {monitoringSummary}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div className="px-5.5 pb-5.5">
                      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        <Link
                          href={`/ncp/${patient.id}/assessment/${record.id}`}
                          className="inline-flex items-center justify-center gap-2 px-3 py-2 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-zinc-200 text-zinc-700 hover:bg-zinc-50 transition-colors"
                        >
                          Assessment
                        </Link>
                        <Link
                          href={`/ncp/${patient.id}/diagnosis/${record.id}`}
                          className="inline-flex items-center justify-center gap-2 px-3 py-2 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-zinc-200 text-zinc-700 hover:bg-zinc-50 transition-colors"
                        >
                          Diagnosis
                        </Link>
                        <Link
                          href={`/ncp/${patient.id}/intervention/${record.id}`}
                          className="inline-flex items-center justify-center gap-2 px-3 py-2 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-zinc-200 text-zinc-700 hover:bg-zinc-50 transition-colors"
                        >
                          Intervention
                        </Link>
                        <Link
                          href={`/ncp/${patient.id}/monitoring/${record.id}`}
                          className="inline-flex items-center justify-center gap-2 px-3 py-2 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-zinc-200 text-zinc-700 hover:bg-zinc-50 transition-colors"
                        >
                          Monitoring
                        </Link>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
