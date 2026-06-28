import { apiFetch } from "@/lib/apiFetch";

export interface AiUsageLimits {
  daily_token_limit: number | null;
  monthly_token_limit: number | null;
  cost_per_1m_tokens_usd: number;
  daily_used: number;
  monthly_used: number;
}

export interface SaveAiUsageLimitsPayload {
  daily_token_limit?: number | null;
  monthly_token_limit?: number | null;
  cost_per_1m_tokens_usd?: number;
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

export async function getAiUsageLimits(): Promise<AiUsageLimits> {
  return unwrap(
    await apiFetch("/api/admin/ai-usage-limits"),
    "Failed to load AI usage limits.",
  );
}

export async function saveAiUsageLimits(payload: SaveAiUsageLimitsPayload): Promise<AiUsageLimits> {
  return unwrap(
    await apiFetch("/api/admin/ai-usage-limits", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    }),
    "Failed to save AI usage limits.",
  );
}
