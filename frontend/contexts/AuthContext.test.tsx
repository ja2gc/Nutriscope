/** @vitest-environment jsdom */
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import { AuthProvider, useAuth } from "./AuthContext";
import { TopBar } from "@/components/layout/TopBar";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
vi.mock("next/navigation", () => ({ usePathname: () => "/profile", useRouter: () => ({ push: vi.fn() }) }));
vi.mock("@/services/notificationService", () => ({ fetchUnreadCount: async () => 0 }));
vi.mock("@/services/authService", () => ({ fetchCurrentUser: async () => ({
  id: 1, name: "Review User", display_name: "Review User", role: "Admin", profile_photo: "/api/auth/profile-photo",
}) }));

function RefreshControl() {
  const { refreshUser } = useAuth();
  return <button onClick={() => void refreshUser()}>Refresh account</button>;
}

test("refreshing the saved account reloads a header photo even when its protected URL stays the same", async () => {
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<AuthProvider><TopBar /><RefreshControl /></AuthProvider>));
    const previous = container.querySelector('img')?.getAttribute('src');
    expect(previous).toContain('/api/auth/profile-photo');
    const refresh = [...container.querySelectorAll('button')].find(button => button.textContent === 'Refresh account')!;
    await act(async () => refresh.click());
    const next = container.querySelector('img')?.getAttribute('src');
    expect(next).toContain('/api/auth/profile-photo');
    expect(next).not.toBe(previous);
  } finally {
    await act(async () => root.unmount());
    container.remove();
  }
});
