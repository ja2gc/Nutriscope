import { apiFetch } from "@/lib/apiFetch";

// Budget burn
export interface BudgetBurnPoint {
  month: string;
  cumulative_spent: number;
  allocated: number;
  remaining: number;
}
export interface BudgetBurnData {
  points: BudgetBurnPoint[];
  summary: { fiscal_year: number; allocated: number; total_deducted: number; remaining: number };
}

// Per-head actual vs limit
export interface PerHeadPoint {
  po_id: number;
  span: string | null;
  period_start: string | null;
  lifecycle_status: string;
  actual_per_head: number | null;
  estimated_per_head: number | null;
  pending: boolean;
  limit_per_head: number | null;
}
export interface PerHeadData {
  points: PerHeadPoint[];
  summary: { fiscal_year: number; limit_per_head: number | null; avg_actual: number | null };
}

// Procurement deduction timeline
export interface TimelineEntry {
  type: string;
  date: string | null;
  po_id?: number;
  reference?: string;
  procurement_span?: string | null;
  total_cost?: number;
  actual_per_head?: number;
  estimated_per_head?: number | null;
  amount?: number;
  reason?: string | null;
  created_by?: string | null;
}
export interface DeductionTimelineData {
  timeline: TimelineEntry[];
  fiscal_year: number;
}

// Spend by supplier
export interface SupplierSpendPoint { supplier_id: number | null; supplier: string; total: number }
export interface SpendBySupplierData {
  points: SupplierSpendPoint[];
  fiscal_year: number;
  total: number;
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

const fyQs = (fiscalYear?: number) =>
  fiscalYear ? `?fiscal_year=${fiscalYear}` : "";

export async function getBudgetBurn(fiscalYear?: number): Promise<BudgetBurnData> {
  return unwrap(await apiFetch(`/api/fss/insights/budget-burn${fyQs(fiscalYear)}`), "Failed to load budget burn.");
}
export async function getPerHeadActualVsLimit(fiscalYear?: number): Promise<PerHeadData> {
  return unwrap(await apiFetch(`/api/fss/insights/per-head-actual-vs-limit${fyQs(fiscalYear)}`), "Failed to load per-head data.");
}
export async function getProcurementDeductionTimeline(fiscalYear?: number): Promise<DeductionTimelineData> {
  return unwrap(await apiFetch(`/api/fss/insights/procurement-deduction-timeline${fyQs(fiscalYear)}`), "Failed to load timeline.");
}
export async function getSpendBySupplier(fiscalYear?: number): Promise<SpendBySupplierData> {
  return unwrap(await apiFetch(`/api/fss/insights/spend-by-supplier${fyQs(fiscalYear)}`), "Failed to load spend by supplier.");
}
