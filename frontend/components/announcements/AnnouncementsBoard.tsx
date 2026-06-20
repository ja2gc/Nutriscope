"use client";

import React, { useEffect, useMemo, useState } from "react";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import { Pagination, PaginationMeta } from "@/components/ui/Pagination";
import {
  Announcement,
  AnnouncementCategory,
  AnnouncementVisibility,
  fetchAdminAnnouncements,
  createAdminAnnouncement,
  updateAdminAnnouncement,
  deleteAdminAnnouncement,
  fetchAnnouncements,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement,
} from "@/services/announcementService";
import { Megaphone, PencilLine, Trash2, X } from "lucide-react";

// Shared category pill styles — exported so other files (e.g. the RND dashboard)
// can import instead of redefining.
export const categoryStyles: Record<AnnouncementCategory, string> = {
  General: "bg-zinc-100 text-zinc-700 border-zinc-200",
  Event: "bg-orange-50 text-[#EA580C] border-orange-200",
  Operational: "bg-blue-50 text-blue-700 border-blue-100",
  Urgent: "bg-red-50 text-red-700 border-red-100",
};

type AnnouncementDraft = {
  title: string;
  body: string;
  category: AnnouncementCategory;
  visibility: AnnouncementVisibility;
  pinned: boolean;
  imageName: string;
  imageDataUrl: string;
};

const EMPTY_DRAFT: AnnouncementDraft = {
  title: "",
  body: "",
  category: "General",
  visibility: "All",
  pinned: false,
  imageName: "",
  imageDataUrl: "",
};

