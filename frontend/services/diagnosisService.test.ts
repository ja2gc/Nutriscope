import { beforeEach, describe, expect, test, vi } from "vitest";

import { apiFetch } from "@/lib/apiFetch";
import { aiSuggestDiagnoses, dismissPesSuggestion, fetchDiagnosesPage } from "./diagnosisService";

vi.mock("@/lib/apiFetch", () => ({ apiFetch: vi.fn() }));

describe("fetchDiagnosesPage", () => {
  beforeEach(() => {
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({
      data: [{ id: 11, domain: "NI" }],
      meta: { current_page: 2, per_page: 10, total: 11, last_page: 2 },
    }), { status: 200 }));
  });

  test("requests a ten-item page and preserves pagination metadata", async () => {
    const result = await fetchDiagnosesPage(77, 2);

    expect(apiFetch).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/77/diagnoses?page=2&per_page=10",
      expect.any(Object)
    );
    expect(result.meta).toMatchObject({ current_page: 2, per_page: 10, total: 11, last_page: 2 });
    expect(result.data).toHaveLength(1);
  });
});

describe("assessment-based PES drafts", () => {
  test("requests server-built evidence without sending patient clinical data", async () => {
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({
      data: [{
        candidate_id: "unintended_weight_loss_v1",
        domain: "NC",
        label: "Unintended Weight Loss",
        etiology: "decreased appetite",
        signs: "6.5% unintended weight loss over 2 months",
        evidence_used: ["Weight loss: 6.5%", "Period: 2 months"],
        source: { id: "academy_ncp_diagnosis_2026", issuer: "Academy", title: "Nutrition Diagnosis", version: "2026", location: "Critical Thinking", url: "https://example.test" },
      }],
      meta: { cached: false, fingerprint: "abc", catalog_version: "2026-09-21-v1", message: null },
    }), { status: 200 }));

    const result = await aiSuggestDiagnoses(77);

    expect(apiFetch).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/77/diagnoses/ai-suggest",
      expect.objectContaining({ method: "POST", body: undefined }),
    );
    expect(result.data).toHaveLength(1);
    expect(result.meta.cached).toBe(false);
  });

  test("persists dismissal through the bounded suggestion endpoint", async () => {
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({
      data: [],
      meta: { cached: true, fingerprint: "abc", catalog_version: "2026-09-21-v1", message: "No sufficiently supported PES draft was found." },
    }), { status: 200 }));

    await dismissPesSuggestion(77, "unintended_weight_loss_v1");

    expect(apiFetch).toHaveBeenCalledWith(
      "/api/rnd/ncp-records/77/diagnoses/ai-suggest",
      expect.objectContaining({
        method: "POST",
        body: JSON.stringify({ dismissed_candidate_id: "unintended_weight_loss_v1" }),
      }),
    );
  });
});
