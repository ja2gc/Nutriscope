"use client";

import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  FileText, RefreshCw, CalendarRange, CalendarDays, PackageCheck,
  Download, Trash2, Users, ClipboardList, Building2, Save,
  Archive, Loader2, CheckCircle2, AlertTriangle, FolderArchive, Eye, Stethoscope,
  History,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Tabs } from "@/components/ui/Tabs";
import { Badge, BadgeTone } from "@/components/ui/Badge";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState } from "@/components/ui/EmptyState";
import {
  ReportItem, ReportTemplate, Branding, ReportAxis, ReportInstance,
  listReports, deleteReport, reportDownloadUrl, reportViewUrl,
  listInstances, prepareReport,
  getBranding, saveBranding, listTemplates, saveTemplate,
} from "@/services/reportService";
import { ReportPreview } from "@/components/ReportPreview";
import { AuditTrail } from "@/components/audit/AuditTrail";

const inp = "w-full px-3 py-2 text-base border border-warm-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500";
const lbl = "block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1";

const STATUS_TONE: Record<string, BadgeTone> = {
  archived: "violet", completed: "emerald", generating: "amber", pending: "amber", queued: "amber", failed: "red",
};

function reportDate(value: string): string {
  return new Date(value).toLocaleString("en-PH", {
    timeZone: "Asia/Manila",
    dateStyle: "medium",
    timeStyle: "short",
  });
}

type TabKey = "browse" | "archived" | "templates";

// ── Report catalog ──────────────────────────────────────────────────────────
export type ReportGroup = "Food Service" | "Clinical";
export interface CatalogEntry { type: string; name: string; desc: string; icon: React.ElementType; group: ReportGroup }

// Full catalog — used by the RND page
export const FULL_CATALOG: CatalogEntry[] = [
  { type: "program_project_activity", name: "Program Project Activity", desc: "Weekly menu, cost, headcount & inclusive dates.", icon: CalendarRange, group: "Food Service" },
  { type: "menu_calendar", name: "Menu Calendar", desc: "Printable Mon→Sun grid for the kitchen.", icon: CalendarDays, group: "Food Service" },
  { type: "procurement_pack", name: "Procurement Pack", desc: "AIR + Statement + Summary of Marketing.", icon: PackageCheck, group: "Food Service" },
  { type: "accomplishment_report", name: "Accomplishment Report", desc: "Per-staff weekly duty sheet + diet-list headcount logged by FSS.", icon: ClipboardList, group: "Food Service" },
  { type: "demographic_census", name: "Demographic Census", desc: "Patient counts by age, sex, ward, diagnosis.", icon: ClipboardList, group: "Clinical" },
  { type: "patient_menu_plan", name: "Patient Menu Plan", desc: "A patient's ADIME meal plan as a calendar.", icon: Users, group: "Clinical" },
  { type: "ncp_summary", name: "NCP Summary", desc: "Patient Nutrition Care Plan (ADIME) — assessment, diagnosis, intervention, monitoring.", icon: Stethoscope, group: "Clinical" },
];

// Admin-allowed catalog: RND parity minus patient-specific reports.
export const ADMIN_CATALOG: CatalogEntry[] = [
  { type: "program_project_activity", name: "Program Project Activity", desc: "Weekly menu, cost, headcount & inclusive dates.", icon: CalendarRange, group: "Food Service" },
  { type: "menu_calendar", name: "Menu Calendar", desc: "Printable Mon-Sun grid for the kitchen.", icon: CalendarDays, group: "Food Service" },
  { type: "procurement_pack", name: "Procurement Pack", desc: "AIR + Statement + Summary of Marketing.", icon: PackageCheck, group: "Food Service" },
  { type: "accomplishment_report", name: "Accomplishment Report", desc: "Per-staff weekly duty sheet + diet-list headcount logged by FSS.", icon: ClipboardList, group: "Food Service" },
  { type: "demographic_census", name: "Demographic Census", desc: "Aggregate patient counts by age, sex, ward, diagnosis.", icon: ClipboardList, group: "Clinical" },
];

