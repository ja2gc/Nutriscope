import { apiFetch } from "@/lib/apiFetch";

export type ReportType =
  | "program_project_activity"
  | "menu_calendar"
  | "procurement_pack"
  | "accomplishment_report"
  | "demographic_census"
  | "patient_menu_plan"
  | "ncp_summary";

export interface ReportItem {
  id: number;
  title: string;
  type: string;
  status: "pending" | "generating" | "completed" | "failed" | "queued" | "archived";
  file_path: string | null;
  snapshot: ReportSnapshot | null;
  generated_at: string | null;
  created_at: string;
}

/** The frozen as-filed metadata captured when a report is archived (Spec 4). */
export interface ReportSnapshot {
  branding?: Record<string, string | null>;
  signatories?: Array<{ role: string; label: string; name: string | null; title: string | null }>;
  params?: Record<string, string | number>;
  archived_at?: string;
}

/** How a report type is browsed: dated buckets, a record, or a single "now". */
export type ReportAxis = "period" | "entity" | "singleton";

/** A renderable instance of a report type (one month, one PO, one patient, …). */
export interface ReportInstance {
  key: string;
  label: string;
  params: Record<string, string | number>;
  date: string | null;
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
export async function listReports(prefix: "rnd" | "admin" = "rnd"): Promise<ReportItem[]> {
  return unwrap(await apiFetch(`/api/${prefix}/reports`), "Failed to load reports.");
}

export async function deleteReport(id: number, prefix: "rnd" | "admin" = "rnd"): Promise<void> {
  const res = await apiFetch(`/api/${prefix}/reports/${id}`, { method: "DELETE" });
  if (!res.ok && res.status !== 204) throw new Error("Failed to delete report.");
}

export const reportDownloadUrl = (id: number, prefix: "rnd" | "admin" = "rnd") =>
  `/api/${prefix}/reports/${id}/download`;

/** Inline (viewable) URL for an archived copy's frozen PDF — for the preview pane. */
export const reportViewUrl = (id: number, prefix: "rnd" | "admin" = "rnd") =>
  `/api/${prefix}/reports/${id}/view`;

// ── Browse / on-demand render / archive (Spec 4) ──────────────────────────
function toQuery(params: ReportParams): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(clean(params))) qs.set(k, String(v));
  const s = qs.toString();
  return s ? `?${s}` : "";
}

/** Enumerate the renderable instances of a type (only those with data). */
export async function listInstances(
  type: ReportType | string,
  filters: ReportParams = {},
  prefix: "rnd" | "admin" = "rnd",
): Promise<{ axis: ReportAxis; instances: ReportInstance[] }> {
  return unwrap(
    await apiFetch(`/api/${prefix}/reports/${type}/instances${toQuery(filters)}`),
    "Failed to load report instances.",
  );
}

/** URL that streams a freshly rendered (live) PDF — open in a new tab. */
export const reportRenderUrl = (
  type: ReportType | string,
  params: ReportParams,
  prefix: "rnd" | "admin" = "rnd",
) => `/api/${prefix}/reports/${type}/render${toQuery(params)}`;

/** Freeze an as-filed copy: render, store, and persist a snapshot. */
export async function archiveReport(
  type: ReportType | string,
  params: ReportParams,
  prefix: "rnd" | "admin" = "rnd",
): Promise<ReportItem> {
  return unwrap(
    await apiFetch(`/api/${prefix}/reports/${type}/archive${toQuery(params)}`, { method: "POST" }),
    "Failed to archive report.",
  );
}

// ── Branding + templates (Template Edit tab) ──────────────────────────────

/** Base helper — fetches branding from the given proxy path. */
async function fetchBranding(path: string): Promise<Branding> {
  return unwrap(await apiFetch(path), "Failed to load branding.");
}

/** Base helper — saves branding to the given proxy path. */
async function postBranding(path: string, form: FormData): Promise<Branding> {
  return unwrap(
    await apiFetch(path, { method: "POST", body: form }),
    "Failed to save branding.",
  );
}

/** RND proxy path — used by the RND reports/template tab. */
export async function getBranding(): Promise<Branding> {
  return fetchBranding("/api/rnd/report-branding");
}

export async function saveBranding(form: FormData): Promise<Branding> {
  return postBranding("/api/rnd/report-branding", form);
}

/** Admin proxy path — used by the Admin settings page. */
export async function getAdminBranding(): Promise<Branding> {
  return fetchBranding("/api/admin/report-branding");
}

export async function saveAdminBranding(form: FormData): Promise<Branding> {
  return postBranding("/api/admin/report-branding", form);
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

function clean(p: ReportParams): ReportParams {
  return Object.fromEntries(Object.entries(p).filter(([, v]) => v !== null && v !== undefined && v !== "")) as ReportParams;
}
