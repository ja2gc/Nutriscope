import { apiFetch } from "@/lib/apiFetch";

export interface SupplierSpendPoint { supplier_id: number | null; supplier: string; total: number }
export interface SpendBySupplier { points: SupplierSpendPoint[]; summary: { total: number; range: { start: string; end: string } } }

export interface CostPerHeadPoint { cycle_id: number; cycle: string; cost_per_head: number; population: number }
export interface CostPerHead { points: CostPerHeadPoint[]; summary: { avg: number } }

export interface ConsumptionPoint { date: string; actual: number; shortfall: boolean }
export interface Consumption { points: ConsumptionPoint[]; summary: { total: number; days: number; shortfall_days: number; range: { start: string; end: string } } }

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

const qs = (o: { start?: string; end?: string }) => {
  const p = new URLSearchParams();
  if (o.start) p.set("start", o.start);
  if (o.end) p.set("end", o.end);
  return p.toString();
};

export async function getSpendBySupplier(o: { start?: string; end?: string }): Promise<SpendBySupplier> {
  return unwrap(await apiFetch(`/api/fss/insights/spend-by-supplier?${qs(o)}`), "Failed to load spend by supplier.");
}
export async function getCostPerHead(): Promise<CostPerHead> {
  return unwrap(await apiFetch(`/api/fss/insights/cost-per-head`), "Failed to load cost per head.");
}
export async function getConsumption(o: { start?: string; end?: string }): Promise<Consumption> {
  return unwrap(await apiFetch(`/api/fss/insights/consumption?${qs(o)}`), "Failed to load consumption.");
}