function getInitials(name: string) {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

function formatTimeStamp(value: string) {
  return new Date(value).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

export function AnnouncementsBoard({ variant }: { variant: "admin" | "rnd" }) {
  const isAdmin = variant === "admin";

  // Pick service functions based on variant
  const apiFetch = isAdmin ? fetchAdminAnnouncements : fetchAnnouncements;
  const apiCreate = isAdmin ? createAdminAnnouncement : createAnnouncement;
  const apiUpdate = isAdmin ? updateAdminAnnouncement : updateAnnouncement;
  const apiDelete = isAdmin ? deleteAdminAnnouncement : deleteAnnouncement;

  // Breadcrumb / subtitle differ per variant
  const breadcrumbRoot = isAdmin ? "Admin" : "Home";
  const subtitle = isAdmin
    ? "Broadcast notices to FSS, Admin, or all departments. Admin announcements support pinning."
    : "Post and view department announcements.";

  const { user } = useAuth();
  const [posts, setPosts] = useState<Announcement[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [composerOpen, setComposerOpen] = useState(false);
  const [editingPostId, setEditingPostId] = useState<number | null>(null);
  const [viewingPostId, setViewingPostId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [draft, setDraft] = useState<AnnouncementDraft>(EMPTY_DRAFT);

  async function loadPage(p: number) {
    setPage(p);
    setLoading(true);
    setError(null);
    try {
      const result = await apiFetch(p, 15);
      setPosts(result.data);
      setMeta(result.meta);
    } catch (err: any) {
      setError(err.message || "Failed to load announcements.");
    } finally {
      setLoading(false);
    }
  }

  // Load on mount / variant change
  useEffect(() => {
    void loadPage(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [variant]);

  // Lock body scroll when modal is open
  useEffect(() => {
    if (!composerOpen && viewingPostId === null) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setComposerOpen(false);
        setEditingPostId(null);
        setViewingPostId(null);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener("keydown", onKey);
    };
  }, [composerOpen, viewingPostId]);

  const selectedPost = useMemo(
    () => posts.find((p) => p.id === viewingPostId) ?? null,
    [posts, viewingPostId]
  );

  function resetDraft() {
    setDraft(EMPTY_DRAFT);
  }

  function openCreate() {
    setEditingPostId(null);
    resetDraft();
    setSaveError(null);
    setComposerOpen(true);
  }

  function openEdit(post: Announcement) {
    setViewingPostId(null);
    setEditingPostId(post.id);
    setDraft({
      title: post.title,
      body: post.body,
      category: post.category,
      visibility: post.visibility,
      pinned: post.pinned,
      imageName: post.attachment ? "Current attachment" : "",
      imageDataUrl: post.attachment || "",
    });
    setSaveError(null);
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
    if (!hasContent) return;

    const payload = {
      title: draft.title.trim() || "Announcement",
      body: draft.body.trim(),
      category: draft.category,
      visibility: draft.visibility,
      pinned: isAdmin ? draft.pinned : false,
      attachment: draft.imageDataUrl || null,
    };

    try {
      setSaving(true);
      setSaveError(null);
      if (editingPostId) {
        await apiUpdate(editingPostId, payload);
        closeComposer();
        resetDraft();
        void loadPage(page);
      } else {
        await apiCreate(payload);
        closeComposer();
        resetDraft();
        void loadPage(1);
      }
    } catch (err: any) {
      setSaveError(err.message || "Failed to save announcement.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(post: Announcement) {
    if (!confirm(`Delete announcement: "${post.title}"?`)) return;
    try {
      await apiDelete(post.id);
      const newPage = posts.length === 1 && page > 1 ? page - 1 : page;
      void loadPage(newPage);
    } catch (err: any) {
      alert(err.message || "Failed to delete announcement.");
    }
  }

  // ---------- Modals ----------

  function renderModal() {
    if (composerOpen) {
      return (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/45 backdrop-blur-sm"
          onClick={() => { closeComposer(); closeViewer(); }}
        >
          <div
            className="w-full max-w-2xl bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50 flex items-center justify-between gap-4">
              <div>
                <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                  {editingPostId ? "Edit Announcement" : "Create Announcement"}
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  {isAdmin
                    ? "Admin announcements support pinning and all-department visibility."
                    : "Post content stays hidden until you open the composer."}
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

            <form onSubmit={(e) => void saveAnnouncement(e)} className="p-5 space-y-5">
              {/* Row 1 — Category / Visibility / Title */}
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

              {/* Body */}
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

              {/* Pin toggle — admin only */}
              {isAdmin && (
                <div className="flex items-center gap-2.5">
                  <input
                    id="pinned-toggle"
                    type="checkbox"
                    checked={draft.pinned}
                    onChange={handlePinnedChange}
                    className="h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500/20"
                  />
                  <label
                    htmlFor="pinned-toggle"
                    className="text-xs font-semibold text-zinc-700 select-none cursor-pointer"
                  >
                    Pin to top of feed
                  </label>
                  {draft.pinned && (
                    <span className="inline-flex px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-orange-50 text-[#EA580C] border-orange-200">
                      Pinned
                    </span>
                  )}
                </div>
              )}

              {/* Image */}
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

              {/* Footer */}
              <div className="flex items-center justify-between gap-3">
                <div className="flex flex-wrap gap-2">
                  {Object.entries(categoryStyles).map(([label, cls]) => (
                    <span
                      key={label}
                      className={`inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border ${cls}`}
                    >
                      {label}
                    </span>
                  ))}
                </div>
                <Button
                  variant="primary"
                  loading={saving}
                  className="w-auto px-4 py-2 text-[10px] font-bold uppercase tracking-wider"
                >
                  {editingPostId ? "Save Changes" : "Post Announcement"}
                </Button>
              </div>

              {saveError && (
                <div className="text-xs font-semibold text-red-700 bg-red-50 border border-red-100 rounded-xl px-3 py-2">
                  {saveError}
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
          onClick={closeViewer}
        >
          <div
            className="w-full max-w-3xl bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-100 bg-zinc-50 flex items-center justify-between gap-4">
              <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
                Announcement
              </h3>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => openEdit(selectedPost)}
                  className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-zinc-200 text-[10px] font-bold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-white transition-colors"
                >
                  <PencilLine className="h-3.5 w-3.5" />
                  Edit
                </button>
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
                  <div className="h-11 w-11 rounded-full bg-zinc-950 text-white flex items-center justify-center text-xs font-bold uppercase shrink-0">
                    {getInitials(selectedPost.author?.name || "")}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div className="text-sm font-bold text-zinc-950">{selectedPost.author?.name}</div>
                        <div className="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">
                          {selectedPost.author?.role} / {formatTimeStamp(selectedPost.created_at)}
                          {selectedPost.updated_at !== selectedPost.created_at ? " / Edited" : ""}
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
                        <span className="inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-zinc-50 text-zinc-600 border-zinc-200">
                          {selectedPost.visibility}
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

  // ---------- Page ----------

  return (
    <div className="space-y-6 font-sans">
      {/* Breadcrumb & header */}
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <span>{breadcrumbRoot}</span>
        <span className="text-zinc-300">/</span>
        <span className="text-zinc-600 font-bold">Announcements</span>
      </div>

      <div className="border-b border-zinc-200 pb-5 flex items-start justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Megaphone className="h-5 w-5 text-emerald-600" />
            Announcements
          </h2>
          <p className="text-xs text-zinc-500 mt-1 select-none">
            {subtitle}
          </p>
        </div>
        <Button
          variant="primary"
          onClick={openCreate}
          className="w-auto px-4 py-2 text-[10px] font-bold uppercase tracking-wider shrink-0"
        >
          Create Announcement
        </Button>
      </div>

      {/* Error */}
      {error && (
        <div className="bg-red-50 border border-red-100 p-4 rounded-xl flex items-start gap-3">
          <span className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-[10px] font-black text-red-600 shrink-0 mt-0.5">!</span>
          <div className="text-xs text-red-700 font-bold">{error}</div>
        </div>
      )}

      {/* Feed */}
      <div className="bg-white border border-zinc-200 rounded-3xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-zinc-100">
          <h3 className="text-xs font-bold text-zinc-900 uppercase tracking-[0.18em]">
            Announcements Feed
          </h3>
          <p className="text-[10px] text-zinc-500 mt-1">
            Pinned posts float to the top. Click any post to view details or edit.
          </p>
        </div>

        <div className="p-4 space-y-4">
          {loading ? (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-32 rounded-3xl bg-zinc-100 animate-pulse" />
              ))}
            </div>
          ) : posts.length === 0 ? (
            <div className="border border-dashed border-zinc-200 rounded-3xl p-8 text-center text-xs text-zinc-400 bg-zinc-50/40">
              No announcements yet. Create the first one above.
            </div>
          ) : (
            posts.map((post) => (
              <article
                key={post.id}
                role="button"
                tabIndex={0}
                onClick={() => openViewer(post)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
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
                        <span className="inline-flex px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wider border bg-zinc-50 text-zinc-500 border-zinc-200">
                          {post.visibility}
                        </span>

                        {/* Edit / Delete — stop propagation so click doesn't open viewer */}
                        <button
                          type="button"
                          onClick={(e) => { e.stopPropagation(); openEdit(post); }}
                          className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-zinc-200 text-[9px] font-extrabold uppercase tracking-wider text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition-colors"
                          title="Edit"
                        >
                          <PencilLine className="h-3 w-3" />
                          Edit
                        </button>
                        <button
                          type="button"
                          onClick={(e) => { e.stopPropagation(); void handleDelete(post); }}
                          className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-red-100 text-[9px] font-extrabold uppercase tracking-wider text-red-600 hover:bg-red-50 transition-colors"
                          title="Delete"
                        >
                          <Trash2 className="h-3 w-3" />
                          Delete
                        </button>
                      </div>
                    </div>

                    <div className="mt-3 space-y-2">
                      <h4 className="text-sm font-bold text-zinc-950 tracking-tight">{post.title}</h4>
                      <p className="text-xs text-zinc-600 leading-relaxed whitespace-pre-wrap line-clamp-4">
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
            ))
          )}
        </div>
        {meta && <Pagination meta={meta} page={page} onPageChange={(p) => void loadPage(p)} />}
      </div>

      {renderModal()}
    </div>
  );
}
