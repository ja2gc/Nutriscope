"use client";

import React, { useEffect, useMemo, useState } from "react";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import { ImageCarousel, ImageUploadGallery, imagesFromSrcs, imageSrcs, type UploadImage } from "@/components/ui/ImageUploadGallery";
import { fetchPatients, Patient } from "@/services/patientService";
import {
  Announcement,
  AnnouncementCategory,
  AnnouncementVisibility,
  createAnnouncement,
  fetchAnnouncements,
  updateAnnouncement,
} from "@/services/announcementService";
import { categoryStyles } from "@/components/announcements/AnnouncementsBoard";
import { FssDashboardSummary, getFssDashboard } from "@/services/menuCycleService";
import { Calendar, Compass, HeartHandshake, PencilLine, TrendingUp, X } from "lucide-react";
import { personDisplayName } from "@/lib/personName";

type AnnouncementDraft = {
  category: AnnouncementCategory;
  visibility: AnnouncementVisibility;
  pinned: boolean;
  title: string;
  body: string;
  images: UploadImage[];
};

type FollowUpRow = {
  patientId: number;
  name: string;
  systemId: string;
  goalType: string;
  nextFollowUpDate: string;
  daysRemaining: number;
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
          name: personDisplayName(patient),
          systemId: `NS-${String(patient.id).padStart(5, "0")}`,
          goalType: patient.ncp_records?.[0]?.intervention?.goal_type?.trim() || "Not yet completed",
          nextFollowUpDate: patient.next_followup_date,
          daysRemaining: diffDays,
        },
      ];
    })
    .sort((left, right) => left.daysRemaining - right.daysRemaining);
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function pesoAmount(n: number) {
  return `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Pending PO headline: open-execution POs and what they're waiting on.
const WAITING_LABELS: Record<string, string> = {
  receipts: "receipts",
  served_population: "served population",
};

function pendingPoKpi(d: FssDashboardSummary | null): { value: string; sub: string } {
  if (!d || d.pending_pos_count === 0) {
    return { value: "0", sub: "No POs in open execution" };
  }
  const reasons = Array.from(
    new Set(d.pending_pos.flatMap((p) => p.waiting_on.map((w) => WAITING_LABELS[w] ?? w))),
  );
  return {
    value: String(d.pending_pos_count),
    sub: reasons.length ? `Waiting on ${reasons.join(", ")}` : "In open execution",
  };
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
  const [activePatientTotal, setActivePatientTotal] = useState(0);
  const [followUpMeta, setFollowUpMeta] = useState<PaginationMeta | null>(null);
  const [fssDashboard, setFssDashboard] = useState<FssDashboardSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [announcementsLoading, setAnnouncementsLoading] = useState(true);
  const [announcementsSaving, setAnnouncementsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [announcementError, setAnnouncementError] = useState<string | null>(null);
  const [posts, setPosts] = useState<Announcement[]>([]);
  const [announcementsMeta, setAnnouncementsMeta] = useState<PaginationMeta | null>(null);
  const [announcementsRefresh, setAnnouncementsRefresh] = useState(0);
  const [composerOpen, setComposerOpen] = useState(false);
  const [editingPostId, setEditingPostId] = useState<number | null>(null);
  const [viewingPostId, setViewingPostId] = useState<number | null>(null);
  const [followUpPage, setFollowUpPage] = useState(1);
  const [announcementsPage, setAnnouncementsPage] = useState(1);
  const FOLLOW_UPS_PER_PAGE = 3;
  const ANNOUNCEMENTS_PER_PAGE = 2;
  const [draft, setDraft] = useState<AnnouncementDraft>({
    category: "General",
    visibility: "All",
    pinned: false,
    title: "",
    body: "",
    images: [],
  });

  useEffect(() => {
    async function loadDashboard() {
      try {
        setLoading(true);
        setError(null);
        const response = await fetchPatients("", "All", followUpPage, FOLLOW_UPS_PER_PAGE, true);
        setPatients(response.data);
        setFollowUpMeta(response.meta ?? {
          current_page: followUpPage,
          per_page: FOLLOW_UPS_PER_PAGE,
          total: response.data.length,
          last_page: 1,
        });
      } catch (err: unknown) {
        setError(err instanceof Error ? err.message : "Failed to load dashboard data.");
      } finally {
        setLoading(false);
      }
    }

    void loadDashboard();
  }, [followUpPage]);

  useEffect(() => {
    fetchPatients("", "Active", 1, 1)
      .then((response) => setActivePatientTotal(response.meta?.total ?? response.data.length))
      .catch(() => setActivePatientTotal(0));
  }, []);

  useEffect(() => {
    // Best-effort for the KPI card — a failure here must not block the dashboard.
    getFssDashboard()
      .then(setFssDashboard)
      .catch(() => setFssDashboard(null));
  }, []);

  useEffect(() => {
    async function loadAnnouncements() {
      try {
        setAnnouncementsLoading(true);
        setAnnouncementError(null);
        const result = await fetchAnnouncements(announcementsPage, ANNOUNCEMENTS_PER_PAGE);
        setPosts(sortAnnouncements(result.data));
        setAnnouncementsMeta(result.meta);
      } catch (err: unknown) {
        setAnnouncementError(err instanceof Error ? err.message : "Failed to load announcements.");
      } finally {
        setAnnouncementsLoading(false);
      }
    }

    void loadAnnouncements();
  }, [announcementsPage, announcementsRefresh]);

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
  const patientCountLabel = loading ? "--" : activePatientTotal.toString();
  const upcomingFollowUpLabel = loading ? "--" : (followUpMeta?.total ?? 0).toString();
  const pendingKpi = useMemo(() => pendingPoKpi(fssDashboard), [fssDashboard]);
  const orderedPosts = useMemo(() => sortAnnouncements(posts), [posts]);

  const pagedFollowUps = followUps;
  const pagedPosts = orderedPosts;
  const selectedPost = useMemo(
    () => orderedPosts.find((post) => post.id === viewingPostId) || null,
    [orderedPosts, viewingPostId]
  );
  const canEditSelectedPost = selectedPost ? isAnnouncementEditable(selectedPost, user?.id) : false;

  function resetDraft() {
    setDraft({
      category: "General",
      visibility: "All",
      pinned: false,
      title: "",
      body: "",
      images: [],
    });
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
      pinned: post.pinned,
      title: post.title,
      body: post.body,
      images: imagesFromSrcs(post.attachments?.length ? post.attachments : (post.attachment ? [post.attachment] : [])),
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

  function handlePinnedChange(e: React.ChangeEvent<HTMLInputElement>) {
    setDraft((prev) => ({ ...prev, pinned: e.target.checked }));
  }

  async function saveAnnouncement(e: React.FormEvent) {
    e.preventDefault();

    const attachments = imageSrcs(draft.images);
    const hasContent = Boolean(draft.title.trim() || draft.body.trim() || attachments.length);
    if (!hasContent) {
      return;
    }

    const payload = {
      category: draft.category,
      visibility: draft.visibility,
      pinned: draft.pinned,
      title: draft.title.trim() || "Announcement",
      body: draft.body.trim(),
      attachment: attachments[0] ?? null,
      attachments,
    };

    try {
      setAnnouncementsSaving(true);
      setAnnouncementError(null);

      if (editingPostId) {
        const updatedPost = await updateAnnouncement(editingPostId, payload);
        setPosts((prev) => sortAnnouncements(prev.map((post) => (post.id === updatedPost.id ? updatedPost : post))));
      } else {
        await createAnnouncement(payload);
        setAnnouncementsPage(1);
      }
      setAnnouncementsRefresh((value) => value + 1);

      closeComposer();
      resetDraft();
    } catch (err: unknown) {
      setAnnouncementError(err instanceof Error ? err.message : "Failed to save announcement.");
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
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-forest-900/45 backdrop-blur-sm"
          onClick={handleModalBackdropClick}
        >
          <div
            className="w-full max-w-2xl max-h-[90vh] bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-2xl flex flex-col"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-warm-100 bg-warm-50 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-sm font-bold text-warm-900 uppercase tracking-[0.18em]">
                  {editingPostId ? "Edit Announcement" : "Create Announcement"}
                </h3>
                <p className="text-xs text-warm-500 mt-1">
                  Post content stays hidden until you open the composer.
                </p>
              </div>
              <button
                type="button"
                onClick={closeComposer}
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-warm-200 text-xs font-bold uppercase tracking-wider text-warm-600 hover:text-warm-900 hover:bg-white transition-colors"
              >
                <X className="h-3.5 w-3.5" />
                Close
              </button>
            </div>

            <form onSubmit={saveAnnouncement} className="flex min-h-0 flex-1 flex-col">
              <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-warm-500 uppercase tracking-wider">
                    Category
                  </label>
                  <select
                    name="category"
                    value={draft.category}
                    onChange={handleDraftChange}
                    className="w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-xl text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                  >
                    <option value="General">General</option>
                    <option value="Event">Event</option>
                    <option value="Operational">Operational</option>
                    <option value="Urgent">Urgent</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-warm-500 uppercase tracking-wider">
                    Visibility
                  </label>
                  <select
                    name="visibility"
                    value={draft.visibility}
                    onChange={handleDraftChange}
                    className="w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-xl text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
                  >
                    <option value="All">All</option>
                    <option value="FSS">FSS</option>
                    <option value="Admin">Admin</option>
                  </select>
                </div>

                <div className="space-y-1.5 sm:col-span-2">
                  <label className="text-xs font-semibold text-warm-500 uppercase tracking-wider">
                    Title
                  </label>
                  <input
                    name="title"
                    value={draft.title}
                    onChange={handleDraftChange}
                    className="w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-xl text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 placeholder:text-warm-400"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-warm-500 uppercase tracking-wider">
                  Body
                </label>
                <textarea
                  name="body"
                  value={draft.body}
                  onChange={handleDraftChange}
                  className="w-full px-3 py-2 text-base bg-white border border-warm-300 rounded-xl text-warm-900 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 placeholder:text-warm-400 min-h-32"
                />
              </div>

              <div className="flex items-center gap-2.5">
                <input
                  id="dashboard-pinned-toggle"
                  type="checkbox"
                  checked={draft.pinned}
                  onChange={handlePinnedChange}
                  className="h-4 w-4 rounded border-warm-300 text-brand-green-600 focus:ring-brand-green-500/20"
                />
                <label
                  htmlFor="dashboard-pinned-toggle"
                  className="text-sm font-semibold text-warm-700 select-none cursor-pointer"
                >
                  Pin to top of feed
                </label>
              </div>

              <ImageUploadGallery
                images={draft.images}
                onImagesChange={(images) => setDraft((prev) => ({ ...prev, images }))}
                label="Images"
              />
              </div>

              <div className="sticky bottom-0 flex items-center justify-between gap-3 border-t border-warm-100 bg-white px-5 py-4">
                <div className="flex flex-wrap gap-2">
                  {Object.entries(categoryStyles).map(([label, className]) => (
                    <span
                      key={label}
                      className={`inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border ${className}`}
                    >
                      {label}
                    </span>
                  ))}
                </div>
                <Button
                  variant="primary"
                  loading={announcementsSaving}
                  className="w-auto px-4 py-2 text-xs font-bold uppercase tracking-wider"
                >
                  {editingPostId ? "Save Changes" : "Post Announcement"}
                </Button>
              </div>
              {announcementError && (
                <div className="mx-5 mb-4 text-sm font-semibold text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
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
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-forest-900/45 backdrop-blur-sm"
          onClick={handleModalBackdropClick}
        >
          <div
            className="w-full max-w-3xl bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-warm-100 bg-warm-50 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-sm font-bold text-warm-900 uppercase tracking-[0.18em]">
                  Announcement
                </h3>
                <p className="text-xs text-warm-500 mt-1">
                  Facebook-style post view with background blur and author controls.
                </p>
              </div>

              <div className="flex items-center gap-2">
                {canEditSelectedPost && (
                  <button
                    type="button"
                    onClick={() => openEditComposer(selectedPost)}
                    className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-warm-200 text-xs font-bold uppercase tracking-wider text-warm-600 hover:text-warm-900 hover:bg-white transition-colors"
                  >
                    <PencilLine className="h-3.5 w-3.5" />
                    Edit
                  </button>
                )}
                <button
                  type="button"
                  onClick={closeViewer}
                  className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-warm-200 text-xs font-bold uppercase tracking-wider text-warm-600 hover:text-warm-900 hover:bg-white transition-colors"
                >
                  <X className="h-3.5 w-3.5" />
                  Close
                </button>
              </div>
            </div>

            <div className="p-5 bg-warm-50/50">
              <article className="bg-white border border-warm-200 rounded-3xl p-5 shadow-sm">
                <div className="flex items-start gap-3">
                  <div className="h-11 w-11 rounded-full bg-brand-green-700 text-white flex items-center justify-center text-sm font-bold uppercase">
                    {getInitials(selectedPost.author?.name || "")}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div className="text-base font-bold text-warm-900">{selectedPost.author?.name}</div>
                        <div className="text-xs font-semibold uppercase tracking-wider text-warm-400">
                          {selectedPost.author?.role} / {formatTimeStamp(selectedPost.created_at)}
                          {selectedPost.updated_at && selectedPost.updated_at !== selectedPost.created_at ? " / Edited" : ""}
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        {selectedPost.pinned && (
                          <span className="inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border bg-orange-50 text-[#EA580C] border-orange-200">
                            Pinned
                          </span>
                        )}
                        <span
                          className={`inline-flex px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider border ${categoryStyles[selectedPost.category]}`}
                        >
                          {selectedPost.category}
                        </span>
                      </div>
                    </div>

                    <div className="mt-4 space-y-3">
                      <h4 className="text-base font-extrabold text-warm-900 tracking-tight">
                        {selectedPost.title}
                      </h4>
                      <p className="text-base text-warm-700 leading-7 whitespace-pre-wrap">
                        {selectedPost.body}
                      </p>
                    </div>

                    <ImageCarousel
                      images={imagesFromSrcs(selectedPost.attachments?.length ? selectedPost.attachments : (selectedPost.attachment ? [selectedPost.attachment] : []))}
                      title={selectedPost.title}
                      className="mt-4"
                    />

                    <div className="mt-4 border-t border-warm-100 pt-3 text-xs font-bold uppercase tracking-wider text-warm-400">
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
      <div className="flex items-center gap-2 text-sm font-semibold text-warm-400 select-none">
        <span>Home</span>
        <span className="text-warm-300">/</span>
        <span className="text-warm-600 font-bold">Dashboard</span>
      </div>

      <div className="border-b border-warm-200 pb-5">
        <h2 className="text-xl font-extrabold text-warm-900 tracking-tight flex items-center gap-2.5">
          <Compass className="h-5 w-5 text-emerald-600" />
          {user ? `Good morning, ${personDisplayName(user)}` : "RND Dashboard"}
        </h2>
        <p className="text-sm text-warm-500 mt-1 select-none">
          Follow-ups, patient oversight, and a social-feed style announcement board built for the clinical workflow.
        </p>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-start gap-3">
          <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-xs font-black text-red-600 shrink-0 mt-0.5">
            !
          </span>
          <div className="text-sm text-red-700 font-bold">{error}</div>
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div className="bg-white border border-warm-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
          <div>
            <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">
              Patients in Care
            </span>
            <span className="text-lg font-extrabold text-warm-900 mt-1 block">
              {patientCountLabel}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-100">
            <HeartHandshake className="h-5 w-5" />
          </div>
        </div>

        <div className="bg-white border border-warm-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
          <div>
            <span className="text-xs font-extrabold text-warm-400 uppercase tracking-wider block">
              Upcoming Follow-ups
            </span>
            <span className="text-lg font-extrabold text-warm-900 mt-1 block">
              {upcomingFollowUpLabel}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-sky-50 text-sky-700 border border-sky-100">
            <Calendar className="h-5 w-5" />
          </div>
        </div>

        <div className="bg-white border border-warm-200 rounded-2xl p-4 flex items-center justify-between shadow-sm">
          <div>
            <Link href="/food-service/procurement" className="text-xs font-extrabold text-[#EA580C] uppercase tracking-wider block hover:underline">
              Pending POs
            </Link>
            <span className="text-lg font-extrabold text-warm-900 mt-1 block">{pendingKpi.value}</span>
            <span className="text-xs font-bold text-warm-500 uppercase tracking-wider block mt-1">
              {pendingKpi.sub}
            </span>
          </div>
          <div className="p-2.5 rounded-xl bg-orange-50 text-[#EA580C] border border-orange-100">
            <TrendingUp className="h-5 w-5" />
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1.12fr)_minmax(380px,0.88fr)] gap-6 items-start">
        <div className="space-y-4">
          <div className="bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-sm xl:h-[480px] flex flex-col">
            <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-sm font-bold text-warm-900 uppercase tracking-[0.18em]">
                  Patient Snapshot
                </h3>
                <p className="text-xs text-warm-500 mt-1">
                  Open the patient profile to continue the NCP cycle or review the next follow-up.
                </p>
              </div>
              <Link
                href="/ncp/patients"
                className="inline-flex px-3 py-1.5 bg-brand-green-600 hover:bg-brand-green-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors"
              >
                Open Patients
              </Link>
            </div>

            {loading ? (
              <div className="p-5 space-y-4 flex-1 overflow-hidden">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  {[1, 2, 3].map((index) => (
                    <div key={index} className="h-20 rounded-2xl bg-warm-100 animate-pulse" />
                  ))}
                </div>
                <div className="space-y-3 pt-2">
                  {[1, 2, 3, 4].map((index) => (
                    <div key={index} className="h-12 rounded-xl bg-warm-100 animate-pulse" />
                  ))}
                </div>
              </div>
            ) : followUps.length === 0 ? (
              <div className="flex flex-1 items-center justify-center p-8 text-center">
                <div>
                <div className="p-3 bg-warm-50 border border-warm-200 rounded-2xl w-fit mx-auto text-warm-400">
                  <HeartHandshake className="h-8 w-8" />
                </div>
                <h3 className="text-base font-bold text-warm-800 mt-4">No follow-ups scheduled yet</h3>
                <p className="text-sm text-warm-500 mt-1 max-w-sm mx-auto leading-relaxed">
                  Once interventions are recorded, the next review dates will appear here.
                </p>
                </div>
              </div>
            ) : (
              <div className="flex min-h-0 flex-1 flex-col">
                <div className="min-h-0 flex-1 space-y-3 p-4">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div className="rounded-2xl border border-warm-200 bg-warm-50 p-3">
                    <div className="text-xs font-extrabold text-warm-400 uppercase tracking-wider">
                      Active Patients
                    </div>
                    <div className="mt-1 text-xl font-extrabold text-warm-900">{patientCountLabel}</div>
                  </div>
                  <div className="rounded-2xl border border-warm-200 bg-warm-50 p-3">
                    <div className="text-xs font-extrabold text-warm-400 uppercase tracking-wider">
                      Follow-ups Due
                    </div>
                    <div className="mt-1 text-xl font-extrabold text-warm-900">{upcomingFollowUpLabel}</div>
                  </div>
                  <div className="rounded-2xl border border-warm-200 bg-warm-50 p-3">
                    <div className="text-xs font-extrabold text-warm-400 uppercase tracking-wider">
                      Review Window
                    </div>
                    <div className="mt-1 text-xl font-extrabold text-warm-900">48h</div>
                  </div>
                </div>

                <div className="overflow-x-auto rounded-2xl border border-warm-200">
                  <table className="w-full text-left border-collapse min-w-[480px]">
                    <thead>
                      <tr className="bg-warm-50 border-b border-warm-200">
                        <th className="px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                          Patient
                        </th>
                        <th className="px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                          Intervention Goal
                        </th>
                        <th className="px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                          Next Follow-up
                        </th>
                        <th className="px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider">
                          Days Remaining
                        </th>
                        <th className="px-4 py-3 text-xs font-extrabold text-warm-500 uppercase tracking-wider text-right">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-zinc-100 bg-white">
                      {pagedFollowUps.map((row, index) => (
                        <tr
                          key={`${row.patientId}-${row.nextFollowUpDate}`}
                          className={`${index % 2 === 0 ? "bg-white" : "bg-warm-50/20"} hover:bg-warm-50/60 transition-colors`}
                        >
                          <td className="px-4 py-2.5">
                            <div className="text-sm font-bold text-warm-900">{row.name}</div>
                            <div className="text-xs font-mono text-warm-400 mt-1">{row.systemId}</div>
                          </td>
                          <td className="px-4 py-2.5 text-sm text-warm-700 font-medium">{row.goalType}</td>
                          <td className="px-4 py-2.5 text-sm text-warm-700 font-semibold">
                            {new Date(row.nextFollowUpDate).toLocaleDateString("en-US", {
                              month: "short",
                              day: "numeric",
                              year: "numeric",
                            })}
                          </td>
                          <td className="px-4 py-2.5 text-sm font-semibold">
                            <span className="inline-flex px-2.5 py-0.5 rounded-full border bg-warm-50 text-warm-700 border-warm-200">
                              {formatDaysRemaining(row.daysRemaining)}
                            </span>
                          </td>
                          <td className="px-4 py-2.5 text-right">
                            <Link
                              href={`/ncp/patients/${row.patientId}`}
                              className="inline-flex px-3 py-1.5 bg-brand-green-600 hover:bg-brand-green-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors"
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
                <Pagination
                  meta={followUpMeta}
                  page={followUpPage}
                  onPageChange={setFollowUpPage}
                />
              </div>
            )}
          </div>
        </div>

        <div className="bg-white border border-warm-200 rounded-3xl overflow-hidden shadow-sm xl:h-[480px] flex flex-col">
          <div className="px-5 py-4 border-b border-warm-100 flex items-center justify-between gap-4">
            <div>
              <h3 className="text-sm font-bold text-warm-900 uppercase tracking-[0.18em]">
                Announcements
              </h3>
              <p className="text-xs text-warm-500 mt-1">
                Social-feed layout on the right. Open a post to view it in a blurred modal.
              </p>
            </div>
            <Link
              href="/announcements"
              className="inline-flex px-3 py-1.5 bg-brand-green-600 hover:bg-brand-green-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors"
            >
              Manage announcements →
            </Link>
          </div>

          <div className="min-h-0 flex-1 p-3">
            {announcementsLoading ? (
              <div className="space-y-2">
                {[1, 2].map((index) => (
                  <div key={index} className="h-28 rounded-2xl bg-warm-100 animate-pulse" />
                ))}
              </div>
            ) : orderedPosts.length === 0 ? (
              <div className="border border-dashed border-warm-200 rounded-3xl p-8 text-center text-sm text-warm-400 bg-warm-50/40">
                Announcements will appear here once configured.
              </div>
            ) : (
              <div className="space-y-2">
              {pagedPosts.map((post) => {
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
                    className="cursor-pointer rounded-2xl border border-warm-200 bg-white p-3 shadow-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-warm-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                  >
                    <div className="flex items-start gap-2.5">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-green-700 text-xs font-bold uppercase text-white">
                        {getInitials(post.author?.name || "")}
                      </div>

                      <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                          <div>
                            <div className="text-sm font-bold text-warm-900">{post.author?.name}</div>
                            <div className="text-xs font-semibold uppercase tracking-wider text-warm-400">
                              {post.author?.role} / {formatTimeStamp(post.created_at)}
                              {post.updated_at && post.updated_at !== post.created_at ? " / Edited" : ""}
                            </div>
                          </div>

                          <div className="flex items-center gap-1.5">
                            {post.pinned && (
                              <span className="inline-flex rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs font-extrabold uppercase tracking-wider text-[#EA580C]">
                                Pinned
                              </span>
                            )}
                            <span
                              className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-extrabold uppercase tracking-wider ${categoryStyles[post.category]}`}
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
                                className="inline-flex items-center gap-1 rounded-full border border-warm-200 px-2 py-0.5 text-xs font-extrabold uppercase tracking-wider text-warm-600 transition-colors hover:bg-warm-50 hover:text-warm-900"
                                title="Edit your post"
                              >
                                <PencilLine className="h-3 w-3" />
                                Edit
                              </button>
                            )}
                          </div>
                        </div>

                        <div className="mt-2 space-y-1">
                          <h4 className="truncate text-sm font-bold tracking-tight text-warm-900">{post.title}</h4>
                          <p className="line-clamp-1 text-xs leading-relaxed text-warm-600">
                            {post.body}
                          </p>
                        </div>
                      </div>
                    </div>
                  </article>
                );
              })}
              </div>
            )}
          </div>
          {!announcementsLoading && orderedPosts.length > 0 && (
            <Pagination
              meta={announcementsMeta}
              page={announcementsPage}
              onPageChange={setAnnouncementsPage}
            />
          )}
        </div>
      </div>

      {renderAnnouncementModal()}
    </div>
  );
}
