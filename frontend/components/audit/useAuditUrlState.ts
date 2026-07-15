"use client";

import { useCallback, useMemo } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import type { AuditFilterState } from "./AuditFilters";
import type { AuditModule, AuditOutcome, AuditSeverity } from "@/types/audit";

function parseFilters(searchParams: URLSearchParams): AuditFilterState {
  return {
    module: (searchParams.get("module") || undefined) as AuditModule | undefined,
    subfilter: searchParams.get("subfilter") || undefined,
    action: searchParams.get("action") || undefined,
    actor_id: searchParams.get("actor_id") || undefined,
    outcome: (searchParams.get("outcome") || undefined) as AuditOutcome | undefined,
    severity: (searchParams.get("severity") || undefined) as AuditSeverity | undefined,
    start: searchParams.get("start") || undefined,
    end: searchParams.get("end") || undefined,
  };
}

export function auditSearchParams(filters: AuditFilterState, page = 1) {
  const searchParams = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value) searchParams.set(key, value);
  }
  if (page > 1) searchParams.set("page", String(page));
  return searchParams;
}

export function useAuditUrlState() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const snapshot = searchParams.toString();
  const filters = useMemo(() => parseFilters(new URLSearchParams(snapshot)), [snapshot]);
  const page = Math.max(1, Number.parseInt(searchParams.get("page") || "1", 10) || 1);

  const navigate = useCallback((nextFilters: AuditFilterState, nextPage: number) => {
    const query = auditSearchParams(nextFilters, nextPage).toString();
    router.replace(query ? `${pathname}?${query}` : pathname, { scroll: false });
  }, [pathname, router]);

  const updateFilters = useCallback((nextFilters: AuditFilterState) => {
    navigate(nextFilters, 1);
  }, [navigate]);

  const setPage = useCallback((nextPage: number) => {
    navigate(filters, nextPage);
  }, [filters, navigate]);

  return { filters, page, updateFilters, setPage };
}
