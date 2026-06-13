import { apiFetch } from "@/lib/apiFetch";

export interface MealPrepLogLine {
  id: number;
  fs_item_id: number;
  qty_base: string;
  unit: string;
  unit_cost: string;
  line_value: string;
  shortfall_qty: string;
}

export interface MealPrepLog {
  id: number;
  menu_cycle_id: number;
  service_date: string;
  status: "completed" | "reversed";
  total_value: string;
  has_shortfall: boolean;
  completed_at: string | null;
  menu_cycle?: { id: number; name: string } | null;
  completed_by?: { id: number; name: string } | null;
  lines?: MealPrepLogLine[];
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

/** Complete a whole service day for a cycle — deducts that day's planned ingredients from stock. */
export async function completeServiceDay(menuCycleId: number, serviceDate: string, population?: number): Promise<MealPrepLog> {
  return unwrap(await apiFetch(`/api/fss/menu-cycles/${menuCycleId}/complete-day`, {
    method: "POST", headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ service_date: serviceDate, population }),
  }), "Failed to complete service day.");
}

/** Reverse a completed service day — restores the deducted quantities to stock. */
export async function reverseServiceDay(logId: number): Promise<MealPrepLog> {
  return unwrap(await apiFetch(`/api/fss/meal-prep-logs/${logId}/reverse`, { method: "POST" }), "Failed to reverse service day.");
}

export async function listServiceLogs(params: { menu_cycle_id?: number; from?: string; to?: string } = {}): Promise<MealPrepLog[]> {
  const qs = new URLSearchParams();
  if (params.menu_cycle_id) qs.set("menu_cycle_id", String(params.menu_cycle_id));
  if (params.from) qs.set("from", params.from);
  if (params.to) qs.set("to", params.to);
  return unwrap(await apiFetch(`/api/fss/meal-prep-logs?${qs}`), "Failed to load service logs.");
}
