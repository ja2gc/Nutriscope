import { beforeEach, describe, expect, test, vi } from "vitest";
import { apiFetch } from "@/lib/apiFetch";
import { getActivity } from "./activityService";
import type { AuditEventDto } from "@/types/audit";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

const apiFetchMock = vi.mocked(apiFetch);

const structuredEvent: AuditEventDto = {
  id: "event-public-id",
  category: "operations",
  domain: "reports",
  action: "archived",
  action_label: "Archived",
  summary: "Archived report",
  severity: "notice",
  outcome: "success",
  actor: { id: "user-public-id", kind: "user", name: "Maria Santos", role: "RND" },
  subject: { type: "report", id: "report-public-id", label: "Nutrition report" },
  context: null,
  occurred_at: "2026-07-12T08:30:00Z",
  details: [],
  changes: [],
};

describe("activityService", () => {
  beforeEach(() => apiFetchMock.mockReset());

  test("returns the shared structured audit DTO and preserves cursor pagination", async () => {
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({
      data: [structuredEvent],
      meta: { next_before_id: "event-cursor", has_more: true },
    }), { status: 200, headers: { "Content-Type": "application/json" } }));

    const result = await getActivity("/api/rnd/reports/report-public-id/activity", "newer-cursor");

    expect(apiFetchMock).toHaveBeenCalledWith(
      "/api/rnd/reports/report-public-id/activity?before_id=newer-cursor",
      expect.objectContaining({ headers: { Accept: "application/json" } }),
    );
    expect(result).toEqual({
      data: [structuredEvent],
      meta: { next_before_id: "event-cursor", has_more: true },
    });
  });

  test("rejects legacy trail shapes instead of fabricating structured fields", async () => {
    apiFetchMock.mockResolvedValue(new Response(JSON.stringify({
      data: [{
        id: 41,
        event: "updated",
        description: "Updated patient",
        subject_id: 9,
        causer: "Ana Reyes",
        changes: { old: { medical_diagnosis: null }, new: { medical_diagnosis: null } },
        created_at: "2026-07-12T08:30:00Z",
      }],
      meta: { next_before_id: null, has_more: false },
    }), { status: 200, headers: { "Content-Type": "application/json" } }));

    await expect(getActivity("/api/rnd/patients/patient-public-id/activity"))
      .rejects.toThrow("Invalid activity trail response.");
  });
});
