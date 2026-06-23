import { apiFetch } from "@/lib/apiFetch";

export type BudgetScope = "monthly" | "quarterly" | "yearly" | "custom";

export interface Budget {
  id: number;
  scope: BudgetScope;
  name: string | null;
  allocated_amount: string | null;
  actual_amount: string | null;
  period_start: string | null;
  period_end: string | null;
  cost_per_person: string | null;
  population: number | null;
  budget_per_head_day: string | null;
  budget_per_head_month: string | null;
  budget_per_head_year: string | null;
}

export interface BudgetPayload {
  scope?: BudgetScope;
  name?: string | null;
  allocated_amount?: number;
  period_start?: string | null;
  period_end?: string | null;
  population?: number | null;
  budget_per_head_day?: number | null;
  budget_per_head_month?: number | null;
  budget_per_head_year?: number | null;
}

export interface TrendPoint { bucket: string; planned: number; actual: number; variance: number }
export interface BudgetSummary {
  planned: number;
  actual: number;
  variance: number;
  variance_pct: number;
  trend: TrendPoint[];
  range: { start: string; end: string; granularity: string };
  source: "consumption" | "purchases";
  cash_flow: number;
  days_served: number;
  allocated: number;
  budget_per_head_day: number | null;
  population: number | null;
  avg_population: number | null;
  per_head_actual: number | null;
  procurement_per_head: {
    actual: number | null;
    pending: boolean;
    pending_reason: string | null;
    procurement_cost: number;
    served_population: number;
  };
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

export async function listBudgets(): Promise<Budget[]> {
  return unwrap(await apiFetch("/api/fss/budgets"), "Failed to load budgets.");
}
export async function saveBudget(id: number | null, payload: BudgetPayload): Promise<Budget> {
  return unwrap(await apiFetch(id ? `/api/fss/budgets/${id}` : "/api/fss/budgets", {
    method: id ? "PATCH" : "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  }), "Failed to save budget.");
}
export async function deleteBudget(id: number): Promise<void> {
  const res = await apiFetch(`/api/fss/budgets/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete budget.");
}
export async function getBudgetSummary(id: number, opts: { start?: string; end?: string; granularity?: string }): Promise<BudgetSummary> {
  const qs = new URLSearchParams();
  if (opts.start) qs.set("start", opts.start);
  if (opts.end) qs.set("end", opts.end);
  if (opts.granularity) qs.set("granularity", opts.granularity);
  return unwrap(await apiFetch(`/api/fss/budgets/${id}/summary?${qs}`), "Failed to load summary.");
}
export async function addDailyLog(id: number, payload: { log_date: string; spent: number; notes?: string }): Promise<void> {
  const res = await apiFetch(`/api/fss/budgets/${id}/daily-logs`, {
    method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error("Failed to add log.");
}
