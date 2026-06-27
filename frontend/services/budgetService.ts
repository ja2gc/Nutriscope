import { apiFetch } from "@/lib/apiFetch";

export interface FiscalYearBudget {
  id: number;
  fiscal_year: number;
  allocated_amount: string;
  per_head_day_limit: string | null;
  total_po_deductions: string;
  total_manual_additions: string;
  total_manual_deductions: string;
  remaining_balance: string;
}

export interface BudgetLedgerEntry {
  id: number;
  fiscal_year: number;
  type: "po_deduction" | "manual_addition" | "manual_deduction";
  amount: number;
  signed_amount: number;
  reason: string | null;
  reference: string | null;
  purchase_order_id: number | null;
  po_number: string | null;
  procurement_span: string | null;
  created_by: string | null;
  created_at: string | null;
}

export type LedgerFilter = "all" | "manual" | "po" | "manual_addition" | "manual_deduction" | "po_deduction";

export interface FiscalYearSummary {
  fiscal_year: number;
  allocated_amount: string;
  per_head_day_limit: string | null;
  total_po_deductions: string;
  total_manual_additions: string;
  total_manual_deductions: string;
  remaining_balance: string;
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

export async function listFiscalYears(): Promise<FiscalYearBudget[]> {
  return unwrap(await apiFetch("/api/fss/budgets"), "Failed to load budgets.");
}

export async function getFiscalYearSummary(fiscalYear: number): Promise<{ data: FiscalYearSummary | null; notice?: string }> {
  const res = await apiFetch(`/api/fss/budgets/summary?fiscal_year=${fiscalYear}`);
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((json as { message?: string }).message ?? "Failed to load summary.");
  return json as { data: FiscalYearSummary | null; notice?: string };
}

export async function setupFiscalYear(payload: {
  fiscal_year: number;
  allocated_amount: number;
  per_head_day_limit?: number | null;
}): Promise<FiscalYearBudget> {
  return unwrap(await apiFetch("/api/fss/budgets", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to create fiscal year budget.");
}

export async function getLedger(fiscalYear: number, type: LedgerFilter = "all"): Promise<BudgetLedgerEntry[]> {
  const qs = new URLSearchParams({ fiscal_year: String(fiscalYear) });
  if (type && type !== "all") qs.set("type", type);
  return unwrap(await apiFetch(`/api/fss/budgets/ledger?${qs}`), "Failed to load ledger.");
}

export async function addManualAdjustment(payload: {
  fiscal_year: number;
  type: "manual_addition" | "manual_deduction";
  amount: number;
  reason: string;
  reference?: string | null;
}): Promise<BudgetLedgerEntry> {
  return unwrap(await apiFetch("/api/fss/budgets/adjust", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to add adjustment.");
}
