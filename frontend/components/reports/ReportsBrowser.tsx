"use client";

import React, { useCallback, useEffect, useState } from "react";
import Image from "next/image";
import {
  FileText, CalendarRange, CalendarDays, PackageCheck,
  ClipboardList, Save,
  Archive, Loader2, CheckCircle2, AlertTriangle, Eye,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Tabs } from "@/components/ui/Tabs";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState } from "@/components/ui/EmptyState";
import SearchInput from "@/components/ui/SearchInput";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import {
  ReportItem, ReportTemplate, Branding, ReportAxis, ReportInstance,
  listReports, unarchiveReport, getReportArchiveSettings, setReportArchiveSettings, reportDownloadUrl, reportViewUrl,
  listInstances, prepareReport, archiveReport,
   getBranding, saveBranding, getAdminBranding, saveAdminBranding, brandingLogoUrl, listTemplates, saveTemplate,
} from "@/services/reportService";
import { ReportPreview } from "@/components/ReportPreview";
import { PatientsNcpTab } from "@/components/reports/PatientsNcpTab";
import { CensusPanel } from "@/components/reports/CensusPanel";
import { ImageFilePicker } from "@/components/ui/ImageFilePicker";
import { InfoHint } from "@/components/ui/InfoHint";

const inp = "w-full px-3 py-2 text-base border border-warm-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500";
const lbl = "block text-xs font-extrabold text-warm-500 uppercase tracking-wider mb-1";

function reportDate(value: string): string {
  return new Date(value).toLocaleString("en-PH", {
    timeZone: "Asia/Manila",
    dateStyle: "medium",
    timeStyle: "short",
  });
}

function reportCalendarDate(value: string): string {
  return new Date(value).toLocaleDateString("en-PH", { timeZone: "Asia/Manila", dateStyle: "medium" });
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
  { type: "accomplishment_report", name: "Accomplishment Report", desc: "Per-staff semi-monthly duty sheet + diet-list headcount logged by FSS.", icon: ClipboardList, group: "Food Service" },
  { type: "patients_ncp", name: "Patients NCP", desc: "Choose a patient, then an ADIME cycle, to view its reports.", icon: ClipboardList, group: "Clinical" },
  { type: "demographic_census", name: "Demographic Census", desc: "Year or month summary of ADIME cycle starts.", icon: ClipboardList, group: "Clinical" },
];

// Admin-allowed catalog: RND parity minus patient-specific reports.
export const ADMIN_CATALOG: CatalogEntry[] = [
  { type: "program_project_activity", name: "Program Project Activity", desc: "Weekly menu, cost, headcount & inclusive dates.", icon: CalendarRange, group: "Food Service" },
  { type: "menu_calendar", name: "Menu Calendar", desc: "Printable Mon-Sun grid for the kitchen.", icon: CalendarDays, group: "Food Service" },
  { type: "procurement_pack", name: "Procurement Pack", desc: "AIR + Statement + Summary of Marketing.", icon: PackageCheck, group: "Food Service" },
  { type: "accomplishment_report", name: "Accomplishment Report", desc: "Per-staff semi-monthly duty sheet + diet-list headcount logged by FSS.", icon: ClipboardList, group: "Food Service" },
  { type: "demographic_census", name: "Demographic Census", desc: "Year or month summary of ADIME cycle starts.", icon: ClipboardList, group: "Clinical" },
];

