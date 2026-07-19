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
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { formatPatientAge } from "@/lib/patientAge";
import { personDisplayName, requiredPersonNameFields } from "@/lib/personName";
import { DatePicker } from "@/components/ui/DatePicker";
import { HeartHandshake, X } from "lucide-react";
import { ClinicalAttribution } from "@/components/ncp/ClinicalAttribution";

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
      className: "bg-warm-50 text-warm-600 border-warm-200",
    };
  }

  const numericScore = Number(score);
  if (!Number.isFinite(numericScore)) {
    return {
      label: "Unscored",
      className: "bg-warm-50 text-warm-600 border-warm-200",
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
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [creating, setCreating] = useState(false);

  // Create patient modal
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newFirstName, setNewFirstName] = useState("");
  const [newLastName, setNewLastName] = useState("");
  const [newSex, setNewSex] = useState<"Male" | "Female">("Female");
  const [newDob, setNewDob] = useState("");
  const [createError, setCreateError] = useState<string | null>(null);

  const loadPatients = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetchPatients(search, status, page);
      setPatients(response.data);
      setMeta(response.meta ?? null);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Failed to load patients.");
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

  const handleCreateAndAssess = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!newFirstName.trim() || !newLastName.trim() || !newDob) {
      setCreateError("Please fill in the patient's first name, last name, and date of birth.");
      return;
    }
    setCreateError(null);
    setCreating(true);
    try {
      const nameFields = requiredPersonNameFields(newFirstName, newLastName);
      const today = new Date().toISOString().split("T")[0];
      const newPatient = await createPatient({
        ...nameFields,
        dob: newDob,
        sex: newSex,
        admission_date: today,
        status: "Active",
      });
      const newNcp = await createNcpRecord(newPatient.id);
      router.push(`/ncp/${newPatient.id}/assessment/${newNcp.id}`);
    } catch (err: unknown) {
      setCreateError(err instanceof Error ? err.message : "Failed to initialize assessment workflow.");
      setCreating(false);
    }
  };

  function openCreateModal() {
    setNewFirstName("");
    setNewLastName("");
    setNewSex("Female");
    setNewDob("");
    setCreateError(null);
    setShowCreateModal(true);
  }

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <span>Home</span>
        <span className="text-warm-300">/</span>
        <span>Clinical Care</span>
        <span className="text-warm-300">/</span>
        <span className="text-warm-600 font-bold">NCP Patients</span>
      </div>

      <div className="border-b border-warm-200 pb-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
            <HeartHandshake className="h-5 w-5 text-emerald-600" />
            NCP Patient Profile Portal
          </h2>
          <p className="text-sm text-warm-500 mt-1 select-none">
            Create the patient record, then open the assessment page immediately to start OCR-assisted intake.
          </p>
        </div>

        <Button
          variant="primary"
          onClick={openCreateModal}
          disabled={creating}
          className="md:w-auto px-4.5 py-2.5 shrink-0 flex items-center justify-center gap-2"
        >
          Create Patient & Start Assessment
        </Button>
      </div>

      <div className="flex flex-col sm:flex-row items-center gap-3 bg-white p-4 rounded-xl border border-warm-200 shadow-sm">
        <div className="relative w-full sm:flex-1">
          <input
            type="text"
            placeholder="Search patient, physician, or ward..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            className="w-full px-4 py-2 text-base bg-white border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-warm-400"
          />
        </div>

        <div className="flex items-center gap-2 w-full sm:w-auto shrink-0 select-none">
          <select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
            className="w-full sm:w-40 px-3 py-2 text-base bg-white border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all cursor-pointer font-semibold"
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
          <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-xs font-black text-red-600 shrink-0 mt-0.5">
            !
          </span>
          <div className="text-sm text-red-700 font-bold">{error}</div>
        </div>
      )}

      <div className="bg-white border border-warm-200 rounded-2xl overflow-hidden shadow-sm">
        {loading ? (
          <div className="p-8 space-y-4">
            <div className="h-5 w-40 bg-warm-200 rounded-lg animate-pulse" />
            <div className="space-y-2 pt-4">
              {[1, 2, 3, 4].map((index) => (
                <div key={index} className="flex gap-4 h-12 items-center">
                  <div className="flex-1 bg-warm-100 rounded-lg h-8 animate-pulse" />
                  <div className="w-24 bg-warm-100 rounded-lg h-8 animate-pulse" />
                  <div className="w-28 bg-warm-100 rounded-lg h-8 animate-pulse" />
                </div>
              ))}
            </div>
          </div>
        ) : patients.length === 0 ? (
          <div className="p-12 text-center select-none">
            <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
              <HeartHandshake className="h-8 w-8" />
            </div>
            <h3 className="text-base font-bold text-warm-800 mt-4">No Patients Found</h3>
            <p className="text-sm text-warm-500 mt-1 max-w-sm mx-auto leading-relaxed">
              No patient records match the current filters.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[1080px]">
              <thead>
                <tr className="bg-warm-50 border-b border-warm-200 select-none">
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Name / ID</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Age / Sex</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Physician</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Last Assessment</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Next Follow-up</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Risk Status</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider">Clinical Attribution</th>
                  <th className="px-5 py-4 text-xs font-extrabold text-warm-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {patients.map((patient, index) => {
                  const systemId = `NS-${String(patient.id).padStart(5, "0")}`;
                  const currentRisk = riskMeta(patient.risk_score);
                  const age = formatPatientAge(patient.dob);

                  return (
                    <tr
                      key={patient.id}
                      className={`${index % 2 === 0 ? "bg-white" : "bg-warm-50/20"} hover:bg-warm-50/40 transition-colors`}
                    >
                      <td className="px-5 py-4">
                        <div className="text-sm font-bold text-warm-900">{personDisplayName(patient)}</div>
                        <div className="text-xs font-mono text-warm-400 mt-1">{systemId}</div>
                      </td>

                      <td className="px-5 py-4 text-sm font-medium text-warm-700">
                        {age} / {patient.sex}
                      </td>

                      <td className="px-5 py-4 text-sm font-semibold text-zinc-650">
                        {patient.physician || "Unassigned"}
                      </td>

                      <td className="px-5 py-4 text-sm text-warm-600">
                        {patient.last_assessment_date ? (
                          <span className="font-semibold text-warm-700">
                            {formatRelativeDate(patient.last_assessment_date)}
                          </span>
                        ) : (
                          <span className="text-warm-400">No assessment yet</span>
                        )}
                      </td>

                      <td className="px-5 py-4 text-sm text-warm-600">
                        {patient.next_followup_date ? (
                          <span className="font-semibold text-warm-700">
                            {formatRelativeDate(patient.next_followup_date)}
                          </span>
                        ) : (
                          <span className="text-warm-400">Not scheduled</span>
                        )}
                      </td>

                      <td className="px-5 py-4 select-none">
                        <div className="flex flex-col items-start gap-1">
                          <span
                            className={`inline-flex px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide border ${currentRisk.className}`}
                          >
                            {currentRisk.label}
                          </span>
                        </div>
                      </td>

                      <td className="px-5 py-4">
                        <ClinicalAttribution
                          creator={patient.latest_ncp_created_by}
                          lastAction={patient.last_clinical_action}
                          formatDate={formatRelativeDate}
                        />
                      </td>

                      <td className="px-5 py-4 text-right">
                        <Link
                          href={`/ncp/patients/${patient.id}`}
                          className="inline-flex px-3 py-1.5 bg-brand-green-600 hover:bg-brand-green-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer select-none"
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

        {/* ── Create patient modal ─────────────────────────────────────────────── */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <form
            onSubmit={handleCreateAndAssess}
            role="dialog"
            aria-modal="true"
            aria-labelledby="new-patient-title"
            className="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6 space-y-5"
          >
            <div className="flex items-center justify-between">
              <h3 id="new-patient-title" className="text-base font-extrabold text-warm-900 tracking-tight">New Patient Details</h3>
              <button
                type="button"
                onClick={() => setShowCreateModal(false)}
                aria-label="Close new patient form"
                className="min-h-11 min-w-11 p-1.5 text-warm-400 hover:text-warm-700 hover:bg-warm-100 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1">
                <label htmlFor="patient-first-name" className="text-xs font-bold text-warm-500 uppercase tracking-wider block">First Name</label>
                <input
                  id="patient-first-name"
                  type="text"
                  required
                  value={newFirstName}
                  onChange={(e) => setNewFirstName(e.target.value)}
                  placeholder="Maria Luisa"
                  autoFocus
                  className="min-h-11 w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 focus-visible:ring-2 transition-all placeholder:text-warm-400"
                />
              </div>
              <div className="space-y-1">
                <label htmlFor="patient-last-name" className="text-xs font-bold text-warm-500 uppercase tracking-wider block">Last Name</label>
                <input
                  id="patient-last-name"
                  type="text"
                  required
                  value={newLastName}
                  onChange={(e) => setNewLastName(e.target.value)}
                  placeholder="De la Cruz"
                  className="min-h-11 w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-lg text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 focus-visible:ring-2 transition-all placeholder:text-warm-400"
                />
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-xs font-bold text-warm-500 uppercase tracking-wider block">Sex</label>
              <div className="flex gap-2">
                {(["Female", "Male"] as const).map((s) => (
                  <button
                    key={s}
                    type="button"
                    onClick={() => setNewSex(s)}
                    className={`min-h-11 flex-1 py-2 text-sm font-bold rounded-lg border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${
                      newSex === s
                        ? "bg-emerald-600 text-white border-emerald-600"
                        : "bg-white text-warm-600 border-warm-300 hover:bg-warm-50"
                    }`}
                  >
                    {s}
                  </button>
                ))}
              </div>
            </div>

            <div className="space-y-1">
              <DatePicker label="Date of Birth" required value={newDob} onChange={setNewDob} max={new Date().toISOString().slice(0, 10)} />
            </div>

            {createError && (
              <p className="text-sm text-red-600 font-semibold">{createError}</p>
            )}

            <div className="flex gap-2 pt-1">
              <button
                type="button"
                onClick={() => setShowCreateModal(false)}
                disabled={creating}
                className="min-h-11 flex-1 py-2.5 text-sm font-bold uppercase tracking-wider rounded-xl border border-warm-300 text-warm-600 hover:bg-warm-50 transition-colors disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={creating || !newFirstName.trim() || !newLastName.trim() || !newDob}
                className="min-h-11 flex-1 py-2.5 text-sm font-bold uppercase tracking-wider rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
              >
                {creating ? "Creating…" : "Create & Assess"}
              </button>
            </div>
          </form>
        </div>
      )}

      {meta && <Pagination meta={meta} page={page} onPageChange={setPage} />}
      </div>
    </div>
  );
}
