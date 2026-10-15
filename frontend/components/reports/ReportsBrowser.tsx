"use client";

import React, { useCallback, useEffect, useRef, useState } from "react";
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
import { MonthYearSelect } from "@/components/ui/MonthYearSelect";
import SearchInput from "@/components/ui/SearchInput";
import { useDebouncedValue } from "@/hooks/useDebouncedValue";
import {
  ReportItem, ReportTemplate, Branding, ReportAxis, ReportInstance, ReportBrandingRevision, ReportTemplateRevision,
  listReports, unarchiveReport, getReportArchiveSettings, setReportArchiveSettings, reportDownloadUrl, reportViewUrl,
  listInstances, prepareReport, archiveReport,
  listAccomplishmentStaff, listAccomplishmentsToReview, decideAccomplishment,
  type AccomplishmentReview, type AccomplishmentStaff,
   getBranding, saveBranding, getAdminBranding, saveAdminBranding, brandingLogoUrl, brandingHistoryLogoUrl,
   listBrandingHistory, listTemplateHistory, listTemplates, saveTemplate,
} from "@/services/reportService";
import { ReportPreview } from "@/components/ReportPreview";
import { PatientsNcpTab } from "@/components/reports/PatientsNcpTab";
import { CensusPanel } from "@/components/reports/CensusPanel";
import { ImageFilePicker } from "@/components/ui/ImageFilePicker";
import { InfoHint } from "@/components/ui/InfoHint";
import { CharacterCountTextarea } from "@/components/ui/CharacterCountTextarea";
import { personDisplayName } from "@/lib/personName";

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

type HistoryRow = Pick<ReportTemplateRevision, "id" | "created_at" | "origin" | "editor">;

function RevisionHistoryList({
  title, items, page, meta, onPageChange, onSelect,
}: {
  title: string;
  items: HistoryRow[];
  page: number;
  meta: PaginationMeta | null;
  onPageChange: (page: number) => void;
  onSelect: (item: HistoryRow) => void;
}) {
  return (
    <div className="space-y-3">
      <h4 className="text-xs font-extrabold text-warm-500 uppercase tracking-wider">{title}</h4>
      {items.length === 0 ? <p className="text-sm text-warm-500">No saved history yet.</p> : (
        <div className="space-y-2">
          {items.map((item) => (
            <button key={item.id} type="button" onClick={() => onSelect(item)} className="w-full min-w-0 rounded-xl border border-warm-200 bg-white px-3 py-3 text-left hover:bg-warm-50">
              <span className="block text-sm font-bold text-warm-800">{reportDate(item.created_at)}</span>
              <span className="block truncate text-xs text-warm-500">{item.editor?.name ?? (item.origin === "baseline" ? "Initial tracked version" : "System")}</span>
            </button>
          ))}
        </div>
      )}
      <Pagination meta={meta} page={page} onPageChange={onPageChange} />
    </div>
  );
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
          : selected.type === "accomplishment_report"
            ? <AccomplishmentReportsPanel apiPrefix={apiPrefix} onFlash={onFlash} />
          : <InstancesPanel key={selected.type} entry={selected} apiPrefix={apiPrefix} onFlash={onFlash} />}
    </div>
  );
}

