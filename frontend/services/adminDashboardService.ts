import { apiFetch } from "@/lib/apiFetch";

export interface DashboardUserStats {
  total: number;
  by_role: {
    Admin: number;
    RND: number;
    FSS: number;
  };
}

export interface DashboardAiUsage {
  total_calls: number;
  total_tokens: number;
  tokens_input: number;
  tokens_output: number;
  by_endpoint: Record<string, { calls: number; tokens: number }>;
}

export interface DashboardAuditLogs {
  total: number;
  last_7_days: number;
}

export interface DashboardData {
  users: DashboardUserStats;
  patients: { total: number };
  ai_usage: DashboardAiUsage;
  audit_logs: DashboardAuditLogs;
  reports: { total: number };
}

export async function fetchDashboard(): Promise<DashboardData> {
  const res = await apiFetch("/api/admin/dashboard", {
    method: "GET",
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const errorData = await res.json().catch(() => ({}));
    throw new Error(errorData.message || "Failed to fetch dashboard.");
  }

  const responseData = await res.json();
  return responseData.data;
}
