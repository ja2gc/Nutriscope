"use client";

import React, { useCallback, useEffect, useMemo, useState } from "react";
import {
  FileText, RefreshCw, CalendarRange, CalendarDays, PackageCheck,
  Download, Trash2, Users, ClipboardList, Building2, Save,
  Archive, Loader2, CheckCircle2, AlertTriangle, FolderArchive, Eye, Stethoscope,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Tabs } from "@/components/ui/Tabs";
import { Badge, BadgeTone } from "@/components/ui/Badge";
import { Pagination } from "@/components/ui/Pagination";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState } from "@/components/ui/EmptyState";
import {
  ReportItem, ReportTemplate, Branding, ReportAxis, ReportInstance,
  listReports, deleteReport, reportDownloadUrl, reportViewUrl,
  listInstances, reportRenderUrl, archiveReport,
  getBranding, saveBranding, listTemplates, saveTemplate,
} from "@/services/reportService";
import { ReportPreview } from "@/components/ReportPreview";

const inp = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500";
const lbl = "block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1";

const STATUS_TONE: Record<string, BadgeTone> = {
  archived: "violet", completed: "emerald", generating: "amber", pending: "amber", queued: "amber", failed: "red",
};

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

// Admin-allowed catalog — non-clinical PHI types only
export const ADMIN_CATALOG: CatalogEntry[] = [
  { type: "demographic_census", name: "Demographic Census", desc: "Patient counts by age, sex, ward, diagnosis.", icon: ClipboardList, group: "Clinical" },
  { type: "procurement_pack", name: "Procurement Pack", desc: "AIR + Statement + Summary of Marketing.", icon: PackageCheck, group: "Food Service" },
];

