"use client";

import React, { useCallback, useEffect, useState } from "react";
import {
  FileText, RefreshCw, CalendarRange, CalendarDays, BookText, PackageCheck,
  Wallet, Boxes, Download, Trash2, Users, ClipboardList, Building2, Save,
  Sparkles, CheckCircle2, AlertTriangle, Loader2,
} from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Tabs } from "@/components/ui/Tabs";
import { Badge, BadgeTone } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState } from "@/components/ui/EmptyState";
import {
  ReportItem, ReportTemplate, Branding, Option, ReportParams,
  listReports, generateReport, generateAll, deleteReport, reportDownloadUrl,
  getBranding, saveBranding, listTemplates, saveTemplate,
  listMenuCycleOptions, listBudgetOptions, listShoppingListOptions, listPatientOptions,
} from "@/services/reportService";

const todayISO = () => new Date().toISOString().slice(0, 10);
const monthStartISO = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); };
const inp = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500";
const lbl = "block text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider mb-1";

const STATUS_TONE: Record<string, BadgeTone> = {
  completed: "emerald", generating: "amber", pending: "amber", queued: "amber", failed: "red",
};

type TabKey = "foodservice" | "ncp" | "templates";

// ── Report catalog (Food Service tab) ──────────────────────────────────────
type Need = "cycle" | "budget" | "list" | "range";
const FS_REPORTS: { type: string; name: string; desc: string; icon: React.ElementType; needs: Need[] }[] = [
  { type: "program_project_activity", name: "Program Project Activity", desc: "Weekly menu + total cost + headcount + inclusive dates, with signatures.", icon: CalendarRange, needs: ["cycle"] },
  { type: "menu_calendar", name: "Menu Calendar", desc: "Printable Mon→Sun grid to post in the kitchen.", icon: CalendarDays, needs: ["cycle"] },
  { type: "dietary_cash_book", name: "Dietary Cash Book", desc: "Cash disbursement ledger from received purchase orders.", icon: BookText, needs: ["range"] },
  { type: "procurement_pack", name: "Procurement Pack", desc: "AIR + Statement of Marketing + Summary of Marketing.", icon: PackageCheck, needs: ["list"] },
  { type: "budget_report", name: "Budget Report", desc: "Planned vs actual spend with variance over a range.", icon: Wallet, needs: ["budget"] },
  { type: "inventory_report", name: "Inventory Report", desc: "Stock levels, value, and low / out-of-stock items.", icon: Boxes, needs: [] },
];

function Flash({ flash }: { flash: { ok: boolean; msg: string } | null }) {
  if (!flash) return null;
  return (
    <div
      role="status"
      className={`flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-xl border w-fit ${
        flash.ok ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-red-50 text-red-700 border-red-200"
      }`}
    >
      {flash.ok ? <CheckCircle2 className="h-3.5 w-3.5" /> : <AlertTriangle className="h-3.5 w-3.5" />}
      {flash.msg}
    </div>
  );
}

