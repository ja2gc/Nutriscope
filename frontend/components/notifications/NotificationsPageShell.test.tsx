// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { dismissNotification, fetchNotifications, fetchUnreadCount } from "@/services/notificationService";
import { NotificationsPageShell } from "./NotificationsPageShell";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push }) }));
vi.mock("@/services/notificationService", async (original) => ({
  ...await original<typeof import("@/services/notificationService")>(),
  fetchNotifications: vi.fn(), fetchUnreadCount: vi.fn(), dismissNotification: vi.fn(),
  markNotificationOpened: vi.fn(), markAllNotificationsRead: vi.fn(),
}));
const fetchMock = vi.mocked(fetchNotifications);
const unreadMock = vi.mocked(fetchUnreadCount);
const dismissMock = vi.mocked(dismissNotification);

describe("NotificationsPageShell", () => {
  let root: Root; let container: HTMLDivElement;
  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    push.mockReset(); dismissMock.mockReset(); unreadMock.mockResolvedValue(2);
    fetchMock.mockResolvedValue({ data: [
      { id: "info-1", title: "Announcement", message: "Policy update", type: "announcement", source_module: "announcements", source_uuid: "announcement-1", read: false, dismissible: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
      { id: "action-1", title: "Appointment due: Maria", message: "Maria — today", type: "appointment_due", source_module: "ncp_appointment", source_uuid: "visit-1", source_parent_uuid: "patient-1", read: false, dismissible: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
    ], meta: { current_page: 1, per_page: 10, total: 2, last_page: 1 } });
    container = document.createElement("div"); document.body.append(container); root = createRoot(container);
  });
  afterEach(() => { act(() => root.unmount()); container.remove(); });

  it("dismisses informational notifications but labels unresolved work as action required", async () => {
    const user = userEvent.setup();
    await act(async () => root.render(<NotificationsPageShell role="RND" />));
    expect(container.textContent).toContain("Action required");
    const dismiss = Array.from(container.querySelectorAll("button")).find((button) => button.textContent?.includes("Dismiss"));
    expect(dismiss).toBeDefined();
    dismissMock.mockResolvedValue();
    await act(async () => user.click(dismiss!));
    expect(dismissMock).toHaveBeenCalledWith("info-1");
    expect(container.textContent).not.toContain("Policy update");
  });
});