export type ApiPrefix = "rnd" | "admin";

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
      className={`flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-xl border w-fit ${
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
  const tabs = apiPrefix === "admin"
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
        subtitle="Browse any report by period or record and download it on demand. Archive the ones you formally file to freeze an as-submitted copy."
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
            <div className="px-4 pt-4 pb-2 text-[10px] font-extrabold text-zinc-400 uppercase tracking-wider">{g}</div>
            <div className="pb-2">
              {catalog.filter((c) => c.group === g).map((c) => {
                const Icon = c.icon;
                const active = c.type === selected.type;
                return (
                  <button
                    key={c.type}
                    onClick={() => setSelected(c)}
                    aria-current={active}
                    className={`w-full flex items-center gap-2.5 px-4 py-2.5 text-left text-sm transition-colors cursor-pointer border-l-2 ${
                      active
                        ? "border-emerald-600 bg-emerald-50/60 text-emerald-700 font-semibold"
                        : "border-transparent text-zinc-600 hover:bg-zinc-50"
                    }`}
                  >
                    <Icon className={`h-4 w-4 shrink-0 ${active ? "text-emerald-600" : "text-zinc-400"}`} />
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
  const [preview, setPreview] = useState<ReportInstance | null>(null);
  const [page, setPage] = useState(1);
  const Icon = entry.icon;

  useEffect(() => {
    let alive = true;
    setLoading(true);
    listInstances(entry.type, {}, apiPrefix)
      .then((r) => { if (alive) { setAxis(r.axis); setInstances(r.instances); } })
      .catch((e) => { if (alive) onFlash(false, e instanceof Error ? e.message : "Failed to load."); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, [entry.type, apiPrefix, onFlash]);

  const years = useMemo(() => {
    const ys = new Set<string>();
    instances.forEach((i) => { if (i.date) ys.add(i.date.slice(0, 4)); });
    return Array.from(ys).sort().reverse();
  }, [instances]);

  const shown = useMemo(
    () => (year === "all" ? instances : instances.filter((i) => i.date?.startsWith(year))),
    [instances, year],
  );

  useEffect(() => { setPage(1); }, [shown]);

  const pagedShown = useMemo(
    () => shown.slice((page - 1) * 15, page * 15),
    [shown, page],
  );

  const instancesMeta = useMemo(() => ({
    current_page: page,
    per_page: 15,
    total: shown.length,
    last_page: Math.max(1, Math.ceil(shown.length / 15)),
  }), [shown.length, page]);

  async function onArchive(i: ReportInstance) {
    setBusy(i.key);
    try {
      await archiveReport(entry.type, i.params, apiPrefix);
      onFlash(true, `Archived "${i.label}". Find it in the Archived tab.`);
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Archive failed.");
    } finally {
      setBusy(null);
    }
  }

  return (
    <Card className="overflow-hidden">
      <div className="px-5 py-4 border-b border-zinc-100 flex items-start justify-between gap-4">
        <div className="flex items-start gap-3">
          <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600"><Icon className="h-5 w-5" /></div>
          <div>
            <h2 className="text-sm font-bold text-zinc-800">{entry.name}</h2>
            <p className="text-[11px] text-zinc-500 mt-0.5 leading-snug">{entry.desc}</p>
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

      {/* Live vs archived disclosure (spec §8.1) */}
      <div className="px-5 py-2.5 bg-zinc-50/70 border-b border-zinc-100 text-[11px] text-zinc-500 flex items-center gap-1.5">
        <Eye className="h-3.5 w-3.5 text-zinc-400" />
        <span><span className="font-semibold text-zinc-600">Click a report to view it.</span> The preview renders live from current data — use <span className="font-semibold text-zinc-600">Archive</span> to freeze an as-filed copy.</span>
      </div>

      {loading ? (
        <div className="py-16 text-center text-xs text-zinc-400 flex items-center justify-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin" /> Loading…
        </div>
      ) : shown.length === 0 ? (
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
            <li key={i.key} className="flex items-center justify-between gap-3 hover:bg-zinc-50/60">
              <button
                onClick={() => setPreview(i)}
                className="flex-1 min-w-0 text-left px-5 py-3 cursor-pointer flex items-center gap-2.5 group focus:outline-none focus-visible:bg-emerald-50/40"
              >
                <Eye className="h-4 w-4 text-zinc-300 group-hover:text-emerald-500 shrink-0" />
                <span className="min-w-0">
                  <span className="block text-sm font-semibold text-zinc-800 truncate">{i.label}</span>
                  {i.date && <span className="block text-[11px] text-zinc-400 tabular-nums">{new Date(i.date).toLocaleDateString()}</span>}
                </span>
              </button>
              <div className="px-5 shrink-0">
                <Button
                  variant="secondary"
                  onClick={() => onArchive(i)}
                  loading={busy === i.key}
                  className="!w-auto !py-1.5 !px-3 text-xs"
                >
                  <Archive className="h-3.5 w-3.5" /> Archive
                </Button>
              </div>
            </li>
          ))}
        </ul>
        <Pagination meta={instancesMeta} page={page} onPageChange={setPage} />
        </>
      )}

      {preview && (
        <ReportPreview
          title={`${entry.name} — ${preview.label}`}
          src={reportRenderUrl(entry.type, preview.params, apiPrefix)}
          downloadUrl={reportRenderUrl(entry.type, preview.params, apiPrefix)}
          onArchive={() => onArchive(preview)}
          archiving={busy === preview.key}
          onClose={() => setPreview(null)}
        />
      )}
    </Card>
  );
}

// ── Archived tab: the frozen, as-filed copies ───────────────────────────────
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
  const [page, setPage] = useState(1);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const all = await listReports(apiPrefix);
      setReports(all.filter((r) => r.status === "archived" || !!r.file_path));
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Failed to load archive.");
    } finally {
      setLoading(false);
    }
  }, [apiPrefix, onFlash]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => { setPage(1); }, [reports]);

  const pagedReports = useMemo(() => reports.slice((page - 1) * 15, page * 15), [reports, page]);
  const archiveMeta = useMemo(() => ({
    current_page: page,
    per_page: 15,
    total: reports.length,
    last_page: Math.max(1, Math.ceil(reports.length / 15)),
  }), [reports.length, page]);

  async function onDelete(id: number) {
    try {
      await deleteReport(id, apiPrefix);
      onFlash(true, "Archived copy deleted.");
      load();
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Delete failed.");
    }
  }

  const label = (type: string) => catalog.find((c) => c.type === type)?.name ?? type;

  return (
    <Card className="overflow-hidden">
      <div className="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
        <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Archived Reports</h2>
        <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 cursor-pointer">
          <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
        </button>
      </div>

      {loading ? (
        <div className="py-12 text-center text-xs text-zinc-400 flex items-center justify-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>
      ) : reports.length === 0 ? (
        <div className="py-12">
          <EmptyState
            icon={<Archive className="h-6 w-6" />}
            title="No archived copies"
            message="Archive a report from the Browse tab to freeze an as-filed copy here. Archived copies keep their original branding and figures even if the live data changes."
          />
        </div>
      ) : (
        <table className="w-full text-xs">
          <thead className="bg-zinc-50 border-b border-zinc-100">
            <tr>{["Report", "Type", "Archived", "Status", "Actions"].map((h) => (
              <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {pagedReports.map((r) => (
              <tr key={r.id} className="hover:bg-zinc-50/60">
                <td className="px-4 py-3 font-semibold text-zinc-800">{r.title}</td>
                <td className="px-4 py-3 text-zinc-500">{label(r.type)}</td>
                <td className="px-4 py-3 text-zinc-500 tabular-nums">{r.generated_at ? new Date(r.generated_at).toLocaleString() : "—"}</td>
                <td className="px-4 py-3"><Badge tone={STATUS_TONE[r.status] ?? "zinc"}>{r.status}</Badge></td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-1">
                    {r.file_path && (
                      <button onClick={() => setPreview(r)} className="p-1.5 rounded-lg hover:bg-zinc-100 text-zinc-500 cursor-pointer" aria-label={`View ${r.title}`} title="View"><Eye className="h-3.5 w-3.5" /></button>
                    )}
                    {r.file_path && (
                      <a href={reportDownloadUrl(r.id, apiPrefix)} download className="p-1.5 rounded-lg hover:bg-emerald-50 text-zinc-500 hover:text-emerald-600" aria-label={`Download ${r.title}`} title="Download frozen copy">
                        <Download className="h-3.5 w-3.5" />
                      </a>
                    )}
                    <button onClick={() => onDelete(r.id)} className="p-1.5 rounded-lg hover:bg-red-50 text-zinc-500 hover:text-red-600 cursor-pointer" aria-label={`Delete ${r.title}`} title="Delete">
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {!loading && reports.length > 0 && (
        <Pagination meta={archiveMeta} page={page} onPageChange={setPage} />
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
  const [savingT, setSavingT] = useState<number | null>(null);

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

  function editSig(tid: number, idx: number, field: "name" | "title", val: string) {
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
        <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-1">Header Branding</h2>
        <p className="text-[11px] text-zinc-500 mb-4">Shared across every report header. The &quot;prepared by&quot; name auto-fills from the logged-in user; these are the fallbacks.</p>
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
            <div><label className={lbl}>Left logo</label><input type="file" name="logo_left" accept="image/*" className="text-xs text-zinc-500" /></div>
            <div><label className={lbl}>Right logo</label><input type="file" name="logo_right" accept="image/*" className="text-xs text-zinc-500" /></div>
          </div>
          <Button variant="primary" type="submit" loading={savingB} className="!w-auto !py-2 !px-4 flex items-center gap-2"><Save className="h-4 w-4" /> Save Branding</Button>
        </form>
      </Card>

      {/* Signatories per report */}
      <div className="grid lg:grid-cols-2 gap-4">
        {templates.map((t) => (
          <Card key={t.id} padded className="space-y-3">
            <div>
              <h3 className="text-sm font-bold text-zinc-800">{t.name}</h3>
              {t.description && <p className="text-[11px] text-zinc-500">{t.description}</p>}
            </div>
            {(t.signatories ?? []).length === 0 ? (
              <p className="text-[11px] text-zinc-400">No signatory block.</p>
            ) : (
              <div className="space-y-2">
                {(t.signatories ?? []).map((s, i) => (
                  <div key={`${s.role}-${i}`} className="grid grid-cols-[90px_1fr_1fr] gap-2 items-center">
                    <span className="text-[10px] font-bold text-zinc-400 uppercase truncate" title={s.label}>{s.label}</span>
                    <input value={s.name ?? ""} onChange={(e) => editSig(t.id, i, "name", e.target.value)} placeholder="Name" className={`${inp} !py-1.5`} />
                    <input value={s.title ?? ""} onChange={(e) => editSig(t.id, i, "title", e.target.value)} placeholder="Title" className={`${inp} !py-1.5`} />
                  </div>
                ))}
              </div>
            )}
            <Button variant="secondary" onClick={() => saveT(t)} loading={savingT === t.id} className="!w-auto !py-1.5 !px-3.5 text-xs flex items-center gap-1.5"><Save className="h-3.5 w-3.5" /> Save</Button>
          </Card>
        ))}
      </div>
    </div>
  );
}
