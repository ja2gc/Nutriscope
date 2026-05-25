"use client";

import React, { useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { 
  HeartHandshake, 
  ShieldAlert, 
  Salad, 
  TrendingUp, 
  Megaphone,
  Plus,
  Sparkles
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
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <span>Home</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-600 font-bold">Overview & Operations</span>
      </div>

      {/* Welcome Header */}
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-900 tracking-tight">
          {user ? `Good morning, ${user.name}` : "Overview & Operations Center"}
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Real-time patient assessments, kitchen readiness logs, and department directives.
        </p>
      </div>

      {/* KPI Cards Row (Grid baseline) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4.5">
        {/* KPI 1 */}
        <div className="bg-white border border-zinc-200 p-4.5 rounded-2xl flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block select-none">
              NCP Patients
            </span>
            <span className="text-lg font-extrabold text-zinc-950 mt-1 block">
              14 Active
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-brand-green-50 text-brand-green-700 border border-brand-green-100">
            <HeartHandshake className="h-5 w-5" />
          </div>
        </div>

        {/* KPI 2 */}
        <div className="bg-white border border-brand-orange-100 p-4.5 rounded-2xl flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-brand-orange-500 uppercase tracking-wider block select-none">
              High Risk Cases
            </span>
            <span className="text-lg font-extrabold text-brand-orange-700 mt-1 block">
              3 Cases
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-brand-orange-50 text-brand-orange-700 border border-brand-orange-100 animate-pulse">
            <ShieldAlert className="h-5 w-5" />
          </div>
        </div>

        {/* KPI 3 */}
        <div className="bg-white border border-zinc-200 p-4.5 rounded-2xl flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block select-none">
              Today's Menu Cycle
            </span>
            <span className="text-lg font-extrabold text-zinc-950 mt-1 block">
              Week 2 - Day 2
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-brand-green-50 text-brand-green-700 border border-brand-green-100">
            <Salad className="h-5 w-5" />
          </div>
        </div>

        {/* KPI 4 */}
        <div className="bg-white border border-zinc-250 p-4.5 rounded-2xl flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block select-none">
              FSS Prep Readiness
            </span>
            <span className="text-lg font-extrabold text-brand-green-700 mt-1 block">
              94.8% Done
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-brand-green-50 text-brand-green-700 border border-brand-green-100">
            <TrendingUp className="h-5 w-5" />
          </div>
        </div>
      </div>

      {/* Split Workflows Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Columns: Active Patients Table */}
        <div className="lg:col-span-2 bg-white border border-zinc-200 rounded-2xl shadow-sm overflow-hidden">
          <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between">
            <div>
              <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                Patients Awaiting Nutrition Care
              </h3>
              <p className="text-[10px] text-zinc-500 mt-1">
                Prioritized by clinical severity and nutritional risk indicators.
              </p>
            </div>
          </div>
          
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-zinc-50 border-b border-zinc-150">
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Patient / ID</th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Location</th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Risk Level</th>
                  <th className="px-5 py-3.5 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">Current Phase</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {patients.map((pat, idx) => (
                  <tr key={pat.id} className={idx % 2 === 0 ? "bg-white" : "bg-zinc-50/20"}>
                    <td className="px-5 py-4.5">
                      <div className="text-xs font-bold text-zinc-800">{pat.name}</div>
                      <div className="text-[10px] font-mono text-zinc-400 mt-0.5">{pat.id}</div>
                    </td>
                    <td className="px-5 py-4.5 text-xs text-zinc-600">
                      {pat.ward}
                    </td>
                    <td className="px-5 py-4.5">
                      <span className={`inline-flex px-2.5 py-0.5 rounded-lg text-[9px] font-extrabold uppercase tracking-wider border ${
                        pat.risk === "High" 
                          ? "bg-red-50 text-red-700 border-red-100" 
                          : "bg-brand-orange-50 text-brand-orange-700 border-brand-orange-100"
                      }`}>
                        {pat.risk} Risk
                      </span>
                    </td>
                    <td className="px-5 py-4.5">
                      <span className="inline-flex items-center gap-2 text-xs font-semibold text-zinc-700">
                        <span className="h-2 w-2 rounded-full bg-brand-green-600" />
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
        <div className="bg-white border border-zinc-200 rounded-2xl shadow-sm flex flex-col overflow-hidden">
          <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between shrink-0">
            <div>
              <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-wider">
                Announcements & Directives
              </h3>
              <p className="text-[10px] text-zinc-500 mt-1">
                Hospital notifications and clinical updates.
              </p>
            </div>
            <button
              onClick={() => setShowAnnounceForm(!showAnnounceForm)}
              className="p-1.5 text-brand-green-700 hover:bg-brand-green-50 rounded-lg cursor-pointer transition-colors border border-transparent hover:border-brand-green-100"
              title="Post announcement"
            >
              <Plus className="h-4 w-4" />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Quick Announcement Post Form */}
            {showAnnounceForm && (
              <form onSubmit={handlePostAnnouncement} className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-xl space-y-3">
                <input
                  type="text"
                  placeholder="Broadcast Title"
                  value={newTitle}
                  onChange={(e) => setNewTitle(e.target.value)}
                  className="w-full px-3 py-1.5 text-xs bg-white border border-zinc-300 rounded-lg font-semibold text-zinc-950 focus:outline-none focus:ring-2 focus:ring-brand-green-500/20 focus:border-brand-green-600 transition-all"
                  required
                />
                <textarea
                  placeholder="Post content message..."
                  value={newAnnouncement}
                  onChange={(e) => setNewAnnouncement(e.target.value)}
                  className="w-full px-3 py-2 text-xs bg-white border border-zinc-300 rounded-lg text-zinc-950 focus:outline-none focus:ring-2 focus:ring-brand-green-500/20 focus:border-brand-green-600 transition-all h-20"
                  required
                />
                <div className="flex justify-end gap-2 pt-1">
                  <button
                    type="button"
                    onClick={() => setShowAnnounceForm(false)}
                    className="px-3 py-1.5 text-[10px] font-bold text-zinc-500 hover:bg-zinc-100 rounded-lg uppercase tracking-wider cursor-pointer transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="px-3 py-1.5 text-[10px] font-bold bg-brand-green-600 hover:bg-brand-green-700 active:bg-brand-green-800 text-white rounded-lg uppercase tracking-wider cursor-pointer transition-all duration-150"
                  >
                    Broadcast
                  </button>
                </div>
              </form>
            )}

            {/* Announcement Feed */}
            <div className="space-y-3.5">
              {announcements.map((ann) => (
                <div 
                  key={ann.id} 
                  className={`p-4 border rounded-xl transition-all duration-200 ${
                    ann.pinned 
                      ? "bg-brand-orange-50/15 border-brand-orange-100" 
                      : "bg-white border-zinc-200"
                  }`}
                >
                  <div className="flex justify-between items-start">
                    <span className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">
                      {ann.date}
                    </span>
                    {ann.pinned && (
                      <span className="inline-flex items-center gap-0.5 px-2 py-0.5 bg-brand-orange-100 text-brand-orange-800 text-[8px] font-extrabold uppercase rounded-lg">
                        <Sparkles className="h-2 w-2 shrink-0" />
                        Pinned
                      </span>
                    )}
                  </div>
                  <h4 className="text-xs font-bold text-zinc-800 mt-1.5">{ann.title}</h4>
                  <p className="text-xs text-zinc-650 mt-1.5 leading-relaxed">{ann.content}</p>
                  <div className="mt-4 pt-2.5 border-t border-zinc-100 flex items-center justify-between text-[9px] font-bold text-zinc-400 uppercase tracking-wider">
                    <span>By: {ann.author}</span>
                    <span className="text-brand-green-700">{ann.role}</span>
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

