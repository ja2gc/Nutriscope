import React from "react";
import { LoaderCircle } from "lucide-react";

export function SectionLoading({ label }: { label: string }) {
  return (
    <div role="status" className="flex items-center gap-2 py-4 text-sm text-warm-500">
      <LoaderCircle aria-hidden="true" className="h-4 w-4 motion-safe:animate-spin" />
      {label}
    </div>
  );
}
