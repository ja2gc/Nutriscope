import { apiFetch } from "@/lib/apiFetch";
import { getAnnouncementNotifications, getFollowUpNotifications } from "@/lib/preferences";
import type { PaginationMeta } from "@/components/ui/Pagination";

export interface Notification {
  id: number;
  title: string;
  message: string;
  type?: string | null;
  source_module?: string | null;
  source_id?: number | null;
  // Public uuid of the source record — deep-links address the target by uuid.
  source_uuid?: string | null;
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

export function notificationTargetHref(
  notification: Pick<Notification, "type" | "source_module" | "source_id" | "source_uuid">,
  role?: string | null
): string {
  const type = (notification.type ?? "").toLowerCase();
  const sourceModule = (notification.source_module ?? "").toLowerCase();
  // Deep-links address the target by its public uuid; source_id is the raw internal FK.
  const sourceId = notification.source_uuid ?? notification.source_id;
  const isAdmin = (role ?? "").toLowerCase() === "admin";

  if ((type.includes("announcement") || sourceModule.includes("announcement")) && sourceId) {
    return isAdmin
      ? `/admin/announcements?announcementId=${sourceId}`
      : `/announcements?announcementId=${sourceId}`;
  }

  if ((type.includes("po") || type.includes("purchase") || sourceModule.includes("food_service")) && sourceId) {
    return `/food-service/procurement?poId=${sourceId}`;
  }

  if ((type.includes("follow") || sourceModule.includes("ncp")) && sourceId) {
    return `/ncp/patients?followUpNcpId=${sourceId}`;
  }

  return isAdmin ? "/admin/notifications" : "/notifications";
}

export async function fetchUnreadCount(): Promise<number> {
  const res = await apiFetch("/api/notifications/unread-count", {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) return 0;

  const data = await res.json().catch(() => ({ count: 0 }));
  return data.count ?? 0;
}

export async function fetchNotifications(page = 1, perPage = 10): Promise<{ data: Notification[]; meta: PaginationMeta }> {
  const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
  const res = await apiFetch(`/api/notifications?${params}`, {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch notifications.");
  }

  const responseData = await res.json();
  const notifications: Notification[] = responseData.data || [];

  return {
    data: notifications.filter((notification) => shouldShowNotification(notification, {
      announcements: getAnnouncementNotifications(),
      followUps: getFollowUpNotifications(),
    })),
    meta: responseData.meta ?? { current_page: page, per_page: perPage, total: 0, last_page: 1 },
  };
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
