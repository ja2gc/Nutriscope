import { apiFetch } from "@/lib/apiFetch";

export type ReportType =
  | "program_project_activity"
  | "menu_calendar"
  | "dietary_cash_book"
  | "procurement_pack"
  | "demographic_census"
  | "patient_menu_plan"
  | "budget_report"
  | "inventory_report";

export interface ReportItem {
  id: number;
  title: string;
  type: string;
  status: "pending" | "generating" | "completed" | "failed" | "queued";
  file_path: string | null;
  generated_at: string | null;
  created_at: string;
}

export interface Signatory {
  role: string;
  label: string;
  name: string | null;
  title: string | null;
}

export interface ReportTemplate {
  id: number;
  type: string;
  name: string;
  description: string | null;
  signatories: Signatory[] | null;
}

export interface Branding {
  id: number;
  hospital_name: string;
  address: string | null;
  accreditation: string | null;
  service_name: string | null;
  province: string | null;
  lgu: string | null;
  logo_left_path: string | null;
  logo_right_path: string | null;
}

export type ReportParams = Record<string, string | number | null | undefined>;

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

// ── Reports ───────────────────────────────────────────────────────────────
export async function listReports(): Promise<ReportItem[]> {
  return unwrap(await apiFetch("/api/rnd/reports"), "Failed to load reports.");
}

export async function generateReport(type: ReportType | string, parameters: ReportParams): Promise<ReportItem> {
  return unwrap(
    await apiFetch("/api/rnd/reports", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ template_code: type, parameters: clean(parameters) }),
    }),
    "Failed to generate report.",
  );
}

export async function generateAll(parameters: ReportParams): Promise<ReportItem[]> {
  return unwrap(
    await apiFetch("/api/rnd/reports/generate-all", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ parameters: clean(parameters) }),
    }),
    "Failed to generate report set.",
  );
}

export async function deleteReport(id: number): Promise<void> {
  const res = await apiFetch(`/api/rnd/reports/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete report.");
}

export const reportDownloadUrl = (id: number) => `/api/rnd/reports/${id}/download`;

// ── Branding + templates (Template Edit tab) ──────────────────────────────
export async function getBranding(): Promise<Branding> {
  return unwrap(await apiFetch("/api/rnd/report-branding"), "Failed to load branding.");
}

export async function saveBranding(form: FormData): Promise<Branding> {
  return unwrap(
    await apiFetch("/api/rnd/report-branding", { method: "POST", body: form }),
    "Failed to save branding.",
  );
}

export async function listTemplates(): Promise<ReportTemplate[]> {
  return unwrap(await apiFetch("/api/rnd/report-templates"), "Failed to load templates.");
}

export async function saveTemplate(id: number, payload: { name?: string; signatories?: Signatory[] }): Promise<ReportTemplate> {
  return unwrap(
    await apiFetch(`/api/rnd/report-templates/${id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    }),
    "Failed to save template.",
  );
}

// ── Selector sources (reused FS/NCP endpoints) ────────────────────────────
export interface Option { id: number; label: string }

export async function listMenuCycleOptions(): Promise<Option[]> {
  const data = await unwrap<Array<{ id: number; name: string }>>(await apiFetch("/api/fss/menu-cycles"), "Failed to load menu cycles.");
  return data.map((c) => ({ id: c.id, label: c.name || `Cycle #${c.id}` }));
}

export async function listBudgetOptions(): Promise<Option[]> {
  const data = await unwrap<Array<{ id: number; name: string | null; scope: string }>>(await apiFetch("/api/fss/budgets"), "Failed to load budgets.");
  return data.map((b) => ({ id: b.id, label: `${b.name || `Budget #${b.id}`} (${b.scope})` }));
}

export async function listShoppingListOptions(): Promise<Option[]> {
  const data = await unwrap<Array<{ id: number; name?: string | null }>>(await apiFetch("/api/fss/shopping-lists"), "Failed to load shopping lists.");
  return data.map((s) => ({ id: s.id, label: s.name || `List #${s.id}` }));
}

export async function listPatientOptions(): Promise<Option[]> {
  const res = await apiFetch("/api/patients");
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((json as { message?: string }).message ?? "Failed to load patients.");
  // /api/patients returns a Laravel paginator: { data: [...] }.
  const list: Array<{ id: number; name: string }> = Array.isArray(json) ? json : json.data ?? [];
  return list.map((p) => ({ id: p.id, label: p.name }));
}

function clean(p: ReportParams): ReportParams {
  return Object.fromEntries(Object.entries(p).filter(([, v]) => v !== null && v !== undefined && v !== "")) as ReportParams;
}
