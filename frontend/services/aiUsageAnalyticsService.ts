import { apiFetch } from "@/lib/apiFetch";

export type AiUsagePoint = {
  day?: number;
  month?: number;
  tokens: number | null;
};

export type AiUsageAnalytics = {
  view: "month" | "year";
  year: number;
  month?: number;
  timezone?: string;
  total_tokens: number;
  points: AiUsagePoint[];
};

export async function fetchAiUsageAnalytics(params: {
  view: "month" | "year";
  year: number;
  month?: number;
}): Promise<AiUsageAnalytics> {
  const query = new URLSearchParams({
    view: params.view,
    year: String(params.year),
  });

  if (params.month) query.set("month", String(params.month));

  const response = await apiFetch(`/api/admin/ai-usage?${query}`);
  if (!response.ok) throw new Error("Failed to fetch AI usage analytics.");

  return response.json();
}
