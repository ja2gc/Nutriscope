"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, ArrowLeft, RefreshCw } from "lucide-react";
import { AuditHistoryView } from "@/components/audit/history/AuditHistoryView";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { AuditHistoryServiceError, getAuditHistory } from "@/services/auditHistoryService";
import type { AuditHistoryDto } from "@/types/auditHistory";

export default function AuditHistoryPage() {
  const params = useParams<{ id: string }>();
  const [history, setHistory] = useState<AuditHistoryDto | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<AuditHistoryServiceError | null>(null);

  const load = useCallback((signal?: AbortSignal) => {
    setLoading(true);
    setError(null);
    getAuditHistory(params.id, signal)
      .then(setHistory)
      .catch((caught) => {
        if (caught instanceof DOMException && caught.name === "AbortError") return;
        setError(caught instanceof AuditHistoryServiceError ? caught : new AuditHistoryServiceError("Audit history unavailable.", 500));
      })
      .finally(() => setLoading(false));
  }, [params.id]);

  useEffect(() => {
    const controller = new AbortController();
    load(controller.signal);
    return () => controller.abort();
  }, [load]);

  return (
    <main className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <div>
        <Link href="/admin/audit-logs" className="inline-flex min-h-11 items-center gap-2 font-bold text-brand-green-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500/30">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Back to audit logs
        </Link>
        <h1 className="mt-3 text-2xl font-extrabold text-warm-900">Historical audit record</h1>
      </div>

      {loading && <Card padded><p className="text-sm text-warm-600" role="status">Loading historical record…</p></Card>}
      {!loading && error && (
        <Card padded className="text-center">
          <AlertTriangle className="mx-auto h-8 w-8 text-amber-600" aria-hidden="true" />
          <h2 className="mt-3 text-lg font-extrabold text-warm-900">Audit history unavailable</h2>
          <p className="mt-1 text-sm text-warm-600">{error.status === 401 || error.status === 403 ? "You are not authorized to view this historical record." : error.message}</p>
          <Button className="mt-4" variant="secondary" onClick={() => load()}><RefreshCw className="h-4 w-4" /> Retry</Button>
        </Card>
      )}
      {!loading && !error && history && <AuditHistoryView history={history} />}
    </main>
  );
}
