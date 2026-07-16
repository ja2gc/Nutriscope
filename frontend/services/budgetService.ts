import { apiFetch } from "@/lib/apiFetch";

export interface FiscalYearBudget {
  id: string;
  fiscal_year: number;
  allocated_amount: string;
  total_deductions: string;
  remaining: string;
  creator: { id: string; name: string } | null;
  created_at: string;
  updated_at: string;
}

export interface BudgetLedgerEntry {
  id: number;
  fiscal_year: number;
  type: "po_deduction" | "manual_addition" | "manual_deduction";
  source: "system" | "manual";
  amount: number;
  signed_amount: number;
  reason: string | null;
  reference: string | null;
  purchase_order_id: number | null;
  po_number: string | null;
  created_by: string | null;
  actor: { id: string | null; kind: "user" | "system"; name: string };
  created_at: string | null;
}

/** Ledger filter by reason source: system (PO deductions) or manual. */
export type LedgerFilter = "all" | "system" | "manual";

export interface FiscalYearSummary {
  fiscal_year: number;
  allocated_amount: string;
  total_deductions: string;
  remaining: string;
}

export interface FoodServiceSetting {
  per_head_day_limit: string | null;
  updated_by: string | null;
  updated_at: string | null;
}

export type BudgetApiPrefix = "fss" | "admin";

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

export async function listFiscalYears(prefix: BudgetApiPrefix = "fss"): Promise<FiscalYearBudget[]> {
  return unwrap(await apiFetch(`/api/${prefix}/budgets`), "Failed to load budgets.");
}

export async function getFiscalYearSummary(
  fiscalYear: number,
  prefix: BudgetApiPrefix = "fss",
): Promise<{ data: FiscalYearSummary | null; notice?: string }> {
  const res = await apiFetch(`/api/${prefix}/budgets/summary?fiscal_year=${fiscalYear}`);
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((json as { message?: string }).message ?? "Failed to load summary.");
  return json as { data: FiscalYearSummary | null; notice?: string };
}

export async function setupFiscalYear(payload: {
  fiscal_year: number;
  allocated_amount: number;
}, prefix: BudgetApiPrefix = "fss"): Promise<FiscalYearBudget> {
  return unwrap(await apiFetch(`/api/${prefix}/budgets`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to create fiscal year budget.");
}

export async function getLedger(
  fiscalYear: number,
  source: LedgerFilter = "all",
  prefix: BudgetApiPrefix = "fss",
): Promise<BudgetLedgerEntry[]> {
  const qs = new URLSearchParams({ fiscal_year: String(fiscalYear) });
  if (source && source !== "all") qs.set("source", source);
  return unwrap(await apiFetch(`/api/${prefix}/budgets/ledger?${qs}`), "Failed to load ledger.");
}

export async function addManualAdjustment(payload: {
  fiscal_year: number;
  type: "manual_addition" | "manual_deduction";
  amount: number;
  reason: string;
  reference?: string | null;
}, prefix: BudgetApiPrefix = "fss"): Promise<BudgetLedgerEntry> {
  return unwrap(await apiFetch(`/api/${prefix}/budgets/adjust`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to add adjustment.");
}

// ── Food Service settings: budget per head per day ──────────────────────────
export async function getFoodServiceSetting(prefix: "fss" | "admin" = "fss"): Promise<FoodServiceSetting> {
  return unwrap(await apiFetch(`/api/${prefix}/food-service-settings`), "Failed to load settings.");
}

export async function setFoodServiceSetting(
  perHeadDayLimit: number | null,
  prefix: "fss" | "admin" = "fss",
): Promise<FoodServiceSetting> {
  return unwrap(await apiFetch(`/api/${prefix}/food-service-settings`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ per_head_day_limit: perHeadDayLimit }),
  }), "Failed to save settings.");
}
