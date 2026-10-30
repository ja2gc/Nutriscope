import React from "react";
import { LoaderCircle } from "lucide-react";

export function SectionLoading({ label, skeleton = false }: { label: string; skeleton?: boolean }) {
  if (skeleton) {
    return (
      <div role="status" aria-busy="true" className="space-y-3 py-4">
        <span className="sr-only">{label}</span>
        <div aria-hidden="true" className="space-y-3 motion-safe:animate-pulse">
          {[0, 1, 2].map(row => <div key={row} className="h-16 rounded-xl bg-warm-100" />)}
        </div>
      </div>
    );
  }

  return (
    <div role="status" className="flex items-center gap-2 py-4 text-sm text-warm-500">
      <LoaderCircle aria-hidden="true" className="h-4 w-4 motion-safe:animate-spin" />
      {label}
    </div>
  );
}
