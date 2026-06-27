"use client";

import { ReportsBrowser, ADMIN_CATALOG } from "@/components/reports/ReportsBrowser";

/**
 * Admin Reports — restricted to the non-PHI report types in ADMIN_CATALOG:
 * demographic_census and procurement_pack. (Dietary Cashbook, Budget, and
 * Inventory reports were removed per the redesign.)
 * The backend enforces the same allowlist; 403 for any other type even via
 * direct API call.
 */
export default function AdminReportsPage() {
  return <ReportsBrowser catalog={ADMIN_CATALOG} apiPrefix="admin" />;
}
