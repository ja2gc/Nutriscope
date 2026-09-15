"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import { ReportPreview } from "@/components/ReportPreview";
import { Badge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { PageHeader } from "@/components/ui/PageHeader";
import { Pagination, type PaginationMeta } from "@/components/ui/Pagination";
import {
  listPatientNcpReports,
  prepareReport,
  reportDownloadUrl,
  reportViewUrl,
  type PatientNcpReportInstance,
} from "@/services/reportService";

export default function PatientNcpReportsPage() {
  const { patientId } = useParams<{ patientId: string }>();
  const [patient, setPatient] = useState<{ display_name: string; hospital_number: string | null; status: string } | null>(null);
  const [instances, setInstances] = useState<PatientNcpReportInstance[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [preview, setPreview] = useState<{ id: string; title: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await listPatientNcpReports(patientId, page);
      setPatient(result.patient);
      setInstances(result.data);
      setMeta(result.meta);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to load patient reports.");
    } finally {
      setLoading(false);
    }
  }, [page, patientId]);

  useEffect(() => { void load(); }, [load]);

  async function openReport(instance: PatientNcpReportInstance) {
    setBusy(instance.key);
    setError(null);
    try {
      const report = await prepareReport(instance.type, instance.params, "rnd");
      setPreview({ id: report.id, title: instance.label });
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Failed to prepare report.");
    } finally {
      setBusy(null);
    }
  }

  return (
    <div className="space-y-6 font-sans">
      <PageHeader
        crumbs={[["Home", "/dashboard"], ["Reports", "/reports?tab=patients"], [patient?.display_name ?? "Patient NCP"]]}
        title={patient?.display_name ?? "Patient NCP Reports"}
        subtitle={patient ? [patient.hospital_number, patient.status].filter(Boolean).join(" · ") : undefined}
      />

      <div><Link href="/reports?tab=patients" className="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Back to Patients NCP</Link></div>

      {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</div>}

      <Card className="overflow-hidden">
        <div className="px-5 py-4 border-b border-warm-100">
          <h2 className="text-base font-bold text-warm-800">NCP Reports</h2>
          <p className="text-xs text-warm-500 mt-0.5">NCP Summaries and Patient Menu Plans, newest first.</p>
        </div>
        {loading ? (
          <div className="py-14 flex items-center justify-center gap-2 text-sm text-warm-500"><Loader2 className="h-4 w-4 animate-spin" /> Loading reports…</div>
        ) : instances.length === 0 ? (
          <div className="py-12"><EmptyState title="No NCP reports" message="This patient has no NCP Summary or Patient Menu Plan yet." /></div>
        ) : (
          <ul className="divide-y divide-zinc-100">
            {instances.map((instance) => (
              <li key={instance.key} className="flex items-center justify-between gap-3 px-5 py-3.5">
                <button
                  type="button"
                  onClick={() => void openReport(instance)}
                  className="min-w-0 flex-1 text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 rounded-lg"
                >
                  <span className="block text-base font-semibold text-warm-900 truncate">{instance.label}</span>
                  <span className="block text-xs text-warm-500 mt-0.5">{instance.type === "ncp_summary" ? "NCP Summary" : "Patient Menu Plan"}</span>
                </button>
                <div className="flex items-center gap-2 shrink-0">
                  <Badge tone="zinc">{instance.status}</Badge>
                  {busy === instance.key && <Loader2 className="h-4 w-4 animate-spin text-emerald-600" />}
                </div>
              </li>
            ))}
          </ul>
        )}
        {!loading && <Pagination meta={meta} page={page} onPageChange={setPage} />}
      </Card>

      {preview && (
        <ReportPreview
          title={preview.title}
          src={reportViewUrl(preview.id, "rnd")}
          downloadUrl={reportDownloadUrl(preview.id, "rnd")}
          onClose={() => setPreview(null)}
        />
      )}
    </div>
  );
}
