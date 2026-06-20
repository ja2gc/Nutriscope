"use client";

import { ReportsBrowser, FULL_CATALOG } from "@/components/reports/ReportsBrowser";

export default function ReportsPage() {
  return <ReportsBrowser catalog={FULL_CATALOG} apiPrefix="rnd" />;
}
