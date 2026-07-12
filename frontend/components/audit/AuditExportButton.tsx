"use client";

import { useState } from "react";
import { Download, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  AuditLogServiceError,
  exportAuditLogs,
  type ListAuditLogsParams,
} from "@/services/auditLogService";

export function exportErrorMessage(error: unknown) {
  const status = error instanceof AuditLogServiceError ? error.status : 500;
  if (status === 401) return "Sign in again before exporting audit events.";
  if (status === 403) return "You do not have permission to export audit events.";
  if (status === 422) return "The selected filters are no longer valid. Refresh and try again.";
  return "Audit export is unavailable. Try again later.";
}

export function AuditExportButton({
  filters,
  requestExport = exportAuditLogs,
}: {
  filters: ListAuditLogsParams;
  requestExport?: (params: ListAuditLogsParams) => Promise<Blob>;
}) {
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function download() {
    setExporting(true);
    setError(null);
    try {
      const blob = await requestExport(filters);
      const objectUrl = URL.createObjectURL(blob);
      try {
        const anchor = document.createElement("a");
        anchor.href = objectUrl;
        anchor.download = "nutriscope-audit-events.csv";
        anchor.hidden = true;
        document.body.append(anchor);
        anchor.click();
        anchor.remove();
      } finally {
        URL.revokeObjectURL(objectUrl);
      }
    } catch (caught) {
      setError(exportErrorMessage(caught));
    } finally {
      setExporting(false);
    }
  }

  return (
    <div>
      <Button type="button" variant="secondary" disabled={exporting} aria-busy={exporting} onClick={() => void download()}>
        {exporting ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
        {exporting ? "Exporting…" : "Export CSV"}
      </Button>
      {error && <p role="alert" className="mt-2 max-w-xs text-sm text-red-700">{error}</p>}
    </div>
  );
}
