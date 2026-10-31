import Link from "next/link";
import { EmptyState } from "@/components/ui/EmptyState";
import { NcpBreadcrumb } from "@/components/ncp/NcpBreadcrumb";

export function NcpNoPatientSelected({ step }: { step: string }) {
  return (
    <div className="space-y-6 font-sans">
      <NcpBreadcrumb step={step} />
      <EmptyState
        title="No Patient Selected"
        message="Select or create a patient to start or continue their nutrition care."
        className="w-full p-6! sm:p-12!"
        action={<Link href="/ncp/patients" className="inline-flex min-h-11 items-center justify-center rounded-lg bg-forest-900 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-forest-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Go to Patients</Link>}
      />
    </div>
  );
}
