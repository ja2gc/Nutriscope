"use client";

import { Suspense, useMemo, useState } from "react";
import { Activity, AlertTriangle, RefreshCw, Shield } from "lucide-react";
import { AuditEventDrawer } from "@/components/audit/AuditEventDrawer";
import { AuditEventTable } from "@/components/audit/AuditEventTable";
import { AuditRetentionControl } from "@/components/audit/AuditRetentionControl";
import { AuditFilters, type AuditFilterState } from "@/components/audit/AuditFilters";
import { useAuditEventList } from "@/components/audit/useAuditEventList";
import { useAuditUrlState } from "@/components/audit/useAuditUrlState";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Pagination } from "@/components/ui/Pagination";
import { Tabs, type TabItem } from "@/components/ui/Tabs";
import {
  AuditLogServiceError,
  updateAuditRetention,
  type ListAuditLogsParams,
} from "@/services/auditLogService";
import type { AuditEventDto, AuditModule } from "@/types/audit";

const MODULE_TABS: TabItem<"all" | AuditModule>[] = [
  { key: "all", label: "All Activity" },
  { key: "security_administration", label: "Security & Administration" },
  { key: "nutrition_care", label: "Nutrition Care" },
  { key: "food_service_operations", label: "Food Service Operations" },
  { key: "reports", label: "Reports" },
];

function StatusPanel({
  title,
  message,
  retry,
}: {
  title: string;
  message: string;
  retry?: () => void;
}) {
  return (
    <EmptyState
      icon={<AlertTriangle className="h-7 w-7" />}
      title={title}
      message={message}
      action={retry ? <Button variant="secondary" onClick={retry}>Retry</Button> : undefined}
    />
  );
}

function AuditLogsContent() {
  const [selected, setSelected] = useState<AuditEventDto | null>(null);
  const { filters, page, updateFilters: replaceFilters, setPage } = useAuditUrlState();

  const requestParams = useMemo<ListAuditLogsParams>(() => ({
    ...filters,
    page,
    per_page: 10,
  }), [filters, page]);
  const { events, meta, loading, loaded, error, reload } = useAuditEventList(requestParams);

  const tabs = useMemo(() => MODULE_TABS.map((tab) => ({
    ...tab,
    label: `${tab.label} (${meta.filters.module_counts[tab.key] || 0})`,
  })), [meta.filters.module_counts]);

  function updateFilters(next: AuditFilterState) {
    if (next.module && next.action && !(meta.filters.module_actions[next.module] || []).includes(next.action)) {
      next = { ...next, action: undefined };
    }
    replaceFilters(next);
  }

  const hasFilters = Object.values(filters).some(Boolean);
  const errorStatus = error instanceof AuditLogServiceError ? error.status : null;

  return (
    <div className="space-y-6 font-sans">
      <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
          <p className="text-sm font-semibold text-warm-500">Admin / Audit logs</p>
          <h1 className="mt-1 flex items-center gap-2 text-2xl font-extrabold tracking-tight text-warm-900">
            <Shield className="h-6 w-6 text-brand-green-600" />
            Audit oversight
          </h1>
          <p className="mt-1 max-w-2xl text-sm leading-relaxed text-warm-600">
            Review security, clinical, and operational activity through privacy-safe event summaries.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" loading={loading} onClick={() => void reload()}>
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>
      </header>

      {!loaded && loading ? (
        <Card className="p-12 text-center" role="status">
          <RefreshCw className="mx-auto h-6 w-6 animate-spin text-brand-green-600" />
          <p className="mt-3 text-sm font-semibold text-warm-600">Loading audit events</p>
        </Card>
      ) : errorStatus === 401 ? (
        <StatusPanel title="Sign in required" message="Sign in again to review audit events." />
      ) : errorStatus === 403 ? (
        <StatusPanel title="Access denied" message="Your account does not have permission to review audit events." />
      ) : error ? (
        <StatusPanel title="Unable to load audit events" message="The audit service could not be reached. Try again." retry={() => void reload()} />
      ) : (
        <>
          <AuditRetentionControl retention={meta.retention} onUpdate={updateAuditRetention} />

          <Card className="overflow-hidden">
            <Tabs
              items={tabs}
              value={filters.module || "all"}
              onChange={(module) => updateFilters({
                ...filters,
                module: module === "all" ? undefined : module,
                subfilter: undefined,
              })}
              className="overflow-x-auto px-1"
            />
          </Card>

          <AuditFilters
            metadata={meta.filters}
            value={filters}
            onChange={updateFilters}
            onClear={() => updateFilters({})}
          />

          {events.length === 0 ? (
            <EmptyState
              icon={<Activity className="h-7 w-7" />}
              title={hasFilters ? "No matching audit events" : "No audit events yet"}
              message={hasFilters
                ? "No events match these filters. Clear one or more filters and try again."
                : "Audited activity will appear here when events are recorded."}
              action={hasFilters ? <Button variant="secondary" onClick={() => updateFilters({})}>Clear filters</Button> : undefined}
            />
          ) : (
            <Card className="overflow-hidden">
              <AuditEventTable events={events} onSelect={setSelected} />
            </Card>
          )}
          <Pagination meta={meta} page={page} onPageChange={setPage} />
        </>
      )}

      {selected && <AuditEventDrawer event={selected} onClose={() => setSelected(null)} />}
    </div>
  );
}

export default function AuditLogsPage() {
  return (
    <Suspense fallback={<div role="status" className="p-8 text-sm text-warm-600">Loading audit events</div>}>
      <AuditLogsContent />
    </Suspense>
  );
}
