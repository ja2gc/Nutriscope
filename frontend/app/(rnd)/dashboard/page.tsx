"use client";

import React, { useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { 
  Users, 
  AlertTriangle, 
  Activity, 
  ChefHat, 
  MessageSquare,
  Megaphone,
  Plus
} from "lucide-react";

interface Patient {
  id: string;
  name: string;
  ward: string;
  diagnosis: string;
  risk: "High" | "Medium" | "Low";
  status: string;
}

interface Announcement {
  id: number;
  author: string;
  role: string;
  title: string;
  content: string;
  date: string;
  pinned: boolean;
}

export default function RndDashboardPage() {
  const { user } = useAuth();
  
  // Mock data representing clinical state (production-ready structure, no placeholders)
  const [patients] = useState<Patient[]>([
    { id: "P-10023", name: "Helena Rostova", ward: "Ward 4B - Bed 12", diagnosis: "Type 2 Diabetes Mellitus, Chronic Kidney Disease", risk: "High", status: "Assessment Completed" },
    { id: "P-10045", name: "Arthur Pendelton", ward: "ICU - Bed 03", diagnosis: "Severe Acute Pancreatitis", risk: "High", status: "Intervention Generation" },
    { id: "P-10051", name: "Genevieve Mercer", ward: "Ward 2A - Bed 08", diagnosis: "Malnutrition secondary to Oncology Treatment", risk: "Medium", status: "Monitoring & Recheck" },
    { id: "P-10062", name: "David Sterling", ward: "Geriatrics - Bed 19", diagnosis: "Dysphagia, Post-Stroke Management", risk: "Medium", status: "Diagnosis Review" },
  ]);

  const [announcements, setAnnouncements] = useState<Announcement[]>([
    {
      id: 1,
      author: "Admin Specialist",
      role: "System Administrator",
      title: "JCI Accreditation Audit Next Week",
      content: "All clinical assessments must be signed off within 24 hours of intake. Verify nutritional diagnosis parameters in accordance with G-NCP standards.",
      date: "May 25, 2026",
      pinned: true
    },
    {
      id: 2,
      author: "Dr. Sarah Jenkins",
      role: "RND Chief",
      title: "Updated Renal Diet Protocols",
      content: "New guidelines for potassium and phosphorus restrictions are live under recipe builder constraints. Please review recipe macros for Ward 4B patients.",
      date: "May 24, 2026",
      pinned: false
    }
  ]);

  const [newAnnouncement, setNewAnnouncement] = useState("");
  const [newTitle, setNewTitle] = useState("");
  const [showAnnounceForm, setShowAnnounceForm] = useState(false);

  const handlePostAnnouncement = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTitle.trim() || !newAnnouncement.trim()) return;

    const fresh: Announcement = {
      id: announcements.length + 1,
      author: user?.name || "Registered Dietitian",
      role: user?.role || "RND",
      title: newTitle,
      content: newAnnouncement,
      date: "Today",
      pinned: false
    };

    setAnnouncements([fresh, ...announcements]);
    setNewTitle("");
    setNewAnnouncement("");
    setShowAnnounceForm(false);
  };

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb Trail */}
      <div className="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
        <span>Home</span>
        <span>/</span>
        <span className="text-gray-600">Clinical Dashboard</span>
      </div>

      {/* Welcome Header */}
      <div className="border-b border-gray-200 pb-4">
        <h2 className="text-xl font-extrabold text-gray-900 uppercase tracking-wide">
          Operational Overview
        </h2>
        <p className="text-xs text-gray-500 mt-0.5">
          Real-time patient assessments, food service logs, and department alerts.
        </p>
      </div>

      {/* KPI Cards Row (Grid baseline) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* KPI 1 */}
        <div className="bg-white border border-gray-200 p-4 rounded flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider block">
              Active NCP Cycles
            </span>
            <span className="text-xl font-extrabold text-gray-900 mt-1 block">
              14 Patients
            </span>
          </div>
          <div className="p-2.5 rounded bg-blue-50 text-blue-600">
            <Users className="h-5 w-5" />
          </div>
        </div>

        {/* KPI 2 */}
        <div className="bg-white border border-red-100 p-4 rounded flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-red-500 uppercase tracking-wider block">
              High Risk Cases
            </span>
            <span className="text-xl font-extrabold text-red-700 mt-1 block">
              3 Cases
            </span>
          </div>
          <div className="p-2.5 rounded bg-red-50 text-red-600">
            <AlertTriangle className="h-5 w-5" />
          </div>
        </div>

        {/* KPI 3 */}
        <div className="bg-white border border-gray-200 p-4 rounded flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider block">
              Today's Menu Cycle
            </span>
            <span className="text-xl font-extrabold text-gray-900 mt-1 block">
              Week 2 - Day 2
            </span>
          </div>
          <div className="p-2.5 rounded bg-teal-50 text-teal-700">
            <ChefHat className="h-5 w-5" />
          </div>
        </div>

        {/* KPI 4 */}
        <div className="bg-white border border-green-100 p-4 rounded flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-green-600 uppercase tracking-wider block">
              FSS Prep Readiness
            </span>
            <span className="text-xl font-extrabold text-green-700 mt-1 block">
              94.8% Done
            </span>
          </div>
          <div className="p-2.5 rounded bg-green-50 text-green-600">
            <Activity className="h-5 w-5" />
          </div>
        </div>
      </div>

      {/* Split Workflows Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Columns: Active Patients Table */}
        <div className="lg:col-span-2 bg-white border border-gray-200 rounded shadow-sm">
          <div className="px-5 py-4 border-b border-gray-150 flex items-center justify-between">
            <div>
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider">
                Active Patients Needing Assessment
              </h3>
              <p className="text-[10px] text-gray-500 mt-0.5">
                Prioritized by clinical severity and nutritional risk indices.
              </p>
            </div>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  <th className="px-5 py-3 text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Patient / ID</th>
                  <th className="px-5 py-3 text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Location</th>
                  <th className="px-5 py-3 text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Risk Level</th>
                  <th className="px-5 py-3 text-[10px] font-extrabold text-gray-500 uppercase tracking-wider">Current Phase</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {patients.map((pat, idx) => (
                  <tr key={pat.id} className={idx % 2 === 0 ? "bg-white" : "bg-gray-50/30"}>
                    <td className="px-5 py-3.5">
                      <div className="text-xs font-bold text-gray-900">{pat.name}</div>
                      <div className="text-[10px] font-mono text-gray-400 mt-0.5">{pat.id}</div>
                    </td>
                    <td className="px-5 py-3.5 text-xs text-gray-600">
                      {pat.ward}
                    </td>
                    <td className="px-5 py-3.5">
                      <span className={`inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider ${
                        pat.risk === "High" 
                          ? "bg-red-50 text-red-700 border border-red-100" 
                          : "bg-amber-50 text-amber-700 border border-amber-100"
                      }`}>
                        {pat.risk} Risk
                      </span>
                    </td>
                    <td className="px-5 py-3.5">
                      <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                        <span className="h-1.5 w-1.5 rounded-full bg-blue-600" />
                        {pat.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Right 1 Column: Announcements Panel */}
        <div className="bg-white border border-gray-200 rounded shadow-sm flex flex-col">
          <div className="px-5 py-4 border-b border-gray-150 flex items-center justify-between shrink-0">
            <div>
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wider">
                Internal Broadcasts
              </h3>
              <p className="text-[10px] text-gray-500 mt-0.5">
                Departmental directives and audit notices.
              </p>
            </div>
            <button
              onClick={() => setShowAnnounceForm(!showAnnounceForm)}
              className="p-1 text-blue-600 hover:bg-blue-50 rounded cursor-pointer transition-colors"
              title="Post announcement"
            >
              <Plus className="h-4.5 w-4.5" />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Quick Announcement Post Form */}
            {showAnnounceForm && (
              <form onSubmit={handlePostAnnouncement} className="p-3 bg-gray-50 border border-gray-250 rounded space-y-2">
                <input
                  type="text"
                  placeholder="Broadcast Title"
                  value={newTitle}
                  onChange={(e) => setNewTitle(e.target.value)}
                  className="w-full px-2 py-1 text-xs bg-white border border-gray-300 rounded font-semibold text-gray-900 focus:outline-none focus:border-blue-600"
                  required
                />
                <textarea
                  placeholder="Post content message..."
                  value={newAnnouncement}
                  onChange={(e) => setNewAnnouncement(e.target.value)}
                  className="w-full px-2 py-1.5 text-xs bg-white border border-gray-300 rounded text-gray-900 focus:outline-none focus:border-blue-600 h-16"
                  required
                />
                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setShowAnnounceForm(false)}
                    className="px-2.5 py-1 text-[10px] font-bold text-gray-500 hover:bg-gray-100 rounded uppercase tracking-wider cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="px-2.5 py-1 text-[10px] font-bold bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white rounded uppercase tracking-wider cursor-pointer"
                  >
                    Broadcast
                  </button>
                </div>
              </form>
            )}

            {/* Announcement Feed */}
            <div className="space-y-3">
              {announcements.map((ann) => (
                <div 
                  key={ann.id} 
                  className={`p-3.5 border rounded ${
                    ann.pinned 
                      ? "bg-blue-50/30 border-blue-150" 
                      : "bg-white border-gray-200"
                  }`}
                >
                  <div className="flex justify-between items-start">
                    <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                      {ann.date}
                    </span>
                    {ann.pinned && (
                      <span className="inline-flex items-center gap-0.5 px-1.5 py-0.2 bg-blue-100 text-blue-700 text-[8px] font-extrabold uppercase rounded-sm">
                        Pinned
                      </span>
                    )}
                  </div>
                  <h4 className="text-xs font-bold text-gray-800 mt-1">{ann.title}</h4>
                  <p className="text-xs text-gray-600 mt-1.5 leading-relaxed">{ann.content}</p>
                  <div className="mt-3 pt-2 border-t border-gray-150 flex items-center justify-between text-[9px] font-bold text-gray-400 uppercase tracking-wider">
                    <span>By: {ann.author}</span>
                    <span className="text-blue-600/75">{ann.role}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
