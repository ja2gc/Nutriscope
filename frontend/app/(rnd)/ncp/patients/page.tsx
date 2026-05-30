"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  createNcpRecord,
  createPatient,
  fetchPatients,
  Patient,
  PatientStoreData,
} from "@/services/patientService";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
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
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [formData, setFormData] = useState<PatientStoreData>({
    name: "",
    dob: "",
    sex: "Male",
    religion: "",
    address: "",
    contact: "",
    physician: "",
    admission_date: new Date().toISOString().split("T")[0],
    medical_diagnosis: "",
    ward: "",
    status: "Active",
  });

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

  function handleFormChange(
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));

    if (formErrors[name]) {
      setFormErrors((prev) => {
        const next = { ...prev };
        delete next[name];
        return next;
      });
    }
  }

  async function handleFormSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFormErrors({});
    setIsSubmitting(true);

    const errors: Record<string, string> = {};
    if (!formData.name.trim()) errors.name = "Patient name is required.";
    if (!formData.dob) errors.dob = "Date of birth is required.";
    if (!formData.sex) errors.sex = "Sex is required.";
    if (!formData.admission_date) errors.admission_date = "Admission date is required.";

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      setIsSubmitting(false);
      return;
    }

    try {
      const createdPatient = await createPatient(formData);

      if (!createdPatient?.id) {
        throw new Error("Patient was created, but no ID was returned.");
      }

      const createdRecord = await createNcpRecord(createdPatient.id);
      if (!createdRecord?.id) {
        throw new Error("Patient was created, but the assessment cycle could not be initialized.");
      }

      setIsModalOpen(false);
      setFormData({
        name: "",
        dob: "",
        sex: "Male",
        religion: "",
        address: "",
        contact: "",
        physician: "",
        admission_date: new Date().toISOString().split("T")[0],
        medical_diagnosis: "",
        ward: "",
        status: "Active",
      });

      router.push(`/ncp/${createdPatient.id}/assessment/${createdRecord.id}`);
    } catch (err: any) {
      setError(err.message || "Failed to create patient.");
    } finally {
      setIsSubmitting(false);
    }
  }

  function handlePageChange(nextPage: number) {
    if (nextPage >= 1 && (!meta || nextPage <= meta.last_page)) {
      setPage(nextPage);
    }
  }

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
          onClick={() => setIsModalOpen(true)}
          className="md:w-auto px-4.5 py-2.5 shrink-0 flex items-center justify-center gap-2"
        >
          Create Patient & Start Assessment
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
            <table className="w-full text-left border-collapse">
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

      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/40 select-none">
          <div className="w-full max-w-2xl bg-white border border-zinc-200 rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
            <div className="px-5 py-4.5 border-b border-zinc-150 flex items-center justify-between bg-zinc-50">
              <div>
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                  Create New Patient
                </h3>
                <p className="text-[9px] text-zinc-500 mt-0.5">
                  The patient record is created first, then the assessment page opens immediately.
                </p>
              </div>
              <button
                onClick={() => setIsModalOpen(false)}
                className="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 rounded-lg transition-colors"
              >
                Close
              </button>
            </div>

            <form onSubmit={handleFormSubmit} className="flex-1 overflow-y-auto p-5.5 space-y-5">
              <Input
                label="Full Patient Name *"
                name="name"
                value={formData.name}
                onChange={handleFormChange}
                error={formErrors.name}
                placeholder="Jane Doe"
                required
              />

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Date of Birth *"
                  name="dob"
                  type="date"
                  value={formData.dob}
                  onChange={handleFormChange}
                  error={formErrors.dob}
                  required
                />

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-semibold text-zinc-600 tracking-wide">
                    Sex *
                  </label>
                  <select
                    name="sex"
                    value={formData.sex}
                    onChange={handleFormChange}
                    className="w-full px-3.5 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all cursor-pointer"
                  >
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Admission Date *"
                  name="admission_date"
                  type="date"
                  value={formData.admission_date}
                  onChange={handleFormChange}
                  error={formErrors.admission_date}
                  required
                />

                <Input
                  label="Ward"
                  name="ward"
                  value={formData.ward || ""}
                  onChange={handleFormChange}
                  placeholder="Ward 4B - Bed 12"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Physician"
                  name="physician"
                  value={formData.physician || ""}
                  onChange={handleFormChange}
                  placeholder="Dr. Sarah Jenkins"
                />

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-semibold text-zinc-600 tracking-wide">
                    Status
                  </label>
                  <select
                    name="status"
                    value={formData.status}
                    onChange={handleFormChange}
                    className="w-full px-3.5 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all cursor-pointer"
                  >
                    <option value="Active">Active Care</option>
                    <option value="Discharged">Discharged</option>
                    <option value="Transferred">Transferred</option>
                  </select>
                </div>
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-xs font-semibold text-zinc-600 tracking-wide">
                  Primary Medical Diagnosis
                </label>
                <textarea
                  name="medical_diagnosis"
                  value={formData.medical_diagnosis || ""}
                  onChange={handleFormChange}
                  placeholder="Type 2 Diabetes Mellitus, CKD stage 4"
                  className="w-full px-3.5 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400 min-h-18 h-18"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Contact Number"
                  name="contact"
                  value={formData.contact || ""}
                  onChange={handleFormChange}
                  placeholder="+63 912 345 6789"
                />

                <Input
                  label="Religion"
                  name="religion"
                  value={formData.religion || ""}
                  onChange={handleFormChange}
                  placeholder="Roman Catholic"
                />
              </div>

              <Input
                label="Home Address"
                name="address"
                value={formData.address || ""}
                onChange={handleFormChange}
                placeholder="Brgy. San Jose, Rizal"
              />
            </form>

            <div className="px-5 py-4 border-t border-zinc-150 bg-zinc-50 flex items-center justify-end gap-3 shrink-0">
              <Button
                variant="secondary"
                onClick={() => setIsModalOpen(false)}
                className="w-auto px-4.5 py-2 cursor-pointer font-bold uppercase tracking-wider text-zinc-650 hover:bg-zinc-100 rounded-lg text-[10px]"
              >
                Cancel
              </Button>
              <Button
                variant="primary"
                onClick={handleFormSubmit}
                loading={isSubmitting}
                className="w-auto px-4.5 py-2 cursor-pointer font-bold uppercase tracking-wider rounded-lg text-[10px]"
              >
                Create & Start Assessment
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
