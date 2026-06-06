"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  fetchPatients,
  Patient,
  createPatient,
  createNcpRecord,
} from "@/services/patientService";
import { Button } from "@/components/ui/Button";
import { HeartHandshake } from "lucide-react";

function calculateAge(dob?: string) {
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

function formatRelativeDate(value?: string | null) {
  if (!value) {
    return "";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
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

function riskMeta(score?: number | string | null) {
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
      label: `High - ${numericScore.toFixed(1)}`,
      className: "bg-red-50 text-red-700 border-red-100",
    };
  }

  if (numericScore >= 2) {
    return {
      label: `Medium - ${numericScore.toFixed(1)}`,
      className: "bg-amber-50 text-amber-700 border-amber-100",
    };
  }

  return {
    label: `Low - ${numericScore.toFixed(1)}`,
    className: "bg-emerald-50 text-emerald-700 border-emerald-100",
  };
}

export default function NcpPatientsPage() {
  const router = useRouter();
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("All");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<any>(null);
  const [creating, setCreating] = useState(false);

  const loadPatients = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetchPatients(search, status, page);
      setPatients(response.data);
      setMeta(response.meta);
    } catch (err: any) {
      setError(err.message || "Failed to load patients.");
    } finally {
      setLoading(false);
    }
  }, [search, status, page]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadPatients();
    }, 250);

    return () => window.clearTimeout(timer);
  }, [loadPatients]);

  function handlePageChange(nextPage: number) {
    if (nextPage >= 1 && (!meta || nextPage <= meta.last_page)) {
      setPage(nextPage);
    }
  }

  const handleCreateAndAssess = async () => {
    try {
      setCreating(true);
      setError(null);
      const today = new Date().toISOString().split("T")[0];
      const newPatient = await createPatient({
        name: "New Admission (Scanning)",
        dob: "1990-01-01",
        sex: "Male",
        admission_date: today,
        status: "Active",
      });

      const newNcp = await createNcpRecord(newPatient.id);
      router.push(`/ncp/${newPatient.id}/assessment/${newNcp.id}`);
    } catch (err: any) {
      setError(err.message || "Failed to initialize assessment workflow.");
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <span>Home</span>
        <span className="text-zinc-300">/</span>
        <span>Clinical Care</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-600 font-bold">NCP Patients</span>
      </div>

      <div className="border-b border-zinc-200 pb-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <HeartHandshake className="h-5 w-5 text-emerald-600" />
            NCP Patient Profile Portal
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Create the patient record, then open the assessment page immediately to start OCR-assisted intake.
          </p>
        </div>

        <Button
          variant="primary"
          onClick={handleCreateAndAssess}
          disabled={creating}
          className="md:w-auto px-4.5 py-2.5 shrink-0 flex items-center justify-center gap-2"
        >
          {creating ? "Creating..." : "Create Patient & Start Assessment"}
        </Button>
      </div>

      <div className="flex flex-col sm:flex-row items-center gap-3 bg-white p-4 rounded-xl border border-zinc-200 shadow-sm">
        <div className="relative w-full sm:flex-1">
          <input
            type="text"
            placeholder="Search patient, physician, or ward..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            className="w-full px-4 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400"
          />
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto shrink-0 select-none">
          <select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
            className="w-full sm:w-40 px-3 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all cursor-pointer font-semibold"
          >
            <option value="All">All Statuses</option>
            <option value="Active">Active Care</option>
            <option value="Discharged">Discharged</option>
            <option value="Transferred">Transferred</option>
          </select>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-start gap-3">
          <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-[10px] font-black text-red-600 shrink-0 mt-0.5">
            !
          </span>
          <div className="text-xs text-red-700 font-bold">{error}</div>
        </div>
      )}

      <div className="bg-white border border-zinc-200 rounded-2xl overflow-hidden shadow-sm">
        {loading ? (
          <div className="p-8 space-y-4">
            <div className="h-5 w-40 bg-zinc-200 rounded-lg animate-pulse" />
            <div className="space-y-2 pt-4">
              {[1, 2, 3, 4].map((index) => (
                <div key={index} className="flex gap-4 h-12 items-center">
                  <div className="flex-1 bg-zinc-100 rounded-lg h-8 animate-pulse" />
                  <div className="w-24 bg-zinc-100 rounded-lg h-8 animate-pulse" />
                  <div className="w-28 bg-zinc-100 rounded-lg h-8 animate-pulse" />
                </div>
              ))}
            </div>
          </div>
        ) : patients.length === 0 ? (
          <div className="p-12 text-center select-none">
            <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
              <HeartHandshake className="h-8 w-8" />
            </div>
            <h3 className="text-sm font-bold text-zinc-800 mt-4">No Patients Found</h3>
            <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
              No patient records match the current filters.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[480px]">
              <thead>
                <tr className="bg-zinc-50 border-b border-zinc-200 select-none">
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Name / ID</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Age / Sex</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Physician</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Last Assessment</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Next Follow-up</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Risk Status</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {patients.map((patient, index) => {
                  const systemId = `NS-${String(patient.id).padStart(5, "0")}`;
                  const currentRisk = riskMeta(patient.risk_score);
                  const age = calculateAge(patient.dob);

                  return (
                    <tr
                      key={patient.id}
                      className={`${index % 2 === 0 ? "bg-white" : "bg-zinc-50/20"} hover:bg-zinc-50/40 transition-colors`}
                    >
                      <td className="px-5 py-4">
                        <div className="text-xs font-bold text-zinc-900">{patient.name}</div>
                        <div className="text-[10px] font-mono text-zinc-400 mt-1">{systemId}</div>
                      </td>

                      <td className="px-5 py-4 text-xs font-medium text-zinc-700">
                        {age} yrs / {patient.sex}
                      </td>

                      <td className="px-5 py-4 text-xs font-semibold text-zinc-650">
                        {patient.physician || "Unassigned"}
                      </td>

                      <td className="px-5 py-4 text-xs text-zinc-600">
                        {patient.last_assessment_date ? (
                          <span className="font-semibold text-zinc-700">
                            {formatRelativeDate(patient.last_assessment_date)}
                          </span>
                        ) : (
                          <span className="text-zinc-400">No assessment yet</span>
                        )}
                      </td>

                      <td className="px-5 py-4 text-xs text-zinc-600">
                        {patient.next_followup_date ? (
                          <span className="font-semibold text-zinc-700">
                            {formatRelativeDate(patient.next_followup_date)}
                          </span>
                        ) : (
                          <span className="text-zinc-400">Not scheduled</span>
                        )}
                      </td>

                      <td className="px-5 py-4 select-none">
                        <div className="flex flex-col items-start gap-1">
                          <span
                            className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide border ${currentRisk.className}`}
                          >
                            {currentRisk.label}
                          </span>
                        </div>
                      </td>

                      <td className="px-5 py-4 text-right">
                        <Link
                          href={`/ncp/patients/${patient.id}`}
                          className="inline-flex px-3 py-1.5 bg-zinc-950 hover:bg-zinc-800 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer select-none"
                        >
                          View Profile
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="px-5 py-4 border-t border-zinc-100 bg-zinc-50 flex items-center justify-between select-none">
            <span className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
              Showing Page {meta.current_page} of {meta.last_page} ({meta.total} Total)
            </span>
            <div className="flex gap-1.5">
              <button
                onClick={() => handlePageChange(page - 1)}
                disabled={page === 1}
                className="px-3 py-1.5 border border-zinc-300 bg-white text-zinc-600 rounded-lg hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors text-[10px] font-bold uppercase tracking-wider"
                title="Previous Page"
              >
                Prev
              </button>
              <button
                onClick={() => handlePageChange(page + 1)}
                disabled={page === meta.last_page}
                className="px-3 py-1.5 border border-zinc-300 bg-white text-zinc-600 rounded-lg hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors text-[10px] font-bold uppercase tracking-wider"
                title="Next Page"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
