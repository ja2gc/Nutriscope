"use client";

import { ReportsBrowser, ADMIN_CATALOG } from "@/components/reports/ReportsBrowser";

/**
 * Admin Reports — restricted to the three non-PHI report types:
 * demographic_census, budget_report, procurement_pack.
 * The backend enforces the same allowlist; 403 for any other type even via
 * direct API call.
 */
export default function AdminReportsPage() {
  return <ReportsBrowser catalog={ADMIN_CATALOG} apiPrefix="admin" />;
}
