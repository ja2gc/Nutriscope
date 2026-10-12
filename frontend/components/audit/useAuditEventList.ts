"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  listAuditLogs,
  type AuditLogListMeta,
  type ListAuditLogsParams,
} from "@/services/auditLogService";
import type { AuditEventDto } from "@/types/audit";

const emptyMeta: AuditLogListMeta = {
  current_page: 1,
  per_page: 10,
  total: 0,
  last_page: 1,
  filters: {
    modules: [],
    actions: [],
    module_counts: { all: 0, security_administration: 0, nutrition_care: 0, food_service_operations: 0 },
    report_contexts: {},
  },
  capabilities: {},
  retention: {
    enabled: false,
    source: "config",
    periods: { security: 365, clinical: 2190, operations: 1095, legacy: 90 },
  },
};

export function useAuditEventList(params: ListAuditLogsParams) {
  const [events, setEvents] = useState<AuditEventDto[]>([]);
  const [meta, setMeta] = useState<AuditLogListMeta>(emptyMeta);
  const [loading, setLoading] = useState(true);
  const [loaded, setLoaded] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const requestSequence = useRef(0);
  const activeRequest = useRef<AbortController | null>(null);
  const stableParams = useMemo<ListAuditLogsParams>(() => ({
    action: params.action,
    actor_id: params.actor_id,
    context: params.context,
    end: params.end,
    module: params.module,
    month: params.month,
    page: params.page,
    per_page: params.per_page,
    start: params.start,
  }), [
    params.action,
    params.actor_id,
    params.context,
    params.end,
    params.module,
    params.month,
    params.page,
    params.per_page,
    params.start,
  ]);

  const reload = useCallback(async () => {
    activeRequest.current?.abort();
    const controller = new AbortController();
    const sequence = ++requestSequence.current;
    activeRequest.current = controller;
    setLoading(true);
    setError(null);

    try {
      const response = await listAuditLogs(stableParams, { signal: controller.signal });
      if (sequence !== requestSequence.current) return;
      setEvents(response.data);
      setMeta(response.meta);
      setLoaded(true);
    } catch (caught) {
      if (sequence !== requestSequence.current || controller.signal.aborted) return;
      setError(caught instanceof Error ? caught : new Error("Unable to load audit events."));
    } finally {
      if (sequence === requestSequence.current) setLoading(false);
    }
  }, [stableParams]);

  useEffect(() => {
    void reload();
    return () => {
      requestSequence.current += 1;
      activeRequest.current?.abort();
    };
  }, [reload]);

  return { events, meta, loading, loaded, error, reload };
}
