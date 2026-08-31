import { apiFetch } from "@/lib/apiFetch";

export type AiUsagePoint = {
  day?: number;
  month?: number;
  tokens: number | null;
  tokens_input: number | null;
  tokens_output: number | null;
};

export type AiUsageAnalytics = {
  view: "month" | "year";
  year: number;
  month?: number;
  timezone?: string;
  total_tokens: number;
  total_tokens_input: number;
  total_tokens_output: number;
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
