import { apiFetch } from "@/lib/apiFetch";
import { getAnnouncementNotifications, getFollowUpNotifications } from "@/lib/preferences";

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

export type NotificationPrefs = {
  announcements: boolean;
  followUps: boolean;
};

export function shouldShowNotification(
  notification: Pick<Notification, "type" | "source_module">,
  prefs: NotificationPrefs
): boolean {
  const type = `${notification.type ?? ""} ${notification.source_module ?? ""}`.toLowerCase();

  if (!prefs.announcements && type.includes("announcement")) {
    return false;
  }

  if (!prefs.followUps && (type.includes("follow") || type.includes("reminder"))) {
    return false;
  }

  return true;
}

export async function fetchNotifications(): Promise<Notification[]> {
  const res = await apiFetch("/api/notifications", {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch notifications.");
  }

  const responseData = await res.json();
  const notifications: Notification[] = responseData.data || [];

  return notifications.filter((notification) => shouldShowNotification(notification, {
    announcements: getAnnouncementNotifications(),
    followUps: getFollowUpNotifications(),
  }));
}

export async function markNotificationRead(id: number | string): Promise<void> {
  const res = await apiFetch(`/api/notifications/${id}/read`, {
    method: "PATCH",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to mark notification as read.");
  }
}

export async function markAllNotificationsRead(): Promise<void> {
  const res = await apiFetch("/api/notifications/read-all", {
    method: "PATCH",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to mark all notifications as read.");
  }
}
