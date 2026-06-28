"use client";

import { ReportsBrowser, ADMIN_CATALOG } from "@/components/reports/ReportsBrowser";

/** Admin Reports: RND parity except patient-specific report types. */
export default function AdminReportsPage() {
  return <ReportsBrowser catalog={ADMIN_CATALOG} apiPrefix="admin" />;
}
