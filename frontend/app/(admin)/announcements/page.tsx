"use client";

import React, { useEffect, useState, useMemo } from "react";
import { useAuth } from "@/contexts/AuthContext";
import {
  fetchAnnouncements,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement,
  Announcement,
  AnnouncementPayload,
  AnnouncementCategory,
  AnnouncementVisibility,
} from "@/services/announcementService";
import {
  Megaphone,
  Plus,
  Pin,
  Pencil,
  Trash2,
  X,
  RefreshCw,
  Eye,
  Calendar,
  AlertTriangle,
  Upload,
  Image as ImageIcon,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Badge, BadgeTone } from "@/components/ui/Badge";

const categoryStyles: Record<AnnouncementCategory, BadgeTone> = {
  General: "zinc",
  Event: "amber",
  Operational: "sky",
  Urgent: "red",
};

const visibilityTones: Record<AnnouncementVisibility, BadgeTone> = {
  All: "emerald",
  FSS: "sky",
  Admin: "violet",
};

export default function AnnouncementsPage() {
  const { user: currentUser } = useAuth();
  const [posts, setPosts] = useState<Announcement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form Modal States
  const [formOpen, setFormOpen] = useState(false);
  const [editingPost, setEditingPost] = useState<Announcement | null>(null);
  
  // Form values
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [category, setCategory] = useState<AnnouncementCategory>("General");
  const [visibility, setVisibility] = useState<AnnouncementVisibility>("All");
  const [pinned, setPinned] = useState(false);
  const [attachment, setAttachment] = useState<string | null>(null);
  
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  async function loadAnnouncements() {
    try {
      setLoading(true);
      setError(null);
      const data = await fetchAnnouncements();
      setPosts(data);
    } catch (err: any) {
      setError(err.message || "Failed to load announcements feed.");
    } finally {
      setLoading(false);
    }
  }

  // Use useEffect to handle loading
  useEffect(() => {
    // Avoid named function for select
    const run = async () => {
      try {
        setLoading(true);
        setError(null);
        const data = await fetchAnnouncements();
        setPosts(data);
      } catch (err: any) {
        setError(err.message || "Failed to load announcements feed.");
      } finally {
        setLoading(false);
      }
    };
    void run();
  }, []);

  const orderedPosts = useMemo(() => {
    return [...posts].sort((a, b) => {
      if (a.pinned !== b.pinned) {
        return a.pinned ? -1 : 1;
      }
      return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
    });
  }, [posts]);

  function openCreate() {
    setEditingPost(null);
    setTitle("");
    setBody("");
    setCategory("General");
    setVisibility("All");
    setPinned(false);
    setAttachment(null);
    setFormError(null);
    setFormOpen(true);
  }

  function openEdit(post: Announcement) {
    setEditingPost(post);
    setTitle(post.title);
    setBody(post.body);
    setCategory(post.category);
    setVisibility(post.visibility);
    setPinned(post.pinned);
    setAttachment(post.attachment || null);
    setFormError(null);
    setFormOpen(true);
  }

  function handleImageUpload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) {
      setAttachment(null);
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      setAttachment(typeof reader.result === "string" ? reader.result : null);
    };
    reader.readAsDataURL(file);
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setSubmitting(true);

    const payload: AnnouncementPayload = {
      title: title.trim(),
      body: body.trim(),
      category,
      visibility,
      pinned,
      attachment: attachment || null,
    };

    try {
      if (editingPost) {
        const updated = await updateAnnouncement(editingPost.id, payload);
        setPosts((prev) => prev.map((p) => (p.id === editingPost.id ? updated : p)));
      } else {
        const created = await createAnnouncement(payload);
        setPosts((prev) => [created, ...prev]);
      }
      setFormOpen(false);
    } catch (err: any) {
      setFormError(err.message || "Failed to broadcast announcement.");
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(post: Announcement) {
    if (!confirm(`Are you sure you want to delete announcement: "${post.title}"?`)) {
      return;
    }
    try {
      await deleteAnnouncement(post.id);
      setPosts((prev) => prev.filter((p) => p.id !== post.id));
    } catch (err: any) {
      alert(err.message || "Failed to delete announcement.");
    }
  }

  return (
    <div className="space-y-6 font-sans text-zinc-100">
      {/* Breadcrumbs & Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 select-none">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold text-zinc-500">
            <span>Admin</span>
            <span className="text-zinc-700">/</span>
            <span className="text-zinc-400 font-bold">Announcements</span>
          </div>
          <h1 className="text-xl font-extrabold text-white tracking-tight mt-1 flex items-center gap-2">
            <Megaphone className="h-5 w-5 text-blue-400" />
            Hospital Feed Manager
          </h1>
          <p className="text-xs text-zinc-400 mt-0.5">
            Broadcast administrative notices, operational protocols, and event schedules to staff dashboards.
          </p>
        </div>

        <button
          onClick={openCreate}
          className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer select-none"
        >
          <Plus className="h-4 w-4" />
          Publish Bulletin
        </button>
      </div>

      {/* Main Bulletins Grid */}
      {error ? (
        <div className="bg-red-950/30 border border-red-900/50 p-4 rounded-xl flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
          <div>
            <div className="text-xs text-red-400 font-bold">Failed to load bulletins</div>
            <div className="text-xs text-red-500/80 mt-0.5">{error}</div>
          </div>
        </div>
      ) : loading ? (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-16 text-center flex flex-col items-center justify-center gap-3">
          <RefreshCw className="h-6 w-6 text-zinc-500 animate-spin" />
          <div className="text-xs text-zinc-400 font-semibold uppercase tracking-wider">
            Loading announcements...
          </div>
        </div>
      ) : orderedPosts.length === 0 ? (
        <div className="bg-zinc-900 border border-zinc-800 rounded-3xl p-16 text-center max-w-xl mx-auto shadow-sm">
          <div className="p-3 bg-zinc-950 border border-zinc-850 rounded-2xl w-fit mx-auto text-zinc-650 mb-4">
            <Megaphone className="h-8 w-8" />
          </div>
          <h3 className="text-sm font-bold text-zinc-300">Announcements feed is empty</h3>
          <p className="text-xs text-zinc-500 mt-1 max-w-sm mx-auto leading-relaxed">
            Create a new bulletin to share updates with the team.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          {orderedPosts.map((post) => {
            const initials = post.author?.name
              ? post.author.name
                  .split(" ")
                  .filter(Boolean)
                  .slice(0, 2)
                  .map((n) => n[0]?.toUpperCase())
                  .join("")
              : "SYS";
            const dateStr = new Date(post.created_at).toLocaleString("en-US", {
              month: "short",
              day: "numeric",
              hour: "numeric",
              minute: "2-digit",
            });

            return (
              <article
                key={post.id}
                className={`bg-zinc-900 border ${
                  post.pinned ? "border-amber-500/30" : "border-zinc-850"
                } rounded-3xl p-5 shadow-md flex flex-col justify-between hover:border-zinc-700 transition-all gap-4 relative overflow-hidden`}
              >
                {post.pinned && (
                  <div className="absolute top-0 right-0 h-16 w-16 overflow-hidden select-none pointer-events-none">
                    <div className="bg-amber-500/10 border border-amber-500/20 text-amber-500 text-[8px] font-black uppercase tracking-widest text-center py-1 absolute transform rotate-45 top-3 -right-4 w-20">
                      Pinned
                    </div>
                  </div>
                )}

                <div className="space-y-3.5">
                  {/* Meta Bar */}
                  <div className="flex items-start gap-3">
                    <div className="h-9 w-9 rounded-full bg-zinc-950 border border-zinc-800 text-zinc-300 font-bold text-xs flex items-center justify-center shrink-0">
                      {initials}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="text-xs font-bold text-white leading-tight truncate">
                        {post.author?.name || "System"}
                      </div>
                      <div className="text-[10px] text-zinc-500 mt-0.5 flex flex-wrap items-center gap-1.5 font-medium">
                        <span>{post.author?.role || "Staff"}</span>
                        <span>·</span>
                        <span>{dateStr}</span>
                      </div>
                    </div>
                  </div>

                  {/* Header badges */}
                  <div className="flex flex-wrap gap-2 select-none">
                    <Badge tone={categoryStyles[post.category] || "zinc"}>{post.category}</Badge>
                    <Badge tone={visibilityTones[post.visibility] || "zinc"}>
                      Target: {post.visibility}
                    </Badge>
                  </div>

                  {/* Content */}
                  <div className="space-y-2">
                    <h3 className="text-sm font-extrabold text-white tracking-tight leading-snug">
                      {post.title}
                    </h3>
                    <p className="text-xs text-zinc-350 leading-relaxed whitespace-pre-wrap line-clamp-6">
                      {post.body}
                    </p>
                  </div>

                  {/* Attachment image */}
                  {post.attachment && (
                    <div className="rounded-xl border border-zinc-850 overflow-hidden bg-zinc-950/60 max-h-48 flex items-center justify-center">
                      <img
                        src={post.attachment}
                        alt={post.title}
                        className="block w-full object-cover max-h-48"
                      />
                    </div>
                  )}
                </div>

                {/* Card controls */}
                <div className="pt-3 border-t border-zinc-850/60 flex items-center justify-between gap-3 select-none">
                  <span className="text-[9px] text-zinc-500 font-bold uppercase tracking-wider">
                    Bulletin ID: #{post.id}
                  </span>
                  
                  <div className="flex gap-1.5">
                    <button
                      onClick={() => openEdit(post)}
                      title="Edit announcement"
                      className="p-1.5 rounded-lg border border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-white hover:border-zinc-700 transition-all cursor-pointer"
                    >
                      <Pencil className="h-3.5 w-3.5" />
                    </button>
                    <button
                      onClick={() => void handleDelete(post)}
                      title="Delete announcement"
                      className="p-1.5 rounded-lg border border-zinc-800 bg-zinc-950 text-zinc-500 hover:text-red-500 hover:border-red-500/20 transition-all cursor-pointer"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      )}

      {/* Editor Modal Form */}
      {formOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
          onClick={() => setFormOpen(false)}
        >
          <div
            className="w-full max-w-2xl bg-zinc-900 border border-zinc-850 rounded-3xl overflow-hidden shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="px-5 py-4 border-b border-zinc-850 bg-zinc-950 flex items-center justify-between gap-4 select-none">
              <div>
                <h3 className="text-xs font-bold text-white uppercase tracking-[0.18em]">
                  {editingPost ? "Edit Bulletin Post" : "Compose Bulletin Post"}
                </h3>
                <p className="text-[10px] text-zinc-500 mt-1">
                  Draft notices dynamically broadcast to patient care dashboards.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setFormOpen(false)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-800 text-[10px] font-bold uppercase tracking-wider text-zinc-455 hover:text-white hover:bg-zinc-850 transition-all cursor-pointer"
              >
                <X className="h-3.5 w-3.5" />
                Close
              </button>
            </div>

            <form onSubmit={handleSave} className="p-5 space-y-4">
              {formError && (
                <div className="text-xs font-semibold text-red-400 bg-red-950/20 border border-red-900/50 rounded-xl px-3 py-2 flex items-start gap-2">
                  <AlertTriangle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                  <span>{formError}</span>
                </div>
              )}

              {/* Title */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                  Post Title
                </label>
                <input
                  type="text"
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="e.g. Critical System Upgrade Schedule"
                  className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-650"
                />
              </div>

              {/* Category, Target, Pinned grid */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Category Theme
                  </label>
                  <select
                    value={category}
                    onChange={(e) => setCategory(e.target.value as AnnouncementCategory)}
                    className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
                  >
                    <option value="General">General Notice</option>
                    <option value="Event">Event Schedule</option>
                    <option value="Operational">Operational Protocol</option>
                    <option value="Urgent">Urgent / Alert</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                    Department Visibility
                  </label>
                  <select
                    value={visibility}
                    onChange={(e) => setVisibility(e.target.value as AnnouncementVisibility)}
                    className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 cursor-pointer"
                  >
                    <option value="All">All Departments (Public)</option>
                    <option value="FSS">Food Service only</option>
                    <option value="Admin">Administrators only</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block select-none">
                    Pin Priority
                  </label>
                  <div className="flex h-[34px] items-center">
                    <label className="flex items-center gap-2 cursor-pointer text-xs text-zinc-350 select-none">
                      <input
                        type="checkbox"
                        checked={pinned}
                        onChange={(e) => setPinned(e.target.checked)}
                        className="h-4 w-4 bg-zinc-950 border border-zinc-850 rounded-md focus:ring-purple-500/20 text-purple-600 focus:outline-none"
                      />
                      <span>Pin post to top of feed</span>
                    </label>
                  </div>
                </div>
              </div>

              {/* Body Text */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                  Body Bulletin text
                </label>
                <textarea
                  required
                  value={body}
                  onChange={(e) => setBody(e.target.value)}
                  placeholder="Detail the announcement bulletin here..."
                  className="w-full px-3 py-2 text-xs bg-zinc-950 border border-zinc-850 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 placeholder:text-zinc-650 min-h-28 leading-5"
                />
              </div>

              {/* Image upload */}
              <div className="space-y-2">
                <label className="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">
                  Attachment Banner Image
                </label>
                <div className="flex items-center gap-3">
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleImageUpload}
                    className="hidden"
                    id="file-upload-input"
                  />
                  <label
                    htmlFor="file-upload-input"
                    className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-zinc-850 bg-zinc-950 text-zinc-400 hover:text-white hover:border-zinc-750 transition-colors select-none cursor-pointer text-xs font-semibold"
                  >
                    <Upload className="h-4 w-4" />
                    Upload Image
                  </label>
                  {attachment && (
                    <button
                      type="button"
                      onClick={() => setAttachment(null)}
                      className="text-xs text-red-400 hover:text-red-300 font-bold select-none cursor-pointer"
                    >
                      Remove attachment
                    </button>
                  )}
                </div>

                {attachment ? (
                  <div className="rounded-2xl border border-zinc-850 overflow-hidden bg-zinc-950/40 max-h-48 flex items-center justify-center select-none">
                    <img
                      src={attachment}
                      alt="Attachment preview"
                      className="block w-full object-cover max-h-48"
                    />
                  </div>
                ) : (
                  <div className="rounded-2xl border border-dashed border-zinc-850 p-6 text-center text-xs text-zinc-555 bg-zinc-950/20 select-none">
                    No image uploaded. (Optional banner)
                  </div>
                )}
              </div>

              {/* Action footer */}
              <div className="flex gap-2.5 pt-2 select-none">
                <Button
                  variant="primary"
                  loading={submitting}
                  className="px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white"
                >
                  {editingPost ? "Publish Changes" : "Broadcast Announcement"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
