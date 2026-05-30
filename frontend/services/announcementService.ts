export type AnnouncementCategory = "General" | "Event" | "Operational" | "Urgent";
export type AnnouncementVisibility = "FSS" | "Admin" | "All";

export interface Announcement {
  id: number;
  title: string;
  body: string;
  category: AnnouncementCategory;
  attachment?: string | null;
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
  pinned?: boolean;
}

export async function fetchAnnouncements(): Promise<Announcement[]> {
  const res = await fetch("/api/announcements", {
    method: "GET",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch announcements.");
  }

  const responseData = await res.json();
  return responseData.data || [];
}

export async function createAnnouncement(data: AnnouncementPayload): Promise<Announcement> {
  const res = await fetch("/api/announcements", {
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
  const res = await fetch(`/api/announcements/${id}`, {
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
  const res = await fetch(`/api/announcements/${id}`, {
    method: "DELETE",
    headers: {
      Accept: "application/json",
    },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to delete announcement.");
  }
}
