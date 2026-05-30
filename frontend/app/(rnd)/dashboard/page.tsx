"use client";

import React, { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import { fetchPatients, Patient } from "@/services/patientService";
import {
  Announcement,
  AnnouncementCategory,
  AnnouncementVisibility,
  createAnnouncement,
  fetchAnnouncements,
  updateAnnouncement,
} from "@/services/announcementService";
import { BellDot, Calendar, Compass, HeartHandshake, PencilLine, TrendingUp, X } from "lucide-react";

type AnnouncementDraft = {
  category: AnnouncementCategory;
  visibility: AnnouncementVisibility;
  title: string;
  body: string;
  imageName: string;
  imageDataUrl: string;
};

type FollowUpRow = {
  patientId: number;
  name: string;
  systemId: string;
  goalType: string;
  nextFollowUpDate: string;
  daysRemaining: number;
};

const categoryStyles: Record<AnnouncementCategory, string> = {
  General: "bg-zinc-100 text-zinc-700 border-zinc-200",
  Event: "bg-orange-50 text-[#EA580C] border-orange-200",
  Operational: "bg-blue-50 text-blue-700 border-blue-100",
  Urgent: "bg-red-50 text-red-700 border-red-100",
};

function formatDaysRemaining(value: number) {
  if (value === 0) {
    return "Today";
  }

  if (value > 0) {
    return value === 1 ? "In 1 day" : `In ${value} days`;
  }

  const overdue = Math.abs(value);
  return overdue === 1 ? "1 day overdue" : `${overdue} days overdue`;
}

function formatTimeStamp(value: string) {
  return new Date(value).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function getInitials(name: string) {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

function buildFollowUps(patients: Patient[]): FollowUpRow[] {
  const today = new Date();

  return patients
    .flatMap((patient) => {
      if (!patient.next_followup_date) {
        return [];
      }

      const followUpDate = new Date(patient.next_followup_date);
      if (Number.isNaN(followUpDate.getTime())) {
        return [];
      }

      const diffDays = Math.round((followUpDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

      return [
        {
          patientId: patient.id,
          name: patient.name,
          systemId: `NS-${String(patient.id).padStart(5, "0")}`,
          goalType: patient.ncp_records?.[0]?.intervention?.goal_type?.trim() || "Not yet completed",
          nextFollowUpDate: patient.next_followup_date,
          daysRemaining: diffDays,
        },
      ];
    })
    .sort((left, right) => left.daysRemaining - right.daysRemaining)
    .slice(0, 8);
}

function sortAnnouncements(posts: Announcement[]) {
  return [...posts].sort((left, right) => {
    if (Boolean(left.pinned) !== Boolean(right.pinned)) {
      return left.pinned ? -1 : 1;
    }

    return new Date(right.created_at).getTime() - new Date(left.created_at).getTime();
  });
}

function isAnnouncementEditable(post: Announcement, userId?: number | null) {
  return Boolean(userId) && post.author?.id === userId;
}

export default function RndDashboardPage() {
  const { user } = useAuth();
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(true);
  const [announcementsLoading, setAnnouncementsLoading] = useState(true);
  const [announcementsSaving, setAnnouncementsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [announcementError, setAnnouncementError] = useState<string | null>(null);
  const [posts, setPosts] = useState<Announcement[]>([]);
  const [composerOpen, setComposerOpen] = useState(false);
  const [editingPostId, setEditingPostId] = useState<number | null>(null);
  const [viewingPostId, setViewingPostId] = useState<number | null>(null);
  const [draft, setDraft] = useState<AnnouncementDraft>({
    category: "General",
    visibility: "All",
    title: "",
    body: "",
    imageName: "",
    imageDataUrl: "",
  });

  useEffect(() => {
    async function loadDashboard() {
      try {
        setLoading(true);
        setError(null);
        const response = await fetchPatients("", "All", 1);
        setPatients(response.data);
      } catch (err: any) {
        setError(err.message || "Failed to load dashboard data.");
      } finally {
        setLoading(false);
      }
    }

    void loadDashboard();
  }, []);

  useEffect(() => {
    async function loadAnnouncements() {
      try {
        setAnnouncementsLoading(true);
        setAnnouncementError(null);
        const data = await fetchAnnouncements();
        setPosts(sortAnnouncements(data));
      } catch (err: any) {
        setAnnouncementError(err.message || "Failed to load announcements.");
      } finally {
        setAnnouncementsLoading(false);
      }
    }

    void loadAnnouncements();
  }, []);

  useEffect(() => {
    if (!composerOpen && viewingPostId === null) {
      return;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setComposerOpen(false);
        setEditingPostId(null);
        setViewingPostId(null);
      }
    };

    window.addEventListener("keydown", handleKeyDown);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [composerOpen, viewingPostId]);

  const followUps = useMemo(() => buildFollowUps(patients), [patients]);
  const activePatients = patients.filter((patient) => patient.status === "Active").length;
  const patientCountLabel = loading ? "--" : activePatients.toString();
  const upcomingFollowUpLabel = loading ? "--" : followUps.length.toString();
  const orderedPosts = useMemo(() => sortAnnouncements(posts), [posts]);
  const selectedPost = useMemo(
    () => orderedPosts.find((post) => post.id === viewingPostId) || null,
    [orderedPosts, viewingPostId]
  );
  const canEditSelectedPost = selectedPost ? isAnnouncementEditable(selectedPost, user?.id) : false;

  function resetDraft() {
    setDraft({
      category: "General",
      visibility: "All",
      title: "",
      body: "",
      imageName: "",
      imageDataUrl: "",
    });
  }

  function openCreateComposer() {
    setEditingPostId(null);
    resetDraft();
    setComposerOpen(true);
  }

  function openEditComposer(post: Announcement) {
    if (!isAnnouncementEditable(post, user?.id)) {
      return;
    }

    setViewingPostId(null);
    setEditingPostId(post.id);
    setDraft({
      category: post.category,
      visibility: post.visibility,
      title: post.title,
      body: post.body,
      imageName: post.attachment ? "Current attachment" : "",
      imageDataUrl: post.attachment || "",
    });
    setComposerOpen(true);
  }

  function openViewer(post: Announcement) {
    setComposerOpen(false);
    setEditingPostId(null);
    setViewingPostId(post.id);
  }

  function closeComposer() {
    setComposerOpen(false);
    setEditingPostId(null);
  }

  function closeViewer() {
    setViewingPostId(null);
  }

  function handleDraftChange(
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) {
    const { name, value } = e.target;
    setDraft((prev) => ({ ...prev, [name]: value } as AnnouncementDraft));
  }

  function handleImageUpload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];

    if (!file) {
      setDraft((prev) => ({ ...prev, imageName: "", imageDataUrl: "" }));
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      setDraft((prev) => ({
        ...prev,
        imageName: file.name,
        imageDataUrl: typeof reader.result === "string" ? reader.result : "",
      }));
    };
    reader.readAsDataURL(file);
  }

  async function saveAnnouncement(e: React.FormEvent) {
    e.preventDefault();

    const hasContent = Boolean(draft.title.trim() || draft.body.trim() || draft.imageDataUrl);
    if (!hasContent) {
      return;
    }

    const payload = {
      category: draft.category,
      visibility: draft.visibility,
      title: draft.title.trim() || "Announcement",
      body: draft.body.trim(),
      attachment: draft.imageDataUrl || null,
    };

    try {
      setAnnouncementsSaving(true);
      setAnnouncementError(null);

      if (editingPostId) {
        const updatedPost = await updateAnnouncement(editingPostId, payload);
        setPosts((prev) => sortAnnouncements(prev.map((post) => (post.id === updatedPost.id ? updatedPost : post))));
      } else {
        const createdPost = await createAnnouncement(payload);
        setPosts((prev) => sortAnnouncements([createdPost, ...prev]));
      }

      closeComposer();
      resetDraft();
    } catch (err: any) {
      setAnnouncementError(err.message || "Failed to save announcement.");
    } finally {
      setAnnouncementsSaving(false);
    }
  }

  function handleModalBackdropClick() {
    closeComposer();
    closeViewer();
  }

  function renderAnnouncementModal() {
    if (composerOpen) {
      return (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/45 backdrop-blur-sm"
          onClick={handleModalBackdropClick}
        >
          <div
            className="w-full max-w-2xl bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                  {editingPostId ? "Edit Announcement" : "Create Announcement"}
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Post content stays hidden until you open the composer.
                </p>
              </div>
              <button
                type="button"
                onClick={closeComposer}
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-200 text-[10px] font-bold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-white transition-colors"
              >
                <X className="h-3.5 w-3.5" />
                Close
              </button>
            </div>

            <form onSubmit={saveAnnouncement} className="p-5 space-y-5">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
                    Category
                  </label>
                  <select
                    name="category"
                    value={draft.category}
                    onChange={handleDraftChange}
                    className="w-full px-3 py-2 text-sm bg-white border border-zinc-300 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                  >
                    <option value="General">General</option>
                    <option value="Event">Event</option>
                    <option value="Operational">Operational</option>
                    <option value="Urgent">Urgent</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
                    Visibility
                  </label>
                  <select
                    name="visibility"
                    value={draft.visibility}
                    onChange={handleDraftChange}
                    className="w-full px-3 py-2 text-sm bg-white border border-zinc-300 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                  >
                    <option value="All">All</option>
                    <option value="FSS">FSS</option>
                    <option value="Admin">Admin</option>
                  </select>
                </div>

                <div className="space-y-1.5 sm:col-span-2">
                  <label className="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
                    Title
                  </label>
                  <input
                    name="title"
                    value={draft.title}
                    onChange={handleDraftChange}
                    placeholder="Announcement title"
                    className="w-full px-3 py-2 text-sm bg-white border border-zinc-300 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 placeholder:text-zinc-400"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
                  Body
                </label>
                <textarea
                  name="body"
                  value={draft.body}
                  onChange={handleDraftChange}
                  placeholder="Write the announcement"
                  className="w-full px-3 py-2 text-sm bg-white border border-zinc-300 rounded-xl text-zinc-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 placeholder:text-zinc-400 min-h-32"
                />
              </div>

              <div className="space-y-2">
                <label className="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
                  Image
                </label>
                <input
                  type="file"
                  accept="image/*"
                  onChange={handleImageUpload}
                  className="block w-full text-xs text-zinc-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-zinc-950 file:text-white file:text-[10px] file:font-bold file:uppercase file:tracking-wider"
                />
                {draft.imageDataUrl ? (
                  <div className="rounded-2xl border border-zinc-200 overflow-hidden bg-white">
                    <img
                      src={draft.imageDataUrl}
                      alt={draft.imageName || "Announcement preview"}
                      className="block h-44 w-full object-cover"
                    />
                  </div>
                ) : (
                  <div className="rounded-2xl border border-dashed border-zinc-200 p-6 text-center text-xs text-zinc-400 bg-white">
                    Image preview appears here after upload.
                  </div>
                )}
              </div>

              <div className="flex items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                  {Object.entries(categoryStyles).map(([label, className]) => (
                    <span
                      key={label}
                      className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${className}`}
                    >
                      {label}
                    </span>
                  ))}
                </div>
                <Button
                  variant="primary"
                  loading={announcementsSaving}
                  className="w-auto px-4 py-2 text-[10px] font-bold uppercase tracking-wider"
                >
                  {editingPostId ? "Save Changes" : "Post Announcement"}
                </Button>
              </div>
              {announcementError && (
                <div className="text-xs font-semibold text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
                  {announcementError}
                </div>
              )}
            </form>
          </div>
        </div>
      );
    }

    if (selectedPost) {
      return (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/45 backdrop-blur-sm"
          onClick={handleModalBackdropClick}
        >
          <div
            className="w-full max-w-3xl bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                  Announcement
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Facebook-style post view with background blur and author controls.
                </p>
              </div>

              <div className="flex items-center gap-2">
                {canEditSelectedPost && (
                  <button
                    type="button"
                    onClick={() => openEditComposer(selectedPost)}
                    className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-200 text-[10px] font-bold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-white transition-colors"
                  >
                    <PencilLine className="h-3.5 w-3.5" />
                    Edit
                  </button>
                )}
                <button
                  type="button"
                  onClick={closeViewer}
                  className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-200 text-[10px] font-bold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-white transition-colors"
                >
                  <X className="h-3.5 w-3.5" />
                  Close
                </button>
              </div>
            </div>

            <div className="p-5 bg-zinc-50/50">
              <article className="bg-white border border-zinc-200 rounded-3xl p-5 shadow-sm">
                <div className="flex items-start gap-3">
                  <div className="h-11 w-11 rounded-full bg-zinc-950 text-white flex items-center justify-center text-xs font-bold uppercase">
                    {getInitials(selectedPost.author?.name || "")}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div className="text-sm font-bold text-zinc-950">{selectedPost.author?.name}</div>
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">
                          {selectedPost.author?.role} / {formatTimeStamp(selectedPost.created_at)}
                          {selectedPost.updated_at && selectedPost.updated_at !== selectedPost.created_at ? " / Edited" : ""}
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        {selectedPost.pinned && (
                          <span className="inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-orange-50 text-[#EA580C] border-orange-200">
                            Pinned
                          </span>
                        )}
                        <span
                          className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${categoryStyles[selectedPost.category]}`}
                        >
                          {selectedPost.category}
                        </span>
                      </div>
                    </div>

                    <div className="mt-4 space-y-3">
                      <h4 className="text-base font-extrabold text-zinc-950 tracking-tight">
                        {selectedPost.title}
                      </h4>
                      <p className="text-sm text-zinc-700 leading-7 whitespace-pre-wrap">
                        {selectedPost.body}
                      </p>
                    </div>

                    {selectedPost.attachment && (
                      <div className="mt-4 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50">
                        <img
                          src={selectedPost.attachment}
                          alt={selectedPost.title}
                          className="block w-full max-h-[520px] object-cover"
                        />
                      </div>
                    )}

                    <div className="mt-4 border-t border-zinc-100 pt-3 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                      Posted to department announcements
                    </div>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </div>
      );
    }

    return null;
  }

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <span>Home</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-600 font-bold">Dashboard</span>
      </div>

      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Compass className="h-5 w-5 text-emerald-600" />
          {user ? `Good morning, ${user.name}` : "RND Dashboard"}
        </h2>
        <p className="text-xs text-zinc-500 mt-1 select-none">
          Follow-ups, patient oversight, and a social-feed style announcement board built for the clinical workflow.
        </p>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-start gap-3">
          <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-[10px] font-black text-red-600 shrink-0 mt-0.5">
            !
          </span>
          <div className="text-xs text-red-700 font-bold">{error}</div>
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div className="bg-white border border-zinc-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block">
              Patients in Care
            </span>
            <span className="text-lg font-extrabold text-zinc-950 mt-1 block">
              {patientCountLabel}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-100">
            <HeartHandshake className="h-5 w-5" />
          </div>
        </div>

        <div className="bg-white border border-zinc-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider block">
              Upcoming Follow-ups
            </span>
            <span className="text-lg font-extrabold text-zinc-950 mt-1 block">
              {upcomingFollowUpLabel}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-blue-50 text-blue-700 border border-blue-100">
            <Calendar className="h-5 w-5" />
          </div>
        </div>

        <div className="bg-white border border-zinc-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
          <div>
            <span className="text-[10px] font-extrabold text-[#EA580C] uppercase tracking-wider block">
              Budget Per Person
            </span>
            <span className="text-lg font-extrabold text-zinc-950 mt-1 block">--</span>
            <span className="text-[10px] font-bold text-zinc-500 uppercase tracking-wider block mt-1">
              Budget tracking available in M9
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-orange-50 text-[#EA580C] border border-orange-100">
            <TrendingUp className="h-5 w-5" />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1.12fr)_minmax(380px,0.88fr)] gap-6 items-start">
        <div className="space-y-4">
          <div className="bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-sm">
            <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                  Patient Snapshot
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Open the patient profile to continue the NCP cycle or review the next follow-up.
                </p>
              </div>
              <Link
                href="/ncp/patients"
                className="inline-flex px-3 py-1.5 bg-zinc-950 hover:bg-zinc-800 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors"
              >
                Open Patients
              </Link>
            </div>

            {loading ? (
              <div className="p-5 space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {[1, 2, 3].map((index) => (
                    <div key={index} className="h-20 rounded-2xl bg-zinc-100 animate-pulse" />
                  ))}
                </div>
                <div className="space-y-3 pt-2">
                  {[1, 2, 3, 4].map((index) => (
                    <div key={index} className="h-12 rounded-xl bg-zinc-100 animate-pulse" />
                  ))}
                </div>
              </div>
            ) : followUps.length === 0 ? (
              <div className="p-12 text-center">
                <div className="p-3 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
                  <HeartHandshake className="h-8 w-8" />
                </div>
                <h3 className="text-sm font-bold text-zinc-800 mt-4">No follow-ups scheduled yet</h3>
                <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
                  Once interventions are recorded, the next review dates will appear here.
                </p>
              </div>
            ) : (
              <div className="p-5 space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div className="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                    <div className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                      Active Patients
                    </div>
                    <div className="text-2xl font-extrabold text-zinc-950 mt-2">{patientCountLabel}</div>
                  </div>
                  <div className="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                    <div className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                      Follow-ups Due
                    </div>
                    <div className="text-2xl font-extrabold text-zinc-950 mt-2">{upcomingFollowUpLabel}</div>
                  </div>
                  <div className="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                    <div className="text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">
                      Review Window
                    </div>
                    <div className="text-2xl font-extrabold text-zinc-950 mt-2">48h</div>
                  </div>
                </div>

                <div className="overflow-x-auto rounded-2xl border border-zinc-200">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="bg-zinc-50 border-b border-zinc-200">
                        <th className="px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">
                          Patient
                        </th>
                        <th className="px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">
                          Intervention Goal
                        </th>
                        <th className="px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">
                          Next Follow-up
                        </th>
                        <th className="px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">
                          Days Remaining
                        </th>
                        <th className="px-4 py-3 text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider text-right">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-zinc-100 bg-white">
                      {followUps.map((row, index) => (
                        <tr
                          key={`${row.patientId}-${row.nextFollowUpDate}`}
                          className={`${index % 2 === 0 ? "bg-white" : "bg-zinc-50/20"} hover:bg-zinc-50/60 transition-colors`}
                        >
                          <td className="px-4 py-4">
                            <div className="text-xs font-bold text-zinc-900">{row.name}</div>
                            <div className="text-[10px] font-mono text-zinc-400 mt-1">{row.systemId}</div>
                          </td>
                          <td className="px-4 py-4 text-xs text-zinc-700 font-medium">{row.goalType}</td>
                          <td className="px-4 py-4 text-xs text-zinc-700 font-semibold">
                            {new Date(row.nextFollowUpDate).toLocaleDateString("en-US", {
                              month: "short",
                              day: "numeric",
                              year: "numeric",
                            })}
                          </td>
                          <td className="px-4 py-4 text-xs font-semibold">
                            <span className="inline-flex px-2.5 py-0.5 rounded-full border bg-zinc-50 text-zinc-700 border-zinc-200">
                              {formatDaysRemaining(row.daysRemaining)}
                            </span>
                          </td>
                          <td className="px-4 py-4 text-right">
                            <Link
                              href={`/ncp/patients/${row.patientId}`}
                              className="inline-flex px-3 py-1.5 bg-zinc-950 hover:bg-zinc-800 text-white text-[10px] font-bold uppercase tracking-wider rounded-lg transition-colors"
                            >
                              Open NCP
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        </div>

        <div className="bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-sm xl:sticky xl:top-6">
          <div className="px-5 py-4 border-b border-zinc-100 flex items-center justify-between gap-4">
            <div>
              <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                Announcements
              </h3>
              <p className="text-[10px] text-zinc-500 mt-1">
                Social-feed layout on the right. Open a post to view it in a blurred modal.
              </p>
            </div>
            <Button
              variant="primary"
              onClick={openCreateComposer}
              className="w-auto px-4 py-2 text-[10px] font-bold uppercase tracking-wider"
            >
              Create Announcement
            </Button>
          </div>

          <div className="p-4 space-y-4">
            {announcementsLoading ? (
              <div className="space-y-3">
                {[1, 2, 3].map((index) => (
                  <div key={index} className="h-32 rounded-3xl bg-zinc-100 animate-pulse" />
                ))}
              </div>
            ) : orderedPosts.length === 0 ? (
              <div className="border border-dashed border-zinc-200 rounded-3xl p-8 text-center text-xs text-zinc-400 bg-zinc-50/40">
                Announcements will appear here once configured.
              </div>
            ) : (
              orderedPosts.map((post) => {
                const canEdit = isAnnouncementEditable(post, user?.id);

                return (
                  <article
                    key={post.id}
                    role="button"
                    tabIndex={0}
                    onClick={() => openViewer(post)}
                    onKeyDown={(event) => {
                      if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        openViewer(post);
                      }
                    }}
                    className="cursor-pointer rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                  >
                    <div className="flex items-start gap-3">
                      <div className="h-11 w-11 rounded-full bg-zinc-950 text-white flex items-center justify-center text-xs font-bold uppercase shrink-0">
                        {getInitials(post.author?.name || "")}
                      </div>

                      <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <div className="text-sm font-bold text-zinc-950">{post.author?.name}</div>
                            <div className="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">
                              {post.author?.role} / {formatTimeStamp(post.created_at)}
                              {post.updated_at && post.updated_at !== post.created_at ? " / Edited" : ""}
                            </div>
                          </div>

                          <div className="flex items-center gap-2">
                            {post.pinned && (
                              <span className="inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-orange-50 text-[#EA580C] border-orange-200">
                                Pinned
                              </span>
                            )}
                            <span
                              className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${categoryStyles[post.category]}`}
                            >
                              {post.category}
                            </span>
                            {canEdit && (
                              <button
                                type="button"
                                onClick={(event) => {
                                  event.stopPropagation();
                                  openEditComposer(post);
                                }}
                                className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-zinc-200 text-[9px] font-extrabold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition-colors"
                                title="Edit your post"
                              >
                                <PencilLine className="h-3 w-3" />
                                Edit
                              </button>
                            )}
                          </div>
                        </div>

                        <div className="mt-3 space-y-2">
                          <h4 className="text-sm font-bold text-zinc-950 tracking-tight">{post.title}</h4>
                          <p className="text-xs text-zinc-600 leading-relaxed whitespace-pre-wrap">
                            {post.body}
                          </p>
                        </div>

                        {post.attachment && (
                          <div className="mt-4 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50">
                            <img
                              src={post.attachment}
                              alt={post.title}
                              className="block w-full max-h-96 object-cover"
                            />
                          </div>
                        )}

                        <div className="mt-4 border-t border-zinc-100 pt-3 text-[10px] font-bold uppercase tracking-wider text-zinc-400">
                          Posted to department announcements
                        </div>
                      </div>
                    </div>
                  </article>
                );
              })
            )}
          </div>
        </div>
      </div>

      {renderAnnouncementModal()}
    </div>
  );
}
