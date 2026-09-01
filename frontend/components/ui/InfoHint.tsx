"use client";

import type { ReactNode } from "react";
import { CircleHelp, X } from "lucide-react";
import { Popover, PopoverClose, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

type InfoHintProps = {
  label: string;
  title?: string;
  children: ReactNode;
};

export function InfoHint({ label, title, children }: InfoHintProps) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={label}
          className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-warm-400 transition-colors hover:bg-warm-100 hover:text-warm-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
        >
          <CircleHelp className="h-4 w-4" aria-hidden="true" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="start" sideOffset={6} className="w-[min(20rem,calc(100vw-2rem))] p-0">
        <div className="flex items-center justify-between gap-3 border-b border-warm-100 px-4 py-3">
          <p className="text-sm font-bold text-warm-900">{title ?? label}</p>
          <PopoverClose asChild>
            <button
              type="button"
              aria-label="Close help"
              className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-warm-400 hover:bg-warm-100 hover:text-warm-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
            >
              <X className="h-4 w-4" aria-hidden="true" />
            </button>
          </PopoverClose>
        </div>
        <div className="px-4 py-3 text-sm leading-6 text-warm-600">{children}</div>
      </PopoverContent>
    </Popover>
  );
}
