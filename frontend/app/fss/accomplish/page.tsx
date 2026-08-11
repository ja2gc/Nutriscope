import { FSS_CATALOG, ReportsBrowser } from "@/components/reports/ReportsBrowser";

export default function FssAccomplishPage() {
  return <ReportsBrowser catalog={FSS_CATALOG} apiPrefix="fss" />;
}
