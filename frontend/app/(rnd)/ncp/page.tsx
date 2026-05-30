"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { 
  fetchPatients, 
  createPatient, 
  Patient, 
  PatientStoreData 
} from "@/services/patientService";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { 
  HeartHandshake, 
  Plus, 
  Search, 
  SlidersHorizontal,
  ChevronLeft, 
  ChevronRight, 
  X, 
  UserPlus, 
  FolderHeart,
  Activity,
  UserCheck
} from "lucide-react";

export default function NcpPatientsPage() {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filter and pagination state
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("All");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<any>(null);

  // Form modal state
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
    status: "Active"
  });

  // Calculate age utility
  const calculateAge = (dobString: string) => {
    if (!dobString) return "N/A";
    const today = new Date();
    const birthDate = new Date(dobString);
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age;
  };

  // Fetch patients
  const loadPatients = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await fetchPatients(search, status, page);
      setPatients(res.data);
      setMeta(res.meta);
    } catch (err: any) {
      setError(err.message || "An error occurred while loading patient records.");
    } finally {
      setLoading(false);
    }
  }, [search, status, page]);

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      loadPatients();
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [loadPatients]);

  const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
    setPage(1);
  };

  const handleStatusChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setStatus(e.target.value);
    setPage(1);
  };

  const handlePageChange = (newPage: number) => {
    if (newPage >= 1 && (!meta || newPage <= meta.last_page)) {
      setPage(newPage);
    }
  };

  // Handle Form Change
  const handleFormChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (formErrors[name]) {
      setFormErrors(prev => {
        const next = { ...prev };
        delete next[name];
        return next;
      });
    }
  };

  // Form Submit
  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormErrors({});
    setIsSubmitting(true);

    // Basic frontend validation
    const errors: Record<string, string> = {};
    if (!formData.name.trim()) errors.name = "Full Patient Name is required.";
    if (!formData.dob) errors.dob = "Date of Birth is required.";
    if (!formData.sex) errors.sex = "Biological sex is required.";
    if (!formData.admission_date) errors.admission_date = "Admission date is required.";

    if (Object.keys(errors).length > 0) {
      setFormErrors(errors);
      setIsSubmitting(false);
      return;
    }

    try {
      await createPatient(formData);
      setIsModalOpen(false);
      // Reset form
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
        status: "Active"
      });
      // Reload
      loadPatients();
    } catch (err: any) {
      if (err.message && err.message.includes("The given data was invalid")) {
        // Handle Laravel validation format if applicable
        setError("Invalid form submission. Please verify input formats.");
      } else {
        setError(err.message || "Failed to save new patient record.");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <span>Home</span>
        <span className="text-zinc-300">/</span>
        <span>Clinical Care</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-650 font-bold">NCP Patients</span>
      </div>

      {/* Header Canvas */}
      <div className="border-b border-zinc-200 pb-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-900 tracking-tight flex items-center gap-2.5">
            <HeartHandshake className="h-5 w-5 text-emerald-600" />
            NCP Patient Management
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            Registered patients assigned to the Nutrition Care Process directory.
          </p>
        </div>
        <Button 
          variant="primary" 
          onClick={() => setIsModalOpen(true)}
          className="md:w-auto px-4.5 py-2.5 shrink-0 flex items-center justify-center gap-2"
        >
          <UserPlus className="h-4.5 w-4.5" />
          Register Patient
        </Button>
      </div>

      {/* Main Controls row */}
      <div className="flex flex-col sm:flex-row items-center gap-3 bg-white p-4 rounded-xl border border-zinc-200 shadow-sm">
        <div className="relative w-full sm:flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
          <input
            type="text"
            placeholder="Search patient, physician, or location..."
            value={search}
            onChange={handleSearchChange}
            className="w-full pl-9.5 pr-4 py-2 text-sm bg-white border border-zinc-350 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400"
          />
        </div>
        
        <div className="flex items-center gap-2 w-full sm:w-auto shrink-0 select-none">
          <SlidersHorizontal className="h-4 w-4 text-zinc-500" />
          <select
            value={status}
            onChange={handleStatusChange}
            className="w-full sm:w-40 px-3 py-2 text-sm bg-white border border-zinc-350 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all cursor-pointer font-semibold"
          >
            <option value="All">All Statuses</option>
            <option value="Active">Active Care</option>
            <option value="Discharged">Discharged</option>
            <option value="Transferred">Transferred</option>
          </select>
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <div className="bg-red-50 border border-red-150 p-4 rounded-xl flex items-start gap-3">
          <X className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
          <div className="text-xs text-red-755 font-bold">{error}</div>
        </div>
      )}

      {/* High-density zebra table */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-8 space-y-4">
            <div className="h-5 w-40 bg-zinc-200 rounded-lg animate-pulse" />
            <div className="space-y-2 pt-4">
              {[1, 2, 3, 4].map(idx => (
                <div key={idx} className="flex gap-4 h-12 items-center">
                  <div className="flex-1 bg-zinc-100 rounded-lg h-8 animate-pulse" />
                  <div className="w-24 bg-zinc-100 rounded-lg h-8 animate-pulse" />
                  <div className="w-32 bg-zinc-100 rounded-lg h-8 animate-pulse" />
                </div>
              ))}
            </div>
          </div>
        ) : patients.length === 0 ? (
          <div className="p-12 text-center select-none">
            <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
              <FolderHeart className="h-8 w-8" />
            </div>
            <h3 className="text-sm font-bold text-zinc-800 mt-4">No Patients Found</h3>
            <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
              No clinical records match your current criteria. Register a new patient to initialize their Nutrition Care Process file.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-zinc-50 border-b border-zinc-200 select-none">
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Patient / System ID</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Clinical Specs</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Location</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Primary Diagnosis & MD</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Intake Date</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Care Status</th>
                  <th className="px-5 py-4 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {patients.map((patient, index) => {
                  const patientId = `NS-${String(patient.id).padStart(5, "0")}`;
                  return (
                    <tr 
                      key={patient.id} 
                      className={`${index % 2 === 0 ? "bg-white" : "bg-zinc-50/20"} hover:bg-zinc-50/40 transition-colors`}
                    >
                      {/* Name & ID */}
                      <td className="px-5 py-4">
                        <Link 
                          href={`/ncp/${patient.id}`}
                          className="text-xs font-bold text-zinc-900 hover:text-emerald-700 hover:underline transition-colors block"
                        >
                          {patient.name}
                        </Link>
                        <span className="text-[10px] font-mono text-zinc-400 mt-1 block">
                          {patientId}
                        </span>
                      </td>

                      {/* Clinical Specs */}
                      <td className="px-5 py-4 text-xs font-medium text-zinc-700">
                        {calculateAge(patient.dob)} yrs / {patient.sex}
                      </td>

                      {/* Location */}
                      <td className="px-5 py-4 text-xs font-semibold text-zinc-650">
                        {patient.ward || "No Bed Assignment"}
                      </td>

                      {/* Primary Diagnosis & MD */}
                      <td className="px-5 py-4">
                        <div className="text-xs font-medium text-zinc-800 line-clamp-1 max-w-[200px]" title={patient.medical_diagnosis || ""}>
                          {patient.medical_diagnosis || "No Diagnosis Logged"}
                        </div>
                        <div className="text-[10px] text-zinc-400 mt-0.5 font-bold uppercase tracking-wider">
                          MD: {patient.physician || "Unassigned"}
                        </div>
                      </td>

                      {/* Intake Date */}
                      <td className="px-5 py-4 text-xs text-zinc-600">
                        {patient.admission_date ? new Date(patient.admission_date).toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" }) : "N/A"}
                      </td>

                      {/* Care Status */}
                      <td className="px-5 py-4 select-none">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide border ${
                          patient.status === "Active" 
                            ? "bg-emerald-50 text-emerald-700 border-emerald-100" 
                            : patient.status === "Discharged"
                            ? "bg-zinc-100 text-zinc-650 border-zinc-200"
                            : "bg-orange-50 text-orange-700 border-orange-100"
                        }`}>
                          {patient.status}
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-4 text-right">
                        <Link
                          href={`/ncp/${patient.id}`}
                          className="inline-flex px-3 py-1.5 bg-zinc-900 hover:bg-zinc-800 text-white hover:text-zinc-50 text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer select-none"
                        >
                          Open Profile
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination controls */}
        {meta && meta.last_page > 1 && (
          <div className="px-5 py-4 border-t border-zinc-100 bg-zinc-50 flex items-center justify-between select-none">
            <span className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
              Showing Page {meta.current_page} of {meta.last_page} ({meta.total} Total)
            </span>
            <div className="flex gap-1.5">
              <button
                onClick={() => handlePageChange(page - 1)}
                disabled={page === 1}
                className="p-1.5 border border-zinc-300 bg-white text-zinc-600 rounded-lg hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                title="Previous Page"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button
                onClick={() => handlePageChange(page + 1)}
                disabled={page === meta.last_page}
                className="p-1.5 border border-zinc-300 bg-white text-zinc-600 rounded-lg hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
                title="Next Page"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Add Patient Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/40 backdrop-blur-xs select-none">
          <div className="w-full max-w-2xl bg-white border border-zinc-200 rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="px-5 py-4.5 border-b border-zinc-150 flex items-center justify-between bg-zinc-50">
              <div className="flex items-center gap-2.5">
                <div className="p-1.5 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg">
                  <UserPlus className="h-4.5 w-4.5" />
                </div>
                <div>
                  <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                    Register New RND Patient
                  </h3>
                  <p className="text-[9px] text-zinc-500 mt-0.5">
                    Initialize an official G-NCP nutrition history chart.
                  </p>
                </div>
              </div>
              <button 
                onClick={() => setIsModalOpen(false)}
                className="p-1 text-zinc-400 hover:text-zinc-600 rounded-lg cursor-pointer hover:bg-zinc-100 transition-colors"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Modal Scrollable Form */}
            <form onSubmit={handleFormSubmit} className="flex-1 overflow-y-auto p-5.5 space-y-5">
              {/* Row 1: Name */}
              <Input
                label="Full Patient Name *"
                name="name"
                value={formData.name}
                onChange={handleFormChange}
                error={formErrors.name}
                placeholder="Jane Doe (Last, First Middle)"
                required
              />

              {/* Row 2: DOB & Sex */}
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
                    Biological Sex *
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

              {/* Row 3: Admission Date & Ward */}
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
                  label="Ward & Bed Location"
                  name="ward"
                  value={formData.ward || ""}
                  onChange={handleFormChange}
                  placeholder="Ward 4B - Bed 12"
                />
              </div>

              {/* Row 4: Physician & Medical Diagnosis */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  label="Physician-in-Charge"
                  name="physician"
                  value={formData.physician || ""}
                  onChange={handleFormChange}
                  placeholder="Dr. Sarah Jenkins"
                />

                <div className="flex flex-col gap-1.5">
                  <label className="text-xs font-semibold text-zinc-600 tracking-wide">
                    Care Status
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
                  placeholder="e.g. Type 2 Diabetes Mellitus, Severe CKD Stage 4"
                  className="w-full px-3.5 py-2 text-sm bg-white border border-zinc-300 rounded-lg text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition-all placeholder:text-zinc-400 min-h-18 h-18"
                />
              </div>

              {/* Row 5: Demographics (Religion, Contact, Address) */}
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
                placeholder="Brgy. San Jose, Romana Pangan, Rizal"
              />
            </form>

            {/* Modal Actions */}
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
                Create Chart
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