export const FSS_CATALOG: CatalogEntry[] = [
  { type: "accomplishment_report", name: "My Accomplishment Reports", desc: "Your own weekly duty sheets and diet-list headcount logs.", icon: ClipboardList, group: "Food Service" },
];

export type ApiPrefix = "rnd" | "admin" | "fss";

export interface ReportsBrowserProps {
  catalog: CatalogEntry[];
  apiPrefix: ApiPrefix;
}

type Flash = { ok: boolean; msg: string } | null;

function FlashBar({ flash }: { flash: Flash }) {
  if (!flash) return null;
  return (
    <div
      role="status"
      aria-live="polite"
      className={`flex items-center gap-2 text-sm font-bold px-3 py-2 rounded-xl border w-fit ${
        flash.ok ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-red-50 text-red-700 border-red-200"
      }`}
    >
      {flash.ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <AlertTriangle className="h-3.5 w-3.5" />}
      {flash.msg}
    </div>
  );
}

export function ReportsBrowser({ catalog, apiPrefix }: ReportsBrowserProps) {
  const [tab, setTab] = useState<TabKey>("browse");
  const [flash, setFlash] = useState<Flash>(null);
  const flashFor = useCallback((ok: boolean, msg: string) => {
    setFlash({ ok, msg });
    setTimeout(() => setFlash(null), 4000);
  }, []);

  // Admin browser suppresses the Template Edit tab (branding owned by Settings page)
  const tabs = apiPrefix !== "rnd"
    ? [
        { key: "browse" as TabKey, label: "Browse", icon: <FileText className="h-4 w-4" /> },
        { key: "archived" as TabKey, label: "Archived", icon: <FolderArchive className="h-4 w-4" /> },
      ]
    : [
        { key: "browse" as TabKey, label: "Browse", icon: <FileText className="h-4 w-4" /> },
        { key: "archived" as TabKey, label: "Archived", icon: <FolderArchive className="h-4 w-4" /> },
        { key: "templates" as TabKey, label: "Template Edit", icon: <Building2 className="h-4 w-4" /> },
      ];

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Home", apiPrefix === "admin" ? "/admin/dashboard" : "/dashboard"], ["Reports"]]}
        icon={<FileText className="h-5 w-5 text-emerald-600" />}
        title="Reports"
        subtitle="Open a saved report with current source data, preview it, or download a local copy for printing."
      />

      <Tabs<TabKey>
        value={tab}
        onChange={setTab}
        items={tabs}
      />

      <FlashBar flash={flash} />

      {tab === "browse" && <BrowseTab catalog={catalog} apiPrefix={apiPrefix} onFlash={flashFor} />}
      {tab === "archived" && <ArchivedTab catalog={catalog} apiPrefix={apiPrefix} onFlash={flashFor} />}
      {tab === "templates" && apiPrefix === "rnd" && <TemplateEditor onFlash={flashFor} />}
    </div>
  );
}

