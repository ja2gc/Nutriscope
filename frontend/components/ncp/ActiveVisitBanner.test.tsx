// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { fetchActiveAppointment } from "@/services/ncpAppointmentService";
import { ActiveVisitBanner } from "./ActiveVisitBanner";

vi.mock("@/services/ncpAppointmentService", () => ({ fetchActiveAppointment: vi.fn() }));
vi.mock("next/link", () => ({ default: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a> }));
const fetchActiveMock = vi.mocked(fetchActiveAppointment);

describe("ActiveVisitBanner", () => {
  let root: Root;
  let container: HTMLDivElement;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    fetchActiveMock.mockReset();
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it("refreshes when visit state changes and links back to exact patient cycle", async () => {
    fetchActiveMock.mockResolvedValueOnce(null).mockResolvedValueOnce({
      id: "visit-1", patient_id: "patient-1", ncp_record_id: "cycle-1", source: "walk_in", status: "in_progress",
      purpose: "Complete diagnosis", scheduled_at: null, started_at: "2026-09-07T10:00:00Z", finished_at: null,
      reason_code: null, worked_on: ["assessment"], newly_completed: [], patient: { id: "patient-1", display_name: "Maria Santos" },
    });
    await act(async () => root.render(<ActiveVisitBanner />));
    expect(container.textContent).toBe("");

    await act(async () => window.dispatchEvent(new Event("ncp-visit-changed")));

    expect(container.textContent).toContain("Maria Santos");
    expect(container.querySelector("a")?.getAttribute("href")).toBe("/ncp/patient-1/assessment/cycle-1");
  });
});
