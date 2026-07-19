"use client";

import { ReportsBrowser, FSS_CATALOG, FULL_CATALOG } from "@/components/reports/ReportsBrowser";
import { useAuth } from "@/contexts/AuthContext";

export default function ReportsPage() {
  const { user } = useAuth();
  const isFss = user?.role === "FSS";

  return <ReportsBrowser catalog={isFss ? FSS_CATALOG : FULL_CATALOG} apiPrefix={isFss ? "fss" : "rnd"} />;
}
