import { apiFetch } from "@/lib/apiFetch";
import type { PaginationMeta } from "@/components/ui/Pagination";

export type { PaginationMeta };
export type AnnouncementCategory = "General" | "Event" | "Operational" | "Urgent" | "Memo";
export type AnnouncementVisibility = "FSS" | "Admin" | "All";

export interface Announcement {
  id: number;
  title: string;
  body: string;
  category: AnnouncementCategory;
  attachment?: string | null;
  attachments?: string[];
  pinned: boolean;
  visibility: AnnouncementVisibility;
  created_at: string;
  updated_at: string;
  author: {
    id: number;
    name: string;
    role: "RND" | "FSS" | "Admin";
  };
}

export interface AnnouncementPayload {
  title: string;
  body: string;
  category: AnnouncementCategory;
  visibility: AnnouncementVisibility;
  attachment?: string | null;
  attachments?: string[];
  pinned?: boolean;
}

export interface AnnouncementPage {
  data: Announcement[];
  meta: PaginationMeta;
}

const FALLBACK_META: PaginationMeta = { current_page: 1, per_page: 10, total: 0, last_page: 1 };

export async function fetchAnnouncements(
  page: number = 1,
  perPage: number = 10,
  announcementId?: string,
): Promise<AnnouncementPage> {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (announcementId) params.set("announcement_id", announcementId);
  const res = await apiFetch(`/api/announcements?${params}`, {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch announcements.");
  }

  const responseData = await res.json();
  return {
    data: responseData.data || [],
    meta: responseData.meta || FALLBACK_META,
  };
}

export async function createAnnouncement(data: AnnouncementPayload): Promise<Announcement> {
  const res = await apiFetch("/api/announcements", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to create announcement.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function updateAnnouncement(
  id: number | string,
  data: Partial<AnnouncementPayload>
): Promise<Announcement> {
  const res = await apiFetch(`/api/announcements/${id}`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(data),
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to update announcement.");
  }

  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function deleteAnnouncement(id: number | string): Promise<void> {
  const res = await apiFetch(`/api/announcements/${id}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to delete announcement.");
  }
}

// ---------------------------------------------------------------------------
// Admin variants — route through /api/admin/announcements* so that the
// Admin\AnnouncementController is hit and `pinned` is honoured.
// ---------------------------------------------------------------------------

export async function fetchAdminAnnouncements(
  page: number = 1,
  perPage: number = 10,
  announcementId?: string,
): Promise<AnnouncementPage> {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  if (announcementId) params.set("announcement_id", announcementId);
  const res = await apiFetch(`/api/admin/announcements?${params}`, {
    method: "GET",
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch announcements.");
  }
  const responseData = await res.json();
  return {
    data: responseData.data || [],
    meta: responseData.meta || FALLBACK_META,
  };
}

export async function createAdminAnnouncement(data: AnnouncementPayload): Promise<Announcement> {
  const res = await apiFetch("/api/admin/announcements", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to create announcement.");
  }
  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function updateAdminAnnouncement(
  id: number | string,
  data: Partial<AnnouncementPayload>
): Promise<Announcement> {
  const res = await apiFetch(`/api/admin/announcements/${id}`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(data),
  });
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to update announcement.");
  }
  const responseData = await res.json();
  return responseData.data || responseData;
}

export async function deleteAdminAnnouncement(id: number | string): Promise<void> {
  const res = await apiFetch(`/api/admin/announcements/${id}`, {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to delete announcement.");
  }
}