export const FSS_CATALOG: CatalogEntry[] = [
  { type: "accomplishment_report", name: "My Accomplishment Reports", desc: "Your own semi-monthly duty sheets and diet-list headcount logs.", icon: ClipboardList, group: "Food Service" },
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

  const tabs = apiPrefix === "fss"
    ? [
        { key: "browse" as TabKey, label: "Browse" },
        { key: "archived" as TabKey, label: "Archived" },
      ]
    : [
        { key: "browse" as TabKey, label: "Browse" },
        { key: "archived" as TabKey, label: "Archived" },
        { key: "templates" as TabKey, label: "Template Edit" },
      ];

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Home", apiPrefix === "admin" ? "/admin/dashboard" : "/dashboard"], ["Reports"]]}
        icon={<FileText className="h-5 w-5 text-emerald-600" />}
        title="Reports"
      />

      <Tabs<TabKey>
        value={tab}
        onChange={setTab}
        items={tabs}
      />

      <FlashBar flash={flash} />

      {tab === "browse" && <BrowseTab catalog={catalog} apiPrefix={apiPrefix} onFlash={flashFor} />}
      {tab === "archived" && <ArchivedTab catalog={catalog} apiPrefix={apiPrefix} onFlash={flashFor} />}
      {tab === "templates" && apiPrefix !== "fss" && <TemplateEditor apiPrefix={apiPrefix} onFlash={flashFor} />}
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
    const params = new URLSearchParams(window.location.search);
    const requestedType = params.get("type") ?? (params.get("tab") === "patients" ? "patients_ncp" : null);
    setSelected(catalog.find((entry) => entry.type === requestedType) ?? catalog[0]);
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
      {selected.type === "patients_ncp" && apiPrefix === "rnd"
        ? <PatientsNcpTab />
        : selected.type === "demographic_census" && apiPrefix !== "fss"
          ? <CensusPanel apiPrefix={apiPrefix} />
          : <InstancesPanel key={selected.type} entry={selected} apiPrefix={apiPrefix} onFlash={onFlash} />}
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
  const [coveredMonth, setCoveredMonth] = useState("");
  const [busy, setBusy] = useState<string | null>(null);
  const [preview, setPreview] = useState<{ report: ReportItem; label: string } | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);
  const [instancesMeta, setInstancesMeta] = useState<PaginationMeta | null>(null);
  const [refreshVersion, setRefreshVersion] = useState(0);
  const Icon = entry.icon;

  useEffect(() => {
    let alive = true;
    setLoading(true);
    listInstances(entry.type, { page, per_page: 10, search: debouncedSearch, covered_month: coveredMonth }, apiPrefix)
      .then((r) => { if (alive) { setAxis(r.data.axis); setInstances(r.data.instances); setInstancesMeta(r.meta); } })
      .catch((e) => { if (alive) onFlash(false, e instanceof Error ? e.message : "Failed to load."); })
      .finally(() => { if (alive) setLoading(false); });
    return () => { alive = false; };
  }, [entry.type, apiPrefix, onFlash, page, coveredMonth, debouncedSearch, refreshVersion]);

  const pagedShown = instances;

  async function onPreview(i: ReportInstance) {
    if (i.available === false) return;

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

  async function onArchive() {
    if (!preview) return;
    setBusy(preview.report.id);
    try {
      await archiveReport(preview.report.id, apiPrefix);
      onFlash(true, `Archived ${preview.label}.`);
      setPreview(null);
      setRefreshVersion((version) => version + 1);
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Report archive failed.");
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
      </div>

      {axis === "entity" && (
        <div className="border-b border-warm-100 px-5 py-3">
          <SearchInput
            label={`Search ${entry.name}`}
            value={search}
            onChange={(value) => { setSearch(value); setPage(1); }}
            loading={loading && search !== debouncedSearch}
          />
        </div>
      )}

      {entry.group === "Food Service" && <div className="border-b border-warm-100 px-5 py-3">
        <label className="block max-w-xs text-xs font-semibold text-warm-600">Report month
          <input type="month" aria-label="Report month" value={coveredMonth}
            onChange={(event) => { setCoveredMonth(event.target.value); setPage(1); }} className={inp} />
        </label>
      </div>}

      {loading ? (
        <div className="py-16 text-center text-sm text-warm-400 flex items-center justify-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin" /> Loading…
        </div>
      ) : instances.length === 0 ? (
        <div className="py-16">
          <EmptyState
            icon={<Icon className="h-6 w-6" />}
            title="Nothing to show yet"
            message={`No ${entry.name.toLowerCase()} data is available${coveredMonth ? ` for ${coveredMonth}` : ""}. Records appear here once the underlying data exists.`}
          />
        </div>
      ) : (
        <>
        <ul className="divide-y divide-zinc-100">
          {pagedShown.map((i) => (
            <li key={i.key} className="flex items-center justify-between gap-3 hover:bg-warm-50/60">
              <button
                onClick={() => void onPreview(i)}
                disabled={i.available === false || busy !== null}
                className="flex-1 min-w-0 text-left px-5 py-3 cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 flex items-center gap-2.5 group focus:outline-none focus-visible:bg-emerald-50/40"
              >
                <Eye className="h-4 w-4 text-warm-300 group-hover:text-emerald-500 shrink-0" />
                <span className="min-w-0">
                  <span className="block text-base font-semibold text-warm-800 truncate">{i.label}</span>
                  {i.date && <span className="block text-xs text-warm-400 tabular-nums">{new Date(i.date).toLocaleDateString()}</span>}
                </span>
              </button>
              {i.available === false && i.unavailable_reason && (
                <InfoHint label="Why this report is unavailable" title="Menu plan required">
                  {i.unavailable_reason}
                </InfoHint>
              )}
              {busy === i.key && <Loader2 className="mr-5 h-4 w-4 animate-spin text-emerald-600" />}
            </li>
          ))}
        </ul>
        </>
      )}
      {!loading && (
        <Pagination meta={instancesMeta} page={page} onPageChange={setPage} />
      )}

      {preview && (
        <ReportPreview
          title={`${entry.name} — ${preview.label}`}
          src={reportViewUrl(preview.report.id, apiPrefix)}
          downloadUrl={reportDownloadUrl(preview.report.id, apiPrefix)}
          onArchive={() => void onArchive()}
          archiveBusy={busy === preview.report.id}
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
  const types = catalog.filter((entry) => entry.group === "Food Service");
  const [selectedType, setSelectedType] = useState(types[0]?.type ?? "");
  const [coveredMonth, setCoveredMonth] = useState("");
  const [retentionEnabled, setRetentionEnabled] = useState(false);
  const [savingRetention, setSavingRetention] = useState(false);
  const [page, setPage] = useState(1);
  const [archiveMeta, setArchiveMeta] = useState<PaginationMeta | null>(null);
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const result = await listReports(apiPrefix, page, { status: "archived", type: selectedType, search: debouncedSearch, covered_month: coveredMonth });
      setReports(result.data);
      setArchiveMeta(result.meta);
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Failed to load archive.");
    } finally {
      setLoading(false);
    }
  }, [apiPrefix, onFlash, page, debouncedSearch, selectedType, coveredMonth]);

  useEffect(() => { load(); }, [load]);
  useEffect(() => {
    void getReportArchiveSettings()
      .then((settings) => setRetentionEnabled(settings.enabled))
      .catch((error) => onFlash(false, error instanceof Error ? error.message : "Failed to load retention settings."));
  }, [apiPrefix, onFlash]);
  const pagedReports = reports;

  async function changeRetention(enabled: boolean) {
    setSavingRetention(true);
    try {
      const settings = await setReportArchiveSettings(enabled);
      setRetentionEnabled(settings.enabled);
      await load();
    } catch (error) {
      onFlash(false, error instanceof Error ? error.message : "Failed to save retention settings.");
    } finally {
      setSavingRetention(false);
    }
  }

  async function onUnarchive(id: string) {
    try {
      await unarchiveReport(id, apiPrefix);
      onFlash(true, "Report restored to Browse.");
      await load();
    } catch (e) {
      onFlash(false, e instanceof Error ? e.message : "Unarchive failed.");
    }
  }

  return (
    <div className="grid gap-5 items-start lg:grid-cols-[260px_1fr]">
    <Card className="overflow-hidden">
      {types.map((entry) => {
        return <button key={entry.type} type="button" aria-current={selectedType === entry.type}
          onClick={() => { setSelectedType(entry.type); setPage(1); }}
          className={`w-full px-4 py-2.5 text-left text-sm border-l-2 hover:bg-warm-50 ${selectedType === entry.type ? "border-emerald-600 bg-emerald-50/60 text-emerald-700 font-semibold" : "border-transparent text-warm-600"}`}>
          {entry.name}
        </button>;
      })}
    </Card>
    <Card className="overflow-hidden">
      <div className="px-5 py-3 border-b border-warm-100 flex items-center justify-between">
        <h2 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Archived Reports</h2>
        {apiPrefix === "admin" && <label className="flex items-center gap-2 text-xs font-semibold text-warm-600">
          <input type="checkbox" aria-label="Enable five-year report retention" checked={retentionEnabled}
            disabled={savingRetention} onChange={(event) => void changeRetention(event.target.checked)} />
          Five-year retention
        </label>}
      </div>
      {retentionEnabled && <p className="px-5 py-2 text-xs text-warm-600 border-b border-warm-100">
        Archived reports and their PDFs are purged five years after archiving. Unarchive before expiry to keep a report.
      </p>}
      <div className="border-b border-warm-100 px-5 py-3 space-y-3">
        <SearchInput
          label="Search archived reports"
          value={search}
          onChange={(value) => { setSearch(value); setPage(1); }}
          loading={loading && search !== debouncedSearch}
        />
        <div>
          <label className="block max-w-xs text-xs font-semibold text-warm-600">Report month
            <input type="month" aria-label="Report month" value={coveredMonth} onChange={(event) => { setCoveredMonth(event.target.value); setPage(1); }} className={inp} />
          </label>
        </div>
      </div>

      {loading ? (
        <div className="py-12 text-center text-sm text-warm-400 flex items-center justify-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>
      ) : reports.length === 0 ? (
        <div className="py-12">
          <EmptyState
            icon={<Archive className="h-6 w-6" />}
            title="No archived reports"
            message="Archived reports keep their filed PDF. Preview and download show that frozen copy."
          />
        </div>
      ) : (
        <div className="overflow-x-auto">
        <table className="w-full min-w-[540px] text-sm">
          <thead className="bg-warm-50 border-b border-warm-100">
            <tr>{["Report", "Created by", "Covers through", "Actions"].map((h) => (
              <th key={h} className="px-4 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {pagedReports.map((r) => (
              <tr key={r.id} className="hover:bg-warm-50/60">
                <td className="px-4 py-3 font-semibold text-warm-800">{r.title}
                  {retentionEnabled && r.retention_expires_at && <span className="block text-xs font-normal text-warm-500">Expires on {reportDate(r.retention_expires_at)}</span>}
                </td>
                <td className="px-4 py-3 text-warm-600">{r.created_by?.name ?? "Former user"}</td>
                <td className="px-4 py-3 text-warm-500 tabular-nums">
                  {r.report_covered_until ? (
                    <time
                      dateTime={r.report_covered_until}
                    >
                      {reportCalendarDate(r.report_covered_until)}
                    </time>
                  ) : "—"}
                </td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-1">
                    <button onClick={() => setPreview(r)} className="p-1.5 rounded-lg hover:bg-warm-100 text-warm-500 cursor-pointer" aria-label={`View ${r.title}`} title="View"><Eye className="h-3.5 w-3.5" /></button>
                    <button onClick={() => void onUnarchive(r.id)} className="p-1.5 rounded-lg hover:bg-emerald-50 text-warm-500 hover:text-emerald-700 cursor-pointer" aria-label={`Unarchive ${r.title}`} title="Unarchive"><Archive className="h-3.5 w-3.5" /></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
      )}
      {!loading && (
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
    </div>
  );
}

// ── Template Edit tab ───────────────────────────────────────────────────────
function TemplateEditor({ apiPrefix, onFlash }: { apiPrefix: "rnd" | "admin"; onFlash: (ok: boolean, msg: string) => void }) {
  const [branding, setBranding] = useState<Branding | null>(null);
  const [brandingDraft, setBrandingDraft] = useState<Branding | null>(null);
  const [templates, setTemplates] = useState<ReportTemplate[]>([]);
  const [templateDraft, setTemplateDraft] = useState<ReportTemplate | null>(null);
  const [editingBranding, setEditingBranding] = useState(false);
  const [editingTemplateId, setEditingTemplateId] = useState<string | null>(null);
  const [savingB, setSavingB] = useState(false);
  const [savingT, setSavingT] = useState<string | null>(null);

  const load = useCallback(() => {
    (apiPrefix === "admin" ? getAdminBranding() : getBranding())
      .then((value) => { setBranding(value); setBrandingDraft(value); }).catch(() => {});
    listTemplates(apiPrefix).then(setTemplates).catch(() => {});
  }, [apiPrefix]);
  useEffect(() => { load(); }, [load]);

  const setB = (p: Partial<Branding>) => setBrandingDraft((b) => (b ? { ...b, ...p } : b));

  async function saveB(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!brandingDraft) return;
    setSavingB(true);
    try {
      const fd = new FormData(e.currentTarget);
      const updated = await (apiPrefix === "admin" ? saveAdminBranding(fd) : saveBranding(fd));
      setBranding(updated);
      setBrandingDraft(updated);
      setEditingBranding(false);
      onFlash(true, "Branding saved.");
    } catch (err) {
      onFlash(false, err instanceof Error ? err.message : "Save failed.");
    } finally { setSavingB(false); }
  }

  async function saveT(t: ReportTemplate) {
    setSavingT(t.id);
    try {
      const updated = await saveTemplate(t.id, { signatories: t.signatories ?? [] }, apiPrefix);
      setTemplates((items) => items.map((item) => item.id === updated.id ? updated : item));
      setTemplateDraft(null);
      setEditingTemplateId(null);
      onFlash(true, `${t.name} signatories saved.`);
    } catch (err) {
      onFlash(false, err instanceof Error ? err.message : "Save failed.");
    } finally { setSavingT(null); }
  }

  function editSig(tid: string, idx: number, field: "name" | "title", val: string) {
    setTemplateDraft((t) => !t || t.id !== tid ? t : {
      ...t,
      signatories: (t.signatories ?? []).map((s, i) => i === idx ? { ...s, [field]: val } : s),
    });
  }

  const isClinicalAutoFilledSignatory = (template: ReportTemplate) =>
    ["patient_menu_plan", "ncp_summary"].includes(template.type);

  if (!branding) return <EmptyState message="Loading branding…" />;

  return (
    <div className="space-y-5">
      {/* Branding */}
      <Card padded>
        <div className="mb-4 flex items-start justify-between gap-3">
          <h2 className="text-sm font-extrabold text-warm-700 uppercase tracking-wider">Header Branding</h2>
          {!editingBranding && <Button variant="secondary" onClick={() => { setBrandingDraft(branding); setEditingBranding(true); }} className="!w-auto !py-1.5 !px-3.5 text-sm">Edit</Button>}
        </div>
        {!editingBranding ? (
          <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {[["Hospital name", branding.hospital_name], ["Address", branding.address], ["Accreditation", branding.accreditation], ["Service name", branding.service_name], ["Province", branding.province], ["LGU", branding.lgu]].map(([label, value]) => <div key={label}><dt className={lbl}>{label}</dt><dd className="text-sm text-warm-800">{value || "—"}</dd></div>)}
            <div><dt className={lbl}>Left logo</dt><dd>{branding.logo_left_path ? <Image src={brandingLogoUrl(apiPrefix, "left")} alt="Current left report logo" width={72} height={72} unoptimized className="h-18 w-18 rounded-lg border border-warm-200 bg-white object-contain p-1" /> : <span className="text-sm text-warm-800">Not set</span>}</dd></div>
            <div><dt className={lbl}>Right logo</dt><dd>{branding.logo_right_path ? <Image src={brandingLogoUrl(apiPrefix, "right")} alt="Current right report logo" width={72} height={72} unoptimized className="h-18 w-18 rounded-lg border border-warm-200 bg-white object-contain p-1" /> : <span className="text-sm text-warm-800">Not set</span>}</dd></div>
          </dl>
        ) : <form onSubmit={saveB} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {([
              ["hospital_name", "Hospital name", brandingDraft?.hospital_name],
              ["address", "Address", brandingDraft?.address],
              ["accreditation", "Accreditation", brandingDraft?.accreditation],
              ["service_name", "Service name", brandingDraft?.service_name],
              ["province", "Province", brandingDraft?.province],
              ["lgu", "LGU", brandingDraft?.lgu],
            ] as const).map(([name, label, value]) => (
              <div key={name}>
                <label className={lbl}>{label}</label>
                <input name={name} value={value ?? ""} onChange={(e) => setB({ [name]: e.target.value } as Partial<Branding>)} className={inp} />
              </div>
            ))}
            <div className="space-y-2">{branding.logo_left_path && <Image src={brandingLogoUrl(apiPrefix, "left")} alt="Current left report logo" width={72} height={72} unoptimized className="h-18 w-18 rounded-lg border border-warm-200 bg-white object-contain p-1" />}<ImageFilePicker id="report-logo-left" name="logo_left" label="Left logo" current={branding.logo_left_path} /></div>
            <div className="space-y-2">{branding.logo_right_path && <Image src={brandingLogoUrl(apiPrefix, "right")} alt="Current right report logo" width={72} height={72} unoptimized className="h-18 w-18 rounded-lg border border-warm-200 bg-white object-contain p-1" />}<ImageFilePicker id="report-logo-right" name="logo_right" label="Right logo" current={branding.logo_right_path} /></div>
          </div>
          <div className="flex gap-2"><Button variant="primary" type="submit" loading={savingB} className="!w-auto !py-2 !px-4 flex items-center gap-2"><Save className="h-4 w-4" /> Save</Button><Button variant="secondary" type="button" onClick={() => { setBrandingDraft(branding); setEditingBranding(false); }} className="!w-auto !py-2 !px-4">Cancel</Button></div>
        </form>}
      </Card>

      {/* Signatories per report */}
      <div className="grid lg:grid-cols-2 gap-4">
        {templates.map((saved) => {
          const t = editingTemplateId === saved.id && templateDraft ? templateDraft : saved;
          const protectedClinical = isClinicalAutoFilledSignatory(t);
          const templateName = t.type === "patient_menu_plan" ? "Nutrition Intervention Plan" : t.name;
          return <Card key={t.id} padded className="space-y-3">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <h3 className="text-base font-bold text-warm-800">{templateName}</h3>
                {t.description && <p className="text-xs text-warm-500">{t.description}</p>}
              </div>
              {!protectedClinical && editingTemplateId !== t.id && <Button variant="secondary" onClick={() => { setTemplateDraft({ ...saved, signatories: (saved.signatories ?? []).map((s) => ({ ...s })) }); setEditingTemplateId(saved.id); }} className="!w-auto shrink-0 !py-1.5 !px-3.5 text-sm">Edit</Button>}
            </div>
            {protectedClinical ? (
              <p className="text-xs text-warm-500">Prepared by and attending physician come from patient care records.</p>
            ) : (t.signatories ?? []).length === 0 ? (
              <p className="text-xs text-warm-400">No signatory block.</p>
            ) : (
              <div className="space-y-2">
                {(t.signatories ?? []).map((s, i) => (
                  <div key={`${s.role}-${i}`} className="grid grid-cols-[90px_1fr_1fr] gap-2 items-center">
                    <span className="text-xs font-bold text-warm-400 uppercase truncate" title={s.label}>{s.label}</span>
                    {editingTemplateId === t.id ? <><input value={s.name ?? ""} onChange={(e) => editSig(t.id, i, "name", e.target.value)} className={`${inp} !py-1.5`} /><input value={s.title ?? ""} onChange={(e) => editSig(t.id, i, "title", e.target.value)} className={`${inp} !py-1.5`} /></> : <><span className="text-sm text-warm-800">{s.name || "—"}</span><span className="text-sm text-warm-600">{s.title || "—"}</span></>}
                  </div>
                ))}
              </div>
            )}
            {!protectedClinical && editingTemplateId === t.id && <div className="flex gap-2"><Button variant="primary" onClick={() => saveT(t)} loading={savingT === t.id} className="!w-auto !py-1.5 !px-3.5 text-sm"><Save className="h-3.5 w-3.5" /> Save</Button><Button variant="secondary" onClick={() => { setTemplateDraft(null); setEditingTemplateId(null); }} className="!w-auto !py-1.5 !px-3.5 text-sm">Cancel</Button></div>}
          </Card>;
        })}
      </div>
    </div>
  );
}
