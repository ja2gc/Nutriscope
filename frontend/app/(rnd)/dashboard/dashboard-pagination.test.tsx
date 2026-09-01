/** @vitest-environment jsdom */

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";

import RndDashboardPage from "./page";
import { fetchAnnouncements } from "@/services/announcementService";
import { fetchPatients } from "@/services/patientService";

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: { id: 1 } }),
}));

vi.mock("@/services/patientService", () => ({
  fetchPatients: vi.fn(),
}));

vi.mock("@/services/announcementService", () => ({
  createAnnouncement: vi.fn(),
  updateAnnouncement: vi.fn(),
  fetchAnnouncements: vi.fn(),
}));

vi.mock("@/services/menuCycleService", () => ({
  getFssDashboard: vi.fn().mockResolvedValue(null),
}));

const announcement = (id: number) => ({
  id,
  title: `Announcement ${id}`,
  body: `Body ${id}`,
  category: "General" as const,
  visibility: "All" as const,
  pinned: false,
  attachment: null,
  attachments: [],
  created_at: `2026-07-${String(10 + id).padStart(2, "0")}T08:00:00Z`,
  updated_at: `2026-07-${String(10 + id).padStart(2, "0")}T08:00:00Z`,
  author: { id: 2, name: "RND User", role: "RND" as const },
});

describe("RND dashboard pagination", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);

    vi.mocked(fetchPatients).mockResolvedValue({
      data: [],
      meta: { current_page: 1, from: 0, last_page: 1, path: "", per_page: 3, to: 0, total: 0 },
    });
    vi.mocked(fetchAnnouncements).mockImplementation(async (page, perPage) => ({
      data: page === 1 ? [announcement(1), announcement(2)] : [announcement(3)],
      meta: { current_page: page ?? 1, last_page: 2, per_page: perPage ?? 2, total: 3 },
    }));
  });

  afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    vi.clearAllMocks();
  });

  test("shows two announcements per page and loads page two", async () => {
    await act(async () => root.render(<RndDashboardPage />));
    await act(async () => undefined);

    expect(fetchAnnouncements).toHaveBeenCalledWith(1, 2);
    expect(container.textContent).toContain("Page 1 of 2");

    const nextButtons = Array.from(container.querySelectorAll<HTMLButtonElement>('button[aria-label="Next page"]'));
    expect(nextButtons).toHaveLength(2);
    expect(nextButtons[0]?.disabled).toBe(true);
    const next = nextButtons.find((button) => !button.disabled);
    expect(next).toBeDefined();
    await act(async () => next?.click());
    await act(async () => undefined);

    expect(fetchAnnouncements).toHaveBeenCalledWith(2, 2);
    expect(container.textContent).toContain("Announcement 3");
  });
});