export default function ReportsPage() {
  const [tab, setTab] = useState<TabKey>("foodservice");
  const [reports, setReports] = useState<ReportItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState<string | null>(null);
  const [flash, setFlash] = useState<{ ok: boolean; msg: string } | null>(null);

  // shared parameters
  const [start, setStart] = useState(monthStartISO());
  const [end, setEnd] = useState(todayISO());
  const [cycleId, setCycleId] = useState<number | "">("");
  const [budgetId, setBudgetId] = useState<number | "">("");
  const [listId, setListId] = useState<number | "">("");
  const [patientId, setPatientId] = useState<number | "">("");

  // selector options
  const [cycles, setCycles] = useState<Option[]>([]);
  const [budgets, setBudgets] = useState<Option[]>([]);
  const [lists, setLists] = useState<Option[]>([]);
  const [patients, setPatients] = useState<Option[]>([]);

  const flashFor = (ok: boolean, msg: string) => { setFlash({ ok, msg }); setTimeout(() => setFlash(null), 4000); };

  const loadReports = useCallback(async () => {
    setLoading(true);
    try { setReports(await listReports()); } finally { setLoading(false); }
  }, []);

  useEffect(() => { loadReports(); }, [loadReports]);
  useEffect(() => {
    listMenuCycleOptions().then(setCycles).catch(() => {});
    listBudgetOptions().then(setBudgets).catch(() => {});
    listShoppingListOptions().then(setLists).catch(() => {});
    listPatientOptions().then(setPatients).catch(() => {});
  }, []);

  const baseParams = (): ReportParams => ({
    start, end,
    menu_cycle_id: cycleId || undefined,
    budget_id: budgetId || undefined,
    shopping_list_id: listId || undefined,
  });

  async function run(type: string, params: ReportParams) {
    setBusy(type);
    try {
      await generateReport(type, params);
      flashFor(true, "Report generated.");
      await loadReports();
    } catch (e) {
      flashFor(false, e instanceof Error ? e.message : "Generation failed.");
    } finally { setBusy(null); }
  }

  async function runAll() {
    setBusy("__all__");
    try {
      const made = await generateAll(baseParams());
      flashFor(true, `Generated ${made.length} reports.`);
      await loadReports();
    } catch (e) {
      flashFor(false, e instanceof Error ? e.message : "Generation failed.");
    } finally { setBusy(null); }
  }

  function missing(needs: Need[]): Need | null {
    if (needs.includes("cycle") && !cycleId) return "cycle";
    if (needs.includes("budget") && !budgetId) return "budget";
    if (needs.includes("list") && !listId) return "list";
    return null;
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Home", "/dashboard"], ["Reports Center"]]}
        icon={<FileText className="h-5 w-5 text-emerald-600" />}
        title="Reports Center"
        subtitle="Generate the hospital's food-service and clinical forms as print-ready PDFs — branding and signatories pulled from your editable templates."
        actions={
          <button onClick={loadReports} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700">
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh
          </button>
        }
      />

      <Tabs<TabKey>
        value={tab}
        onChange={setTab}
        items={[
          { key: "foodservice", label: "Food Service", icon: <PackageCheck className="h-4 w-4" /> },
          { key: "ncp", label: "NCP", icon: <Users className="h-4 w-4" /> },
          { key: "templates", label: "Template Edit", icon: <Building2 className="h-4 w-4" /> },
        ]}
      />

      <Flash flash={flash} />

      {tab === "foodservice" && (
        <div className="space-y-5">
          {/* Parameters */}
          <Card padded className="space-y-4">
            <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Parameters</h2>
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
              <div><label className={lbl}>From</label><input type="date" value={start} onChange={(e) => setStart(e.target.value)} className={inp} /></div>
              <div><label className={lbl}>To</label><input type="date" value={end} onChange={(e) => setEnd(e.target.value)} className={inp} /></div>
              <div>
                <label className={lbl}>Menu cycle</label>
                <select value={cycleId} onChange={(e) => setCycleId(e.target.value ? Number(e.target.value) : "")} className={inp}>
                  <option value="">— select —</option>
                  {cycles.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                </select>
              </div>
              <div>
                <label className={lbl}>Budget</label>
                <select value={budgetId} onChange={(e) => setBudgetId(e.target.value ? Number(e.target.value) : "")} className={inp}>
                  <option value="">— select —</option>
                  {budgets.map((b) => <option key={b.id} value={b.id}>{b.label}</option>)}
                </select>
              </div>
              <div>
                <label className={lbl}>Shopping list</label>
                <select value={listId} onChange={(e) => setListId(e.target.value ? Number(e.target.value) : "")} className={inp}>
                  <option value="">— select —</option>
                  {lists.map((l) => <option key={l.id} value={l.id}>{l.label}</option>)}
                </select>
              </div>
            </div>
            <div className="flex justify-end">
              <Button variant="primary" onClick={runAll} loading={busy === "__all__"} className="!w-auto !py-2 !px-4 flex items-center gap-2">
                <Sparkles className="h-4 w-4" /> Generate All
              </Button>
            </div>
          </Card>

          {/* Report cards */}
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {FS_REPORTS.map((r) => {
              const Icon = r.icon;
              const miss = missing(r.needs);
              return (
                <Card key={r.type} padded className="flex flex-col gap-3">
                  <div className="flex items-start gap-3">
                    <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600"><Icon className="h-5 w-5" /></div>
                    <div>
                      <h3 className="text-sm font-bold text-zinc-800">{r.name}</h3>
                      <p className="text-[11px] text-zinc-500 mt-0.5 leading-snug">{r.desc}</p>
                    </div>
                  </div>
                  <div className="mt-auto flex items-center justify-between">
                    {miss ? <Badge tone="amber">needs {miss}</Badge> : <span />}
                    <Button
                      variant="secondary"
                      onClick={() => run(r.type, baseParams())}
                      disabled={!!miss}
                      loading={busy === r.type}
                      className="!w-auto !py-1.5 !px-3.5 text-xs"
                    >
                      Generate
                    </Button>
                  </div>
                </Card>
              );
            })}
          </div>

          <RecentReports reports={reports} loading={loading} onDelete={async (id) => { await deleteReport(id); loadReports(); }} />
        </div>
      )}

      {tab === "ncp" && (
        <div className="space-y-5">
          <div className="grid sm:grid-cols-2 gap-4">
            <Card padded className="space-y-3">
              <div className="flex items-center gap-2"><ClipboardList className="h-5 w-5 text-emerald-600" /><h3 className="text-sm font-bold text-zinc-800">Demographic / Research Census</h3></div>
              <p className="text-[11px] text-zinc-500">Patient counts by age group, sex, ward, diagnosis, nutritional status, and screening type over a date range.</p>
              <div className="grid grid-cols-2 gap-3">
                <div><label className={lbl}>From</label><input type="date" value={start} onChange={(e) => setStart(e.target.value)} className={inp} /></div>
                <div><label className={lbl}>To</label><input type="date" value={end} onChange={(e) => setEnd(e.target.value)} className={inp} /></div>
              </div>
              <Button variant="secondary" onClick={() => run("demographic_census", { start, end })} loading={busy === "demographic_census"} className="!w-auto !py-1.5 !px-3.5 text-xs">Generate Census</Button>
            </Card>

            <Card padded className="space-y-3">
              <div className="flex items-center gap-2"><Users className="h-5 w-5 text-emerald-600" /><h3 className="text-sm font-bold text-zinc-800">Patient Menu Plan</h3></div>
              <p className="text-[11px] text-zinc-500">A patient's ADIME meal plan as a weekly calendar PDF.</p>
              <div>
                <label className={lbl}>Patient</label>
                <select value={patientId} onChange={(e) => setPatientId(e.target.value ? Number(e.target.value) : "")} className={inp}>
                  <option value="">— select —</option>
                  {patients.map((p) => <option key={p.id} value={p.id}>{p.label}</option>)}
                </select>
              </div>
              <Button variant="secondary" onClick={() => run("patient_menu_plan", { patient_id: patientId || undefined })} disabled={!patientId} loading={busy === "patient_menu_plan"} className="!w-auto !py-1.5 !px-3.5 text-xs">Generate Menu Plan</Button>
            </Card>
          </div>

          <RecentReports reports={reports} loading={loading} onDelete={async (id) => { await deleteReport(id); loadReports(); }} />
        </div>
      )}

      {tab === "templates" && <TemplateEditor onFlash={flashFor} />}
    </div>
  );
}

// ── Recent reports table ────────────────────────────────────────────────────
function RecentReports({ reports, loading, onDelete }: { reports: ReportItem[]; loading: boolean; onDelete: (id: number) => void }) {
  return (
    <Card className="overflow-x-auto">
      <div className="px-5 py-3 border-b border-zinc-100"><h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Recent Reports</h2></div>
      {loading ? (
        <div className="py-12 text-center text-xs text-zinc-400 flex items-center justify-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>
      ) : reports.length === 0 ? (
        <div className="py-12 text-center text-xs text-zinc-400">No reports yet. Generate one above.</div>
      ) : (
        <table className="w-full text-xs">
          <thead className="bg-zinc-50 border-b border-zinc-100">
            <tr>{["Title", "Status", "Generated", ""].map((h) => <th key={h} className="px-4 py-3 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>)}</tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {reports.map((r) => (
              <tr key={r.id} className="hover:bg-zinc-50/60">
                <td className="px-4 py-3 font-semibold text-zinc-800">{r.title}</td>
                <td className="px-4 py-3"><Badge tone={STATUS_TONE[r.status] ?? "zinc"}>{r.status}</Badge></td>
                <td className="px-4 py-3 text-zinc-500">{r.generated_at ? new Date(r.generated_at).toLocaleString() : "—"}</td>
                <td className="px-4 py-3">
                  <div className="flex items-center justify-end gap-1">
                    {r.status === "completed" && r.file_path && (
                      <a href={reportDownloadUrl(r.id)} className="p-1.5 rounded-lg hover:bg-emerald-50 text-zinc-500 hover:text-emerald-600" aria-label={`Download ${r.title}`} title="Download PDF">
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
    </Card>
  );
}

// ── Template Edit tab ───────────────────────────────────────────────────────
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
        <p className="text-[11px] text-zinc-500 mb-4">Shared across every report header. The “prepared by” name auto-fills from the logged-in user; these are the fallbacks.</p>
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
