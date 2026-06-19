import { apiFetch } from "@/lib/apiFetch";

export interface Notification {
  id: number;
  title: string;
  message: string;
  type?: string | null;
  source_module?: string | null;
  source_id?: number | null;
  read: boolean;
  created_at: string;
  updated_at: string;
}

export async function fetchNotifications(): Promise<Notification[]> {
  const res = await apiFetch("/api/rnd/notifications", {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch notifications.");
  }

  const responseData = await res.json();
  return responseData.data || [];
}

export async function markNotificationRead(id: number | string): Promise<void> {
  const res = await apiFetch(`/api/rnd/notifications/${id}/read`, {
    method: "PATCH",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to mark notification as read.");
  }
}

export async function markAllNotificationsRead(): Promise<void> {
  const res = await apiFetch("/api/rnd/notifications/read-all", {
    method: "PATCH",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to mark all notifications as read.");
  }
}
