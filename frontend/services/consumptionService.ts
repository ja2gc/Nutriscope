import { apiFetch } from "@/lib/apiFetch";

export interface MealPrepLog {
  id: string;
  menu_cycle_id: number;
  service_date: string;
  status: "completed" | "reversed";
  served_population: number | null;
}

async function unwrap<T>(response: Response, fallback: string): Promise<T> {
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error((body as { message?: string }).message ?? fallback);
  return (body as { data: T }).data;
}

export async function setServedPopulation(menuCycleId: string | number, serviceDate: string, servedPopulation: number): Promise<MealPrepLog> {
  return unwrap(await apiFetch(`/api/fss/menu-cycles/${menuCycleId}/served-population`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ service_date: serviceDate, served_population: servedPopulation }),
  }), "Failed to save served population.");
}

export async function listServiceLogs(params: { menu_cycle_id?: string | number; from?: string; to?: string } = {}): Promise<MealPrepLog[]> {
  const query = new URLSearchParams();
  if (params.menu_cycle_id) query.set("menu_cycle_id", String(params.menu_cycle_id));
  if (params.from) query.set("from", params.from);
  if (params.to) query.set("to", params.to);
  return unwrap(await apiFetch(`/api/fss/meal-prep-logs?${query}`), "Failed to load served population.");
}