// ── Browse tab: type rail → instances panel ─────────────────────────────────
function BrowseTab({
  catalog,
  apiPrefix,
  onFlash,
}: {
  catalog: CatalogEntry[];
  apiPrefix: ApiPrefix;
  onFlash: (ok: boolean, msg: string) => void;
}) {
  const [selected, setSelected] = useState<CatalogEntry>(catalog[0]);
  const groups = Array.from(new Set(catalog.map((c) => c.group))) as ReportGroup[];

  // Reset selected when catalog changes (e.g. navigating between pages)
  useEffect(() => {
    setSelected(catalog[0]);
  }, [catalog]);

  return (
    <div className="grid lg:grid-cols-[260px_1fr] gap-5 items-start">
      {/* Type rail */}
      <Card className="overflow-hidden">
        {groups.map((g) => (
          <div key={g}>
            <div className="px-4 pt-4 pb-2 text-xs font-extrabold text-warm-400 uppercase tracking-wider">{g}</div>
            <div className="pb-2">
              {catalog.filter((c) => c.group === g).map((c) => {
                const Icon = c.icon;
                const active = c.type === selected.type;
                return (
                  <button
                    key={c.type}
                    onClick={() => setSelected(c)}
                    aria-current={active}
                    className={`w-full flex items-center gap-2.5 px-4 py-2.5 text-left text-base transition-colors cursor-pointer border-l-2 ${
                      active
                        ? "border-emerald-600 bg-emerald-50/60 text-emerald-700 font-semibold"
                        : "border-transparent text-warm-600 hover:bg-warm-50"
                    }`}
                  >
                    <Icon className={`h-4 w-4 shrink-0 ${active ? "text-emerald-600" : "text-warm-400"}`} />
                    <span className="truncate">{c.name}</span>
                  </button>
                );
              })}
            </div>
          </div>
        ))}
      </Card>

      {/* Instances panel — remounts per type so its state resets cleanly */}
      <InstancesPanel key={selected.type} entry={selected} apiPrefix={apiPrefix} onFlash={onFlash} />
    </div>
  );
}

