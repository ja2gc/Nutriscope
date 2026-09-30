import Link from "next/link";
import { ChevronRight } from "lucide-react";

export function NcpBreadcrumb({ step }: { step: string }) {
  return (
    <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-sm font-semibold text-warm-400 select-none">
      <Link href="/dashboard" className="transition-colors hover:text-emerald-700">Home</Link>
      <ChevronRight className="h-3 w-3" aria-hidden="true" />
      <Link href="/ncp/patients" className="transition-colors hover:text-emerald-700">Nutrition Care</Link>
      <ChevronRight className="h-3 w-3" aria-hidden="true" />
      <span className="font-bold text-warm-700">{step}</span>
    </nav>
  );
}