function AccomplishmentReportsPanel({ apiPrefix, onFlash }: { apiPrefix: ApiPrefix; onFlash: (ok: boolean, msg: string) => void }) {
  const [view, setView] = useState<"reports" | "review">("reports");
  const [staff, setStaff] = useState<AccomplishmentStaff[]>([]);
  const [staffMeta, setStaffMeta] = useState<PaginationMeta | null>(null);
  const [staffPage, setStaffPage] = useState(1);
  const [staffSearch, setStaffSearch] = useState("");
  const debouncedStaffSearch = useDebouncedValue(staffSearch);
  const [selectedStaff, setSelectedStaff] = useState<AccomplishmentStaff | null>(null);
  const [coveredMonth, setCoveredMonth] = useState("");
  const [filterYear, setFilterYear] = useState(new Date().getFullYear());
  const [reports, setReports] = useState<ReportItem[]>([]);
  const [queue, setQueue] = useState<AccomplishmentReview[]>([]);
  const [selected, setSelected] = useState<AccomplishmentReview | null>(null);
  const [preview, setPreview] = useState<ReportItem | null>(null);
  const [reason, setReason] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState("");
  const [busy, setBusy] = useState(false);
  const reviewRef = useRef<HTMLDivElement>(null);

  useEffect(() => { if (selected) reviewRef.current?.focus(); }, [selected]);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError("");
    try {
      if (apiPrefix === "rnd" && !selectedStaff) {
        const result = await listAccomplishmentStaff(staffPage, debouncedStaffSearch);
        setStaff(result.data);
        setStaffMeta(result.meta);
        return;
      }
      if (view === "review" && apiPrefix === "rnd") {
        setQueue(await listAccomplishmentsToReview({ fss_user_id: selectedStaff?.id, ...(coveredMonth ? { covered_month: coveredMonth } : {}) }));
      } else {
        const result = await listReports(apiPrefix, page, { type: "accomplishment_report", ...(selectedStaff ? { fss_user_id: selectedStaff.id } : {}), ...(coveredMonth ? { covered_month: coveredMonth } : {}) });
        setReports(result.data);
        setMeta(result.meta);
      }
    } catch (error) {
      setLoadError(error instanceof Error ? error.message : "Could not load reports.");
    } finally { setLoading(false); }
  }, [apiPrefix, coveredMonth, debouncedStaffSearch, page, selectedStaff, staffPage, view]);
  useEffect(() => { void load(); }, [load]);

  async function decide(decision: "approve" | "reject") {
    if (!selected || (decision === "reject" && !reason.trim())) return;
    setBusy(true);
    try {
      await decideAccomplishment(selected.report_id, selected.submission_version, decision, reason.trim());
      setSelected(null);
      setReason("");
      setQueue(await listAccomplishmentsToReview({ fss_user_id: selectedStaff?.id, ...(coveredMonth ? { covered_month: coveredMonth } : {}) }));
      onFlash(true, decision === "approve" ? "Submission approved." : "Submission returned for correction.");
    } catch (error) {
      onFlash(false, error instanceof Error ? error.message : "Could not save review. Refresh and try again.");
      await load();
    } finally { setBusy(false); }
  }

  return <Card className="min-w-0 overflow-hidden">
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-warm-100 px-4 py-4 sm:px-5">
      <div>{selectedStaff && <button type="button" onClick={() => { setSelectedStaff(null); setSelected(null); setCoveredMonth(""); }} className="mb-2 min-h-11 text-sm font-semibold text-emerald-700">Back to Food Service Staff</button>}<h2 className="font-bold text-warm-800">{selectedStaff?.display_name ?? "Accomplishment Report"}</h2><p className="text-sm text-warm-500">{selectedStaff ? "Semi-monthly accomplishment reports" : apiPrefix === "rnd" ? "Select Food Service Staff to view reports" : "Semi-monthly reports from Food Service Staff"}</p></div>
      {apiPrefix === "rnd" && selectedStaff && <div className="flex gap-2" aria-label="Accomplishment views">
        {(["reports", "review"] as const).map((key) => <button key={key} type="button" onClick={() => { setView(key); setSelected(null); setPage(1); }} aria-current={view === key} className={`min-h-11 rounded-lg px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-emerald-600 ${view === key ? "bg-emerald-700 text-white" : "bg-warm-50 text-warm-700"}`}>{key === "review" ? "To Review" : "Approved Reports"}</button>)}
      </div>}
    </div>
    {apiPrefix === "rnd" && !selectedStaff ? <>
      <div className="border-b border-warm-100 px-4 py-3 sm:px-5"><SearchInput label="Search Food Service Staff" value={staffSearch} onChange={(value) => { setStaffSearch(value); setStaffPage(1); }} /></div>
      {loadError ? <div className="p-5" role="alert"><p className="text-sm text-red-700">{loadError}</p><button type="button" onClick={() => void load()} className="mt-2 min-h-11 font-semibold text-emerald-700">Retry</button></div> : loading ? <p className="px-5 py-10 text-sm text-warm-500">Loading staff…</p> : staff.length === 0 ? <div className="p-6"><EmptyState title="No Food Service Staff found" message="Try another name." /></div> : <ul className="divide-y divide-warm-100">{staff.map((user) => <li key={user.id}><button type="button" onClick={() => { setSelectedStaff(user); setView("reports"); setPage(1); setCoveredMonth(""); }} className="min-h-14 w-full px-4 py-3 text-left font-semibold text-warm-800 hover:bg-warm-50 focus-visible:outline-2 focus-visible:outline-emerald-600 sm:px-5">{user.display_name}</button></li>)}</ul>}
      {!loading && !loadError && <Pagination meta={staffMeta} page={staffPage} onPageChange={setStaffPage} />}
    </> : <>
    <div className="border-b border-warm-100 px-4 py-3 sm:px-5">
      <div className="grid max-w-sm grid-cols-2 gap-2">
        <MonthYearSelect month={coveredMonth ? Number(coveredMonth.slice(5, 7)) : null} year={filterYear} allowAllMonths monthAriaLabel="Report month" yearAriaLabel="Report year"
          onMonthChange={(month) => { setCoveredMonth(month === null ? "" : `${filterYear}-${String(month).padStart(2, "0")}`); setPage(1); }}
          onYearChange={(year) => { setFilterYear(year); if (coveredMonth) setCoveredMonth(`${year}-${coveredMonth.slice(5, 7)}`); setPage(1); }} />
      </div>
    </div>
    {loadError ? <div className="p-5" role="alert"><p className="text-sm text-red-700">{loadError}</p><button type="button" onClick={() => void load()} className="mt-2 min-h-11 font-semibold text-emerald-700">Retry</button></div> : loading ? <p className="px-5 py-10 text-sm text-warm-500">Loading…</p> : view === "review" && apiPrefix === "rnd" ? (
      queue.length === 0 ? <div className="p-6"><EmptyState title="No submissions to review" message="Submitted periods appear here." /></div> :
      <ul className="divide-y divide-warm-100">{queue.map((item) => <li key={item.report_id} className="hover:bg-warm-50/60">
        <button type="button" onClick={() => { setSelected(item); setReason(""); }} aria-label={`Review submission for ${item.fss_user.display_name}, ${item.start} to ${item.end}`} className="flex min-h-14 w-full items-center gap-3 px-4 py-4 text-left focus-visible:bg-emerald-50/40 focus-visible:outline-2 focus-visible:outline-emerald-600 sm:px-5">
          <Eye className="h-4 w-4 shrink-0 text-warm-300" />
          <span className="min-w-0"><span className="block break-words font-semibold text-warm-800">{item.fss_user.display_name}</span><span className="block text-sm text-warm-600">{item.start} to {item.end}</span><span className="block text-xs text-warm-500">Submitted {reportDate(item.submitted_at)}</span></span>
        </button>
      </li>)}</ul>
    ) : reports.length === 0 ? <div className="p-6"><EmptyState title="No reports yet" message="Approved reports and earlier generated reports appear here." /></div> :
      <><ul className="divide-y divide-warm-100">{reports.map((report) => <li key={report.id} className="hover:bg-warm-50/60">
        <button type="button" onClick={() => setPreview(report)} aria-label={`Open report: ${report.title}`} className="flex min-h-14 w-full items-center gap-3 px-4 py-4 text-left focus-visible:bg-emerald-50/40 focus-visible:outline-2 focus-visible:outline-emerald-600 sm:px-5">
          <Eye className="h-4 w-4 shrink-0 text-warm-300" />
          <span className="min-w-0"><span className="block break-words font-semibold text-warm-800">{selectedStaff && report.report_covered_from && report.report_covered_until ? `${reportCalendarDate(report.report_covered_from)} – ${reportCalendarDate(report.report_covered_until)}` : personDisplayName(report.created_by, "Food Service Staff")}</span><span className="block text-sm text-warm-600">{selectedStaff ? `${report.title} · ${report.review_state === "approved" ? "Approved" : "Earlier generated"}` : report.report_covered_until ? `Through ${reportCalendarDate(report.report_covered_until)}` : report.title}</span></span>
        </button>
      </li>)}</ul><div className="px-4 py-3"><Pagination meta={meta} page={page} onPageChange={setPage} /></div></>
    }
    {selected && <div ref={reviewRef} tabIndex={-1} className="border-t border-warm-200 bg-warm-50 p-4 sm:p-5 focus:outline-2 focus:outline-emerald-700" aria-label="Review submission">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-bold text-warm-800">{selected.fss_user.display_name}</h3><p className="text-sm text-warm-600">{selected.start} to {selected.end}</p></div><button type="button" onClick={() => setSelected(null)} className="min-h-11 px-2 text-sm text-warm-600">Close</button></div>
      <a href={reportViewUrl(selected.report_id, "rnd")} target="_blank" rel="noopener noreferrer" className="mt-3 inline-flex min-h-11 items-center rounded-lg border border-emerald-700 px-4 text-sm font-semibold text-emerald-700">View submitted PDF</a>
      <div className="mt-4 max-w-xl"><label htmlFor="accomplishment-rejection-reason" className="mb-1 block text-sm font-semibold text-warm-700">Reason for rejection</label><CharacterCountTextarea id="accomplishment-rejection-reason" value={reason} onChange={setReason} maxLength={500} rows={4} className={inp} /></div>
      <div className="mt-4 flex flex-wrap gap-2"><Button type="button" onClick={() => void decide("approve")} disabled={busy}>Approve</Button><Button type="button" variant="secondary" onClick={() => void decide("reject")} disabled={busy || !reason.trim()}>Reject</Button></div>
    </div>}
    {preview && <ReportPreview title={preview.title} src={reportViewUrl(preview.id, apiPrefix)} downloadUrl={reportDownloadUrl(preview.id, apiPrefix)} onClose={() => setPreview(null)} />}
    </>}
  </Card>;
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
  const [filterYear, setFilterYear] = useState(new Date().getFullYear());
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
  const hasProcurementSpan = entry.type === "procurement_pack" || entry.type === "program_project_activity";

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
        <div className="grid max-w-sm grid-cols-2 gap-2">
          <MonthYearSelect month={coveredMonth ? Number(coveredMonth.slice(5, 7)) : null} year={filterYear} allowAllMonths monthAriaLabel="Report month" yearAriaLabel="Report year"
            onMonthChange={(month) => { setCoveredMonth(month === null ? "" : `${filterYear}-${String(month).padStart(2, "0")}`); setPage(1); }}
            onYearChange={(year) => { setFilterYear(year); if (coveredMonth) setCoveredMonth(`${year}-${coveredMonth.slice(5, 7)}`); setPage(1); }} />
        </div>
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
        {hasProcurementSpan && (
          <div className="grid grid-cols-[minmax(0,1fr)_8.5rem] gap-3 border-b border-warm-100 px-5 py-2 text-xs font-semibold text-warm-500 sm:grid-cols-[minmax(0,1fr)_14rem]">
            <span className="pl-7">PO name</span><span>Procurement span</span>
          </div>
        )}
        <ul className="divide-y divide-zinc-100">
          {pagedShown.map((i) => (
            <li key={i.key} className="flex items-center justify-between gap-3 hover:bg-warm-50/60">
              <button
                onClick={() => void onPreview(i)}
                disabled={i.available === false || busy !== null}
                className={`flex-1 min-w-0 text-left px-5 py-3 cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 gap-3 items-center group focus:outline-none focus-visible:bg-emerald-50/40 ${hasProcurementSpan ? "grid grid-cols-[minmax(0,1fr)_8.5rem] sm:grid-cols-[minmax(0,1fr)_14rem]" : "flex"}`}
              >
                <span className="flex min-w-0 items-center gap-2.5">
                  <Eye className="h-4 w-4 text-warm-300 group-hover:text-emerald-500 shrink-0" />
                  <span className="min-w-0">
                    <span className={`block text-base font-semibold text-warm-800 ${hasProcurementSpan ? "break-words" : "truncate"}`}>{i.label}</span>
                    {!hasProcurementSpan && i.date && <span className="block text-xs text-warm-400 tabular-nums">{new Date(i.date).toLocaleDateString()}</span>}
                  </span>
                </span>
                {hasProcurementSpan ? (
                  <span className="text-xs text-warm-500 tabular-nums">
                    {i.covered_from && i.covered_until
                      ? `${reportCalendarDate(i.covered_from)} – ${reportCalendarDate(i.covered_until)}`
                      : i.date ? new Date(i.date).toLocaleDateString() : "—"}
                  </span>
                ) : null}
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
  const [filterYear, setFilterYear] = useState(new Date().getFullYear());
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
        <div className="grid max-w-sm grid-cols-2 gap-2">
          <MonthYearSelect month={coveredMonth ? Number(coveredMonth.slice(5, 7)) : null} year={filterYear} allowAllMonths monthAriaLabel="Report month" yearAriaLabel="Report year"
            onMonthChange={(month) => { setCoveredMonth(month === null ? "" : `${filterYear}-${String(month).padStart(2, "0")}`); setPage(1); }}
            onYearChange={(year) => { setFilterYear(year); if (coveredMonth) setCoveredMonth(`${year}-${coveredMonth.slice(5, 7)}`); setPage(1); }} />
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
        <table className="w-full min-w-[640px] whitespace-nowrap text-sm">
          <thead className="bg-warm-50 border-b border-warm-100">
            <tr>{["Report", "Created by", "Covers through", "Actions"].map((h) => (
              <th key={h} className="px-2 py-3 text-left text-xs font-bold text-warm-500 uppercase tracking-wider sm:px-3">{h}</th>
            ))}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {pagedReports.map((r) => (
              <tr key={r.id} className="hover:bg-warm-50/60">
                <td className="px-2 py-3 font-semibold text-warm-800 sm:px-3">{r.title}
                  {retentionEnabled && r.retention_expires_at && <span className="ml-2 text-xs font-normal text-warm-500">Expires on {reportDate(r.retention_expires_at)}</span>}
                </td>
                <td className="px-2 py-3 text-warm-600 sm:px-3">{r.created_by?.name ?? "Former user"}</td>
                <td className="px-2 py-3 text-warm-500 tabular-nums sm:px-3">
                  {r.report_covered_until ? (
                    <time
                      dateTime={r.report_covered_until}
                    >
                      {reportCalendarDate(r.report_covered_until)}
                    </time>
                  ) : "—"}
                </td>
                <td className="px-2 py-3 sm:px-3">
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
  const [brandingView, setBrandingView] = useState<"current" | "history">("current");
  const [brandingHistory, setBrandingHistory] = useState<{ data: ReportBrandingRevision[]; meta: PaginationMeta } | null>(null);
  const [brandingHistoryPage, setBrandingHistoryPage] = useState(1);
  const [selectedBrandingRevision, setSelectedBrandingRevision] = useState<ReportBrandingRevision | null>(null);
  const [loadingBrandingHistory, setLoadingBrandingHistory] = useState(false);
  const [historyTemplateId, setHistoryTemplateId] = useState<string | null>(null);
  const [templateHistory, setTemplateHistory] = useState<{ data: ReportTemplateRevision[]; meta: PaginationMeta } | null>(null);
  const [templateHistoryPage, setTemplateHistoryPage] = useState(1);
  const [selectedTemplateRevision, setSelectedTemplateRevision] = useState<ReportTemplateRevision | null>(null);
  const [loadingTemplateHistory, setLoadingTemplateHistory] = useState(false);
  const [savingB, setSavingB] = useState(false);
  const [savingT, setSavingT] = useState<string | null>(null);

  const load = useCallback(() => {
    (apiPrefix === "admin" ? getAdminBranding() : getBranding())
      .then((value) => { setBranding(value); setBrandingDraft(value); }).catch(() => {});
    listTemplates(apiPrefix).then(setTemplates).catch(() => {});
  }, [apiPrefix]);
  useEffect(() => { load(); }, [load]);

  const setB = (p: Partial<Branding>) => setBrandingDraft((b) => (b ? { ...b, ...p } : b));

  async function showBrandingHistory(page = 1) {
    setBrandingView("history");
    setSelectedBrandingRevision(null);
    setBrandingHistoryPage(page);
    setLoadingBrandingHistory(true);
    try {
      setBrandingHistory(await listBrandingHistory(apiPrefix, page));
    } catch (err) {
      onFlash(false, err instanceof Error ? err.message : "History could not be loaded.");
    } finally { setLoadingBrandingHistory(false); }
  }

  async function showTemplateHistory(id: string, page = 1) {
    setHistoryTemplateId(id);
    setEditingTemplateId(null);
    setTemplateDraft(null);
    setSelectedTemplateRevision(null);
    setTemplateHistoryPage(page);
    setLoadingTemplateHistory(true);
    try {
      setTemplateHistory(await listTemplateHistory(apiPrefix, id, page));
    } catch (err) {
      onFlash(false, err instanceof Error ? err.message : "History could not be loaded.");
    } finally { setLoadingTemplateHistory(false); }
  }

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
          {brandingView === "history" ? (
            <Button variant="secondary" onClick={() => { setBrandingView("current"); setSelectedBrandingRevision(null); }} className="!w-auto !shrink-0 !py-1.5 !px-3.5 text-sm">Back to current</Button>
          ) : !editingBranding && <div className="flex shrink-0 gap-2">
            <Button variant="secondary" onClick={() => showBrandingHistory()} className="!w-auto !py-1.5 !px-3.5 text-sm">History</Button>
            <Button variant="secondary" onClick={() => { setBrandingDraft(branding); setEditingBranding(true); }} className="!w-auto !py-1.5 !px-3.5 text-sm">Edit</Button>
          </div>}
        </div>
        {brandingView === "history" ? (
          selectedBrandingRevision ? <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="text-xs text-warm-500">Saved {reportDate(selectedBrandingRevision.created_at)} · {selectedBrandingRevision.editor?.name ?? (selectedBrandingRevision.origin === "baseline" ? "Initial tracked version" : "System")}</p>
              <Button variant="secondary" onClick={() => setSelectedBrandingRevision(null)} className="!w-auto !py-1.5 !px-3.5 text-sm">Back to history</Button>
            </div>
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {[["Hospital name", selectedBrandingRevision.hospital_name], ["Address", selectedBrandingRevision.address], ["Accreditation", selectedBrandingRevision.accreditation], ["Service name", selectedBrandingRevision.service_name], ["Province", selectedBrandingRevision.province], ["LGU", selectedBrandingRevision.lgu]].map(([label, value]) => <div key={label}><dt className={lbl}>{label}</dt><dd className="break-words text-sm text-warm-800">{value || "—"}</dd></div>)}
              <div><dt className={lbl}>Left logo</dt><dd>{selectedBrandingRevision.logo_left_path ? <Image src={brandingHistoryLogoUrl(apiPrefix, selectedBrandingRevision.id, "left")} alt="Historical left report logo" width={72} height={72} unoptimized className="h-18 w-18 rounded-lg border border-warm-200 bg-white object-contain p-1" /> : <span className="text-sm text-warm-800">Not set</span>}</dd></div>
              <div><dt className={lbl}>Right logo</dt><dd>{selectedBrandingRevision.logo_right_path ? <Image src={brandingHistoryLogoUrl(apiPrefix, selectedBrandingRevision.id, "right")} alt="Historical right report logo" width={72} height={72} unoptimized className="h-18 w-18 rounded-lg border border-warm-200 bg-white object-contain p-1" /> : <span className="text-sm text-warm-800">Not set</span>}</dd></div>
            </dl>
          </div> : loadingBrandingHistory ? <p className="text-sm text-warm-500">Loading history…</p> : brandingHistory && (
            <RevisionHistoryList title="Branding edits" items={brandingHistory.data} page={brandingHistoryPage} meta={brandingHistory.meta} onPageChange={(next) => showBrandingHistory(next)} onSelect={(item) => setSelectedBrandingRevision(brandingHistory.data.find((revision) => revision.id === item.id) ?? null)} />
          )
        ) : !editingBranding ? (
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
        {templates.filter((template) => !isClinicalAutoFilledSignatory(template)).map((saved) => {
          const t = editingTemplateId === saved.id && templateDraft ? templateDraft : saved;
          const viewingHistory = historyTemplateId === saved.id;
          const displayed = viewingHistory && selectedTemplateRevision ? selectedTemplateRevision : t;
          const templateName = displayed.name;
          return <Card key={t.id} padded className="space-y-3">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <h3 className="text-base font-bold text-warm-800">{templateName}</h3>
                {displayed.description && <p className="text-xs text-warm-500">{displayed.description}</p>}
              </div>
              {viewingHistory ? (
                <Button variant="secondary" onClick={() => { setHistoryTemplateId(null); setSelectedTemplateRevision(null); }} className="!w-auto shrink-0 !py-1.5 !px-3.5 text-sm">Back to current</Button>
              ) : editingTemplateId !== t.id && <div className="flex shrink-0 gap-2">
                <Button variant="secondary" onClick={() => showTemplateHistory(saved.id)} className="!w-auto !py-1.5 !px-3.5 text-sm">History</Button>
                <Button variant="secondary" onClick={() => { setTemplateDraft({ ...saved, signatories: (saved.signatories ?? []).map((s) => ({ ...s })) }); setEditingTemplateId(saved.id); }} className="!w-auto shrink-0 !py-1.5 !px-3.5 text-sm">Edit</Button>
              </div>}
            </div>
            {viewingHistory && !selectedTemplateRevision ? (
              loadingTemplateHistory ? <p className="text-sm text-warm-500">Loading history…</p> : templateHistory && (
                <RevisionHistoryList title="Template edits" items={templateHistory.data} page={templateHistoryPage} meta={templateHistory.meta} onPageChange={(next) => showTemplateHistory(saved.id, next)} onSelect={(item) => setSelectedTemplateRevision(templateHistory.data.find((revision) => revision.id === item.id) ?? null)} />
              )
            ) : viewingHistory && selectedTemplateRevision ? <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-warm-500">Saved {reportDate(selectedTemplateRevision.created_at)} · {selectedTemplateRevision.editor?.name ?? (selectedTemplateRevision.origin === "baseline" ? "Initial tracked version" : "System")}</p>
                <Button variant="secondary" onClick={() => setSelectedTemplateRevision(null)} className="!w-auto !py-1.5 !px-3.5 text-sm">Back to history</Button>
              </div>
              {selectedTemplateRevision.signatories.length === 0 ? <p className="text-xs text-warm-400">No signatory block.</p> : (
                <div className="space-y-2">
                  {selectedTemplateRevision.signatories.map((s, i) => <div key={`${s.role}-${i}`} className="grid grid-cols-1 gap-1 sm:grid-cols-[90px_1fr_1fr] sm:gap-2">
                    <span className="text-xs font-bold text-warm-400 uppercase sm:truncate" title={s.label}>{s.label}</span>
                    <span className="break-words text-sm text-warm-800">{s.name || "—"}</span>
                    <span className="break-words text-sm text-warm-600">{s.title || "—"}</span>
                  </div>)}
                </div>
              )}
            </div> : (t.signatories ?? []).length === 0 ? (
              <p className="text-xs text-warm-400">No signatory block.</p>
            ) : (
              <div className="space-y-2">
                {(t.signatories ?? []).map((s, i) => (
                  <div key={`${s.role}-${i}`} className="grid grid-cols-1 gap-1 sm:grid-cols-[90px_1fr_1fr] sm:gap-2 sm:items-center">
                    <span className="text-xs font-bold text-warm-400 uppercase sm:truncate" title={s.label}>{s.label}</span>
                    {editingTemplateId === t.id ? <><input value={s.name ?? ""} onChange={(e) => editSig(t.id, i, "name", e.target.value)} className={`${inp} !py-1.5`} /><input value={s.title ?? ""} onChange={(e) => editSig(t.id, i, "title", e.target.value)} className={`${inp} !py-1.5`} /></> : <><span className="text-sm text-warm-800">{s.name || "—"}</span><span className="text-sm text-warm-600">{s.title || "—"}</span></>}
                  </div>
                ))}
              </div>
            )}
            {editingTemplateId === t.id && <div className="flex gap-2"><Button variant="primary" onClick={() => saveT(t)} loading={savingT === t.id} className="!w-auto !py-1.5 !px-3.5 text-sm"><Save className="h-3.5 w-3.5" /> Save</Button><Button variant="secondary" onClick={() => { setTemplateDraft(null); setEditingTemplateId(null); }} className="!w-auto !py-1.5 !px-3.5 text-sm">Cancel</Button></div>}
          </Card>;
        })}
      </div>
    </div>
  );
}