function InstancesPanel({
  entry,
  apiPrefix,
  onFlash,
}: {
  entry: CatalogEntry;
  apiPrefix: ApiPrefix;
  onFlash: (ok: boolean, msg: string) => void;
}) {
  const [loading, setLoading] = useState(true);
  const [axis, setAxis] = useState<ReportAxis>("entity");
  const [instances, setInstances] = useState<ReportInstance[]>([]);
  const [year, setYear] = useState<string>("all");
  const [busy, setBusy] = useState<string | null>(null);
  const [preview, setPreview] = useState<{ report: ReportItem; label: string } | null>(null);
  const [page, setPage] = useState(1);
  const [instancesMeta, setInstancesMeta] = useState<PaginationMeta | null>(null);
  const Icon = entry.icon;

  useEffect(() => {
    let alive = true;
    setLoading(true);
    listInstances(entry.type, { page, per_page: 10, ...(year !== "all" ? { year } : {}) }, apiPrefix)
      .then((r) => { if (alive) { setAxis(r.data.axis); setInstances(r.data.instances); setInstancesMeta(r.meta); } })
      .catch((e) => { if (alive) onFlash(false, e instanceof Error ? e.message : "Failed to load."); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, [entry.type, apiPrefix, onFlash, page, year]);

  const years = useMemo(() => {
    const ys = new Set<string>();
    instances.forEach((i) => { if (i.date) ys.add(i.date.slice(0, 4)); });
    return Array.from(ys).sort().reverse();
  }, [instances]);

  const pagedShown = instances;

  async function onPreview(i: ReportInstance) {
    setBusy(i.key);
    try {
      const report = await prepareReport(entry.type, i.params, apiPrefix);
      setPreview({ report, label: i.label });
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Report preparation failed.");
    } finally {
      setBusy(null);
    }
  }

  return (
    <Card className="overflow-hidden">
      <div className="px-5 py-4 border-b border-warm-100 flex items-start justify-between gap-4">
        <div className="flex items-start gap-3">
          <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600"><Icon className="h-5 w-5" /></div>
          <div>
            <h2 className="text-base font-bold text-warm-800">{entry.name}</h2>
            <p className="text-xs text-warm-500 mt-0.5 leading-snug">{entry.desc}</p>
          </div>
        </div>
        {axis === "period" && years.length > 0 && (
          <div>
            <label className={lbl} htmlFor="year">Year</label>
            <select id="year" value={year} onChange={(e) => setYear(e.target.value)} className={inp}>
              <option value="all">All years</option>
              {years.map((y) => <option key={y} value={y}>{y}</option>)}
            </select>
          </div>
        )}
      </div>

      {/* Saved-report behavior */}
      <div className="px-5 py-2.5 bg-warm-50/70 border-b border-warm-100 text-xs text-warm-500 flex items-center gap-1.5">
        <Eye className="h-3.5 w-3.5 text-warm-400" />
        <span><span className="font-semibold text-warm-600">Click a report to view it.</span> NutriScope saves its identity automatically and refreshes important source data when needed.</span>
      </div>

      {loading ? (
        <div className="py-16 text-center text-sm text-warm-400 flex items-center justify-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin" /> Loading…
        </div>
      ) : instances.length === 0 ? (
        <div className="py-16">
          <EmptyState
            icon={<Icon className="h-6 w-6" />}
            title="Nothing to show yet"
            message={`No ${entry.name.toLowerCase()} data is available${year !== "all" ? ` for ${year}` : ""}. Records appear here once the underlying data exists.`}
          />
        </div>
      ) : (
        <>
        <ul className="divide-y divide-zinc-100">
          {pagedShown.map((i) => (
            <li key={i.key} className="flex items-center justify-between gap-3 hover:bg-warm-50/60">
              <button
                onClick={() => void onPreview(i)}
                className="flex-1 min-w-0 text-left px-5 py-3 cursor-pointer flex items-center gap-2.5 group focus:outline-none focus-visible:bg-emerald-50/40"
              >
                <Eye className="h-4 w-4 text-warm-300 group-hover:text-emerald-500 shrink-0" />
                <span className="min-w-0">
                  <span className="block text-base font-semibold text-warm-800 truncate">{i.label}</span>
                  {i.date && <span className="block text-xs text-warm-400 tabular-nums">{new Date(i.date).toLocaleDateString()}</span>}
                </span>
              </button>
              {busy === i.key && <Loader2 className="mr-5 h-4 w-4 animate-spin text-emerald-600" />}
            </li>
          ))}
        </ul>
        <Pagination meta={instancesMeta} page={page} onPageChange={setPage} />
        </>
      )}

      {preview && (
        <ReportPreview
          title={`${entry.name} — ${preview.label}`}
          src={reportViewUrl(preview.report.id, apiPrefix)}
          downloadUrl={reportDownloadUrl(preview.report.id, apiPrefix)}
          onClose={() => setPreview(null)}
        />
      )}
    </Card>
  );
}

// Archived tab: saved reports hidden from the active list.
function ArchivedTab({
  catalog,
  apiPrefix,
  onFlash,
}: {
  catalog: CatalogEntry[];
  apiPrefix: ApiPrefix;
  onFlash: (ok: boolean, msg: string) => void;
}) {
  const [reports, setReports] = useState<ReportItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [preview, setPreview] = useState<ReportItem | null>(null);
  const [historyReport, setHistoryReport] = useState<ReportItem | null>(null);
  const [page, setPage] = useState(1);
  const [archiveMeta, setArchiveMeta] = useState<PaginationMeta | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const result = await listReports(apiPrefix, page);
      setReports(result.data.filter((r) => r.status === "archived"));
      setArchiveMeta(result.meta);
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Failed to load archive.");
    } finally {
      setLoading(false);
    }
  }, [apiPrefix, onFlash, page]);

  useEffect(() => { load(); }, [load]);
  const pagedReports = reports;

  async function onDelete(id: string) {
    try {
      await deleteReport(id, apiPrefix);
      onFlash(true, "Report remains archived.");
      load();
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Delete failed.");
    }
  }

  const label = (type: string) => catalog.find((c) => c.type === type)?.name ?? type;

  return (
    <Card className="overflow-hidden">
      <div className="px-5 py-3 border-b border-warm-100 flex items-center justify-between">
        <h2 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Archived Reports</h2>
        <button onClick={load} className="flex items-center gap-1.5 text-sm text-warm-500 hover:text-warm-700 cursor-pointer">
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
        </button>
      </div>

      {loading ? (
        <div className="py-12 text-center text-sm text-warm-400 flex items-center justify-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>
      ) : reports.length === 0 ? (
        <div className="py-12">
          <EmptyState
            icon={<Archive className="h-6 w-6" />}
            title="No archived reports"
            message="Archived reports are hidden from the active saved-report list. Preview and download use the latest prepared content."
          />
        </div>
      ) : (
        <div className="overflow-x-auto">
        <table className="w-full min-w-[760px] text-sm">
          <thead className="bg-warm-50 border-b border-warm-100">
            <tr>{["Report", "Type", "Created by", "Archived", "Status", "Actions"].map((h) => (
              <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {pagedReports.map((r) => (
              <tr key={r.id} className="hover:bg-warm-50/60">
                <td className="px-4 py-3 font-semibold text-warm-800">{r.title}</td>
                <td className="px-4 py-3 text-warm-500">{label(r.type)}</td>
                <td className="px-4 py-3 text-warm-600">{r.created_by?.name ?? "Former user"}</td>
                <td className="px-4 py-3 text-warm-500 tabular-nums">
                  {r.snapshot?.archived_at || r.generated_at ? (
                    <time
                      dateTime={r.snapshot?.archived_at ?? r.generated_at ?? undefined}
                      title={new Date(r.snapshot?.archived_at ?? r.generated_at!).toISOString()}
                    >
                      {reportDate(r.snapshot?.archived_at ?? r.generated_at!)}
                    </time>
                  ) : "—"}
                </td>
                <td className="px-4 py-3"><Badge tone={STATUS_TONE[r.status] ?? "zinc"}>{r.status}</Badge></td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-1">
                    {r.file_path && (
                      <button onClick={() => setPreview(r)} className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 cursor-pointer" aria-label={`View ${r.title}`} title="View"><Eye className="h-3.5 w-3.5" /></button>
                    )}
                    {r.file_path && (
                      <a href={reportDownloadUrl(r.id, apiPrefix)} download className="p-1.5 rounded-lg hover:bg-emerald-50 text-warm-500 hover:text-emerald-600" aria-label={`Download ${r.title}`} title="Download current prepared copy">
                        <Download className="h-3.5 w-3.5" />
                      </a>
                    )}
                    {apiPrefix !== "fss" && (
                      <button onClick={() => setHistoryReport(r)} className="p-1.5 rounded-lg hover:bg-sky-50 text-warm-500 hover:text-sky-700 cursor-pointer" aria-label={`Show activity for ${r.title}`} title="Activity trail">
                        <History className="h-3.5 w-3.5" />
                      </button>
                    )}
                    <button onClick={() => onDelete(r.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-warm-500 hover:text-red-600 cursor-pointer" aria-label={`Delete ${r.title}`} title="Delete">
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
      )}
      {!loading && reports.length > 0 && (
        <Pagination meta={archiveMeta} page={page} onPageChange={setPage} />
      )}

      {historyReport && apiPrefix !== "fss" && (
        <div className="border-t border-warm-100 p-4">
          <AuditTrail
            path={`/api/${apiPrefix}/reports/${historyReport.id}/activity`}
            title={`${historyReport.title} lifecycle`}
          />
        </div>
      )}

      {preview && (
        <ReportPreview
          title={preview.title}
          src={reportViewUrl(preview.id, apiPrefix)}
          downloadUrl={reportDownloadUrl(preview.id, apiPrefix)}
          onClose={() => setPreview(null)}
        />
      )}
    </Card>
  );
}

// ── Template Edit tab (RND only) ────────────────────────────────────────────
function TemplateEditor({ onFlash }: { onFlash: (ok: boolean, msg: string) => void }) {
  const [branding, setBranding] = useState<Branding | null>(null);
  const [templates, setTemplates] = useState<ReportTemplate[]>([]);
  const [savingB, setSavingB] = useState(false);
  const [savingT, setSavingT] = useState<string | null>(null);

  const load = useCallback(() => {
    getBranding().then(setBranding).catch(() => {});
    listTemplates().then(setTemplates).catch(() => {});
  }, []);
  useEffect(() => { load(); }, [load]);

  const setB = (p: Partial<Branding>) => setBranding((b) => (b ? { ...b, ...p } : b));

  async function saveB(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!branding) return;
    setSavingB(true);
    try {
      const fd = new FormData(e.currentTarget);
      const updated = await saveBranding(fd);
      setBranding(updated);
      onFlash(true, "Branding saved.");
    } catch (err) {
      onFlash(false, err instanceof Error ? err.message : "Save failed.");
    } finally { setSavingB(false); }
  }

  async function saveT(t: ReportTemplate) {
    setSavingT(t.id);
    try {
      await saveTemplate(t.id, { signatories: t.signatories ?? [] });
      onFlash(true, `${t.name} signatories saved.`);
    } catch (err) {
      onFlash(false, err instanceof Error ? err.message : "Save failed.");
    } finally { setSavingT(null); }
  }

  function editSig(tid: string, idx: number, field: "name" | "title", val: string) {
    setTemplates((ts) => ts.map((t) => t.id !== tid ? t : {
      ...t,
      signatories: (t.signatories ?? []).map((s, i) => i === idx ? { ...s, [field]: val } : s),
    }));
  }

  if (!branding) return <EmptyState message="Loading branding…" />;

  return (
    <div className="space-y-5">
      {/* Branding */}
      <Card padded>
        <h2 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider mb-1">Header Branding</h2>
        <p className="text-xs text-warm-500 mb-4">Shared across every report header. The &quot;prepared by&quot; name auto-fills from the logged-in user; these are the fallbacks.</p>
        <form onSubmit={saveB} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {([
              ["hospital_name", "Hospital name", branding.hospital_name],
              ["address", "Address", branding.address],
              ["accreditation", "Accreditation", branding.accreditation],
              ["service_name", "Service name", branding.service_name],
              ["province", "Province", branding.province],
              ["lgu", "LGU", branding.lgu],
            ] as const).map(([name, label, value]) => (
              <div key={name}>
                <label className={lbl}>{label}</label>
                <input name={name} value={value ?? ""} onChange={(e) => setB({ [name]: e.target.value } as Partial<Branding>)} className={inp} />
              </div>
            ))}
            <div><label className={lbl}>Left logo</label><input type="file" name="logo_left" accept="image/*" className="text-sm text-warm-500" /></div>
            <div><label className={lbl}>Right logo</label><input type="file" name="logo_right" accept="image/*" className="text-sm text-warm-500" /></div>
          </div>
          <Button variant="primary" type="submit" loading={savingB} className="!w-auto !py-2 !px-4 flex items-center gap-2"><Save className="h-4 w-4" /> Save Branding</Button>
        </form>
      </Card>

      {/* Signatories per report */}
      <div className="grid lg:grid-cols-2 gap-4">
        {templates.map((t) => (
          <Card key={t.id} padded className="space-y-3">
            <div>
              <h3 className="text-base font-bold text-warm-800">{t.name}</h3>
              {t.description && <p className="text-xs text-warm-500">{t.description}</p>}
            </div>
            {(t.signatories ?? []).length === 0 ? (
              <p className="text-xs text-warm-400">No signatory block.</p>
            ) : (
              <div className="space-y-2">
                {(t.signatories ?? []).map((s, i) => (
                  <div key={`${s.role}-${i}`} className="grid grid-cols-[90px_1fr_1fr] gap-2 items-center">
                    <span className="text-xs font-bold text-warm-400 uppercase truncate" title={s.label}>{s.label}</span>
                    <input value={s.name ?? ""} onChange={(e) => editSig(t.id, i, "name", e.target.value)} placeholder="Name" className={`${inp} !py-1.5`} />
                    <input value={s.title ?? ""} onChange={(e) => editSig(t.id, i, "title", e.target.value)} placeholder="Title" className={`${inp} !py-1.5`} />
                  </div>
                ))}
              </div>
            )}
            <Button variant="secondary" onClick={() => saveT(t)} loading={savingT === t.id} className="!w-auto !py-1.5 !px-3.5 text-sm flex items-center gap-1.5"><Save className="h-3.5 w-3.5" /> Save</Button>
          </Card>
        ))}
      </div>
    </div>
  );
}
