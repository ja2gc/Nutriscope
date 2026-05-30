"use client";

import React, { useState, useEffect, use } from "react";
import Link from "next/link";
import { 
  fetchPatientById, 
  fetchPatientNcpRecords, 
  Patient, 
  NcpRecord 
} from "@/services/patientService";
import { Button } from "@/components/ui/Button";
import { 
  ChevronLeft,
  User, 
  Calendar, 
  MapPin, 
  ShieldAlert, 
  Stethoscope, 
  Activity, 
  Clock, 
  Sparkles,
  ArrowRight,
  FolderHeart,
  Award,
  AlertCircle
} from "lucide-react";

export default function PatientProfilePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const [patient, setPatient] = useState<Patient | null>(null);
  const [records, setRecords] = useState<NcpRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  // Navigation Tabs state
  const [activeTab, setActiveTab] = useState("overview");

  // Calculate age utility
  const calculateAge = (dobString?: string) => {
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

  useEffect(() => {
    async function loadData() {
      try {
        setLoading(true);
        setError(null);
        
        // Load patient details & NCP history records in parallel
        const [patientData, recordsData] = await Promise.all([
          fetchPatientById(id),
          fetchPatientNcpRecords(id)
        ]);

        setPatient(patientData);
        setRecords(recordsData);
      } catch (err: any) {
        setError(err.message || "Failed to load patient profile records.");
      } finally {
        setLoading(false);
      }
    }

    loadData();
  }, [id]);

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
        <div className="bg-red-50 border border-red-150 p-6 rounded-2xl text-center space-y-4">
          <AlertCircle className="h-10 w-10 text-red-600 mx-auto" />
          <h3 className="text-sm font-bold text-zinc-900 uppercase tracking-wider">
            Patient Profile Error
          </h3>
          <p className="text-xs text-zinc-500 max-w-sm mx-auto leading-relaxed">
            {error || "The requested patient record could not be found. Verify that the unique ID is valid."}
          </p>
          <Link
            href="/ncp"
            className="inline-flex px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white font-semibold text-xs rounded-lg transition-all"
          >
            Return to Directory
          </Link>
        </div>
      </div>
    );
  }

  const patientSysId = `NS-${String(patient.id).padStart(5, "0")}`;
  const patientAge = calculateAge(patient.dob);

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center justify-between text-xs font-semibold text-zinc-400 select-none">
        <div className="flex items-center gap-2">
          <Link href="/ncp" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <span className="text-zinc-300">/</span>
          <span className="text-zinc-650 font-bold">{patient.name}</span>
        </div>
        <Link 
          href="/ncp" 
          className="flex items-center gap-1 text-zinc-500 hover:text-zinc-800 transition-colors"
        >
          <ChevronLeft className="h-4 w-4" />
          Back to Directory
        </Link>
      </div>

      {/* PERSISTENT PATIENT CLINICAL HEADER */}
      <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm p-5.5 relative overflow-hidden">
        {/* Subtle background decoration (non-gradient, clinical layout line) */}
        <div className="absolute right-0 top-0 bottom-0 w-1.5 bg-emerald-600 shrink-0" />

        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div className="space-y-3.5">
            <div className="flex flex-wrap items-center gap-2.5">
              <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight">
                {patient.name}
              </h2>
              <span className="text-xs font-mono px-2 py-0.5 bg-zinc-100 text-zinc-550 border border-zinc-200 rounded-lg select-none">
                {patientSysId}
              </span>
              <span className={`px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider border select-none ${
                patient.status === "Active" 
                  ? "bg-emerald-50 text-emerald-700 border-emerald-100" 
                  : patient.status === "Discharged"
                  ? "bg-zinc-100 text-zinc-550 border-zinc-200"
                  : "bg-orange-50 text-orange-700 border-orange-100"
              }`}>
                {patient.status}
              </span>
            </div>

            {/* Core specs strip */}
            <div className="flex flex-wrap items-center gap-y-2 gap-x-5 text-xs text-zinc-500 font-semibold select-none">
              <span className="flex items-center gap-1.5">
                <User className="h-4 w-4 text-zinc-400" />
                {patientAge} years / {patient.sex}
              </span>
              <span className="h-3 w-px bg-zinc-200 hidden sm:inline" />
              <span className="flex items-center gap-1.5">
                <MapPin className="h-4 w-4 text-zinc-400" />
                {patient.ward || "No Bed Assigned"}
              </span>
              <span className="h-3 w-px bg-zinc-200 hidden sm:inline" />
              <span className="flex items-center gap-1.5">
                <Stethoscope className="h-4 w-4 text-zinc-400" />
                MD: {patient.physician || "Unassigned Physician"}
              </span>
            </div>

            {/* Primary diagnosis text */}
            <div className="text-xs text-zinc-700 font-medium pt-1">
              <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block mb-0.5 select-none">
                Primary Medical Diagnosis:
              </span>
              <span className="text-zinc-800 leading-relaxed font-semibold">
                {patient.medical_diagnosis || "No primary medical diagnosis logged in system."}
              </span>
            </div>
          </div>

          {/* Critical indicators & Direct triggers */}
          <div className="flex flex-col gap-2.5 min-w-[200px] shrink-0 select-none">
            <div className="flex flex-wrap gap-1.5">
              <span className="inline-flex px-2 py-1 bg-red-50 text-red-750 text-[9px] font-extrabold uppercase border border-red-100 rounded-lg">
                High Risk Indicator
              </span>
              <span className="inline-flex px-2 py-1 bg-orange-50 text-orange-750 text-[9px] font-extrabold uppercase border border-orange-100 rounded-lg">
                Fluid Watch
              </span>
            </div>
            
            <Button
              variant="primary"
              className="px-4 py-2.5 text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer transition-all duration-150"
            >
              <Activity className="h-4 w-4" />
              Start NCP Cycle
            </Button>
          </div>
        </div>
      </div>

      {/* ADIME CLINICAL MODULE TABS */}
      <div className="border-b border-zinc-200 select-none">
        <nav className="flex space-x-6">
          {[
            { id: "overview", label: "Overview & History", active: true },
            { id: "assessment", label: "Nutrition Assessment", active: false, badge: "M4" },
            { id: "diagnosis", label: "Diagnosis (PES)", active: false, badge: "M5" },
            { id: "intervention", label: "Intervention (Meal Plan)", active: false, badge: "M6" },
            { id: "monitoring", label: "Monitoring Trends", active: false, badge: "M8" },
          ].map(tab => (
            <button
              key={tab.id}
              onClick={() => {
                if (tab.id === "overview") setActiveTab("overview");
              }}
              disabled={tab.id !== "overview"}
              className={`pb-4 text-xs font-extrabold uppercase tracking-wider border-b-2 transition-all relative flex items-center gap-1.5 ${
                activeTab === tab.id
                  ? "border-emerald-600 text-emerald-800"
                  : "border-transparent text-zinc-400 disabled:opacity-55 disabled:cursor-not-allowed hover:text-zinc-650"
              }`}
            >
              {tab.label}
              {tab.badge && (
                <span className="px-1.5 py-0.2 bg-zinc-100 border border-zinc-200 text-[8px] text-zinc-500 font-bold rounded-lg uppercase tracking-normal">
                  {tab.badge}
                </span>
              )}
            </button>
          ))}
        </nav>
      </div>

      {/* TAB CONTENT CANVAS */}
      {activeTab === "overview" && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
          
          {/* LEFT 2 COLUMNS: Profile details split card */}
          <div className="lg:col-span-2 space-y-6">
            
            {/* Demographics & Intake details */}
            <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50/50 select-none">
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                  <User className="h-4.5 w-4.5 text-zinc-500" />
                  Demographics & Admission Profile
                </h3>
              </div>
              
              <div className="p-5.5 grid grid-cols-1 sm:grid-cols-2 gap-y-4.5 gap-x-6 text-xs">
                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">Date of Birth</span>
                  <span className="text-zinc-800 font-semibold">
                    {patient.dob ? new Date(patient.dob).toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" }) : "N/A"}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">Admission Date</span>
                  <span className="text-zinc-800 font-semibold">
                    {patient.admission_date ? new Date(patient.admission_date).toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" }) : "N/A"}
                  </span>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">Contact Number</span>
                  <span className="text-zinc-800 font-mono font-semibold">{patient.contact || "No number logged"}</span>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">Religion</span>
                  <span className="text-zinc-800 font-semibold">{patient.religion || "Not specified"}</span>
                </div>

                <div className="sm:col-span-2 space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">Home Address</span>
                  <span className="text-zinc-700 font-semibold leading-relaxed">{patient.address || "No address documented"}</span>
                </div>
              </div>
            </div>

            {/* Directives details */}
            <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
              <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50/50 select-none">
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                  <Stethoscope className="h-4.5 w-4.5 text-zinc-500" />
                  Clinical & Care Directives
                </h3>
              </div>
              
              <div className="p-5.5 space-y-5 text-xs">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1 select-none">
                    <span className="text-[9px] font-extrabold text-zinc-450 uppercase tracking-wider block">Intake Supervisor</span>
                    <span className="text-xs font-bold text-zinc-850 flex items-center gap-1.5 mt-0.5">
                      <Stethoscope className="h-3.5 w-3.5 text-emerald-600" />
                      {patient.physician || "No attending MD"}
                    </span>
                  </div>

                  <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl space-y-1 select-none">
                    <span className="text-[9px] font-extrabold text-zinc-450 uppercase tracking-wider block">Initial Nutrition Risk</span>
                    <span className="text-xs font-bold text-red-750 flex items-center gap-1.5 mt-0.5">
                      <ShieldAlert className="h-3.5 w-3.5 text-red-650" />
                      High Risk (Score: 4/5)
                    </span>
                  </div>
                </div>

                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">Attending Dietitian Note</span>
                  <p className="text-zinc-650 leading-relaxed font-semibold italic">
                    "Patient presents secondary kidney dysfunction and chronic hyperglycemia. Care cycles must optimize high protein restrictions under Stage 4 CKD nutrition rules."
                  </p>
                </div>
              </div>
            </div>
            
          </div>

          {/* RIGHT 1 COLUMN: NCP Care History Timeline */}
          <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50/50 select-none">
              <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider flex items-center gap-2">
                <Clock className="h-4.5 w-4.5 text-zinc-500" />
                NCP Cycle History
              </h3>
            </div>

            <div className="p-5 flex-1 min-h-[350px]">
              {records.length === 0 ? (
                <div className="h-full flex flex-col items-center justify-center text-center p-6 select-none">
                  <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit text-zinc-400 mb-4">
                    <FolderHeart className="h-8 w-8 animate-pulse" />
                  </div>
                  <h4 className="text-xs font-bold text-zinc-800">No Care Cycles Initiated</h4>
                  <p className="text-[11px] text-zinc-500 mt-1.5 max-w-[200px] leading-relaxed">
                    This patient has no registered Nutrition Care Process history. Initiate a cycle to begin assessment.
                  </p>
                  
                  <Button
                    variant="primary"
                    className="w-auto px-4 py-2 mt-4 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5 cursor-pointer rounded-lg"
                  >
                    Initialize Care Cycle
                    <ArrowRight className="h-3.5 w-3.5" />
                  </Button>
                </div>
              ) : (
                /* Dynamic clinical vertical timeline */
                <div className="relative pl-5 border-l-2 border-zinc-200 space-y-6">
                  {records.map((rec) => {
                    const formattedDate = new Date(rec.created_at).toLocaleDateString("en-US", {
                      month: "short",
                      day: "numeric",
                      year: "numeric"
                    });
                    
                    return (
                      <div key={rec.id} className="relative">
                        {/* Timeline point indicator */}
                        <div className="absolute -left-[27px] top-1 h-3.5 w-3.5 rounded-full bg-white border-2 border-emerald-600 flex items-center justify-center">
                          <div className="h-1.5 w-1.5 bg-emerald-600 rounded-full" />
                        </div>
                        
                        <div className="space-y-1">
                          <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">
                              {formattedDate}
                            </span>
                            <span className={`px-1.5 py-0.2 rounded-full text-[8px] font-bold uppercase tracking-wide border ${
                              rec.status === "Active" 
                                ? "bg-emerald-50 text-emerald-700 border-emerald-100" 
                                : "bg-zinc-100 text-zinc-600 border-zinc-200"
                            }`}>
                              {rec.status}
                            </span>
                          </div>

                          <h4 className="text-xs font-bold text-zinc-800">
                            Nutrition Care Cycle
                          </h4>
                          
                          <div className="text-[10px] text-zinc-500 flex flex-col gap-0.5">
                            <span className="font-semibold">Dietitian: {rec.rnd?.name || "Registered Dietitian"}</span>
                            {rec.ai_risk_score && (
                              <span className="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 bg-orange-50 border border-orange-100 text-orange-700 rounded-lg w-fit font-bold">
                                <Sparkles className="h-2.5 w-2.5" />
                                AI Intake Risk: {rec.ai_risk_score}
                              </span>
                            )}
                          </div>
                          
                          <div className="pt-2">
                            <span className="inline-flex items-center gap-1.5 text-[10px] font-bold text-emerald-750 hover:text-emerald-900 transition-colors cursor-pointer select-none">
                              Review Cycle Parameters
                              <ArrowRight className="h-3 w-3" />
                            </span>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
          
        </div>
      )}
    </div>
  );
}
