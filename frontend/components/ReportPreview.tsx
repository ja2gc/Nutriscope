"use client";

import React, { useEffect, useRef, useState } from "react";
import { X, Download, Archive, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/Button";

/**
 * Full-bleed PDF preview overlay — the *primary* way to look at a report (Spec 4
 * follow-on): the rendered form is shown inline; downloading is a secondary action.
 * Same-origin <iframe> against the inline render/view route.
 */
export function ReportPreview({
  title,
  src,
  downloadUrl,
  onArchive,
  archiving,
  onClose,
}: {
  title: string;
  /** Inline PDF URL (live render or archived view). */
  src: string;
  /** Explicit save URL (forces download). */
  downloadUrl: string;
  /** Shown only when the copy isn't already archived. */
  onArchive?: () => void;
  archiving?: boolean;
  onClose: () => void;
}) {
  const [loading, setLoading] = useState(true);
  const closeRef = useRef<HTMLButtonElement>(null);

  // Esc to close + lock body scroll while open (a11y: escape-routes).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", onKey);
    closeRef.current?.focus();
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => { document.removeEventListener("keydown", onKey); document.body.style.overflow = prev; };
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 z-[1000] flex items-center justify-center p-4 sm:p-6 bg-black/50"
      role="dialog"
      aria-modal="true"
      aria-label={`${title} preview`}
      onClick={onClose}
    >
      <div
        className="flex flex-col w-full max-w-4xl h-[88vh] bg-white rounded-2xl shadow-2xl overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between gap-3 px-5 py-3 border-b border-warm-100 shrink-0">
          <h2 className="text-base font-bold text-warm-800 truncate">{title}</h2>
          <div className="flex items-center gap-2 shrink-0">
            {onArchive && (
              <Button variant="secondary" onClick={onArchive} loading={archiving} className="!w-auto !py-1.5 !px-3 text-sm">
                <Archive className="h-3.5 w-3.5" /> Archive
              </Button>
            )}
            <a
              href={downloadUrl}
              download
              className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-lg border border-warm-200 text-warm-700 bg-white hover:bg-warm-50 transition-colors"
            >
              <Download className="h-3.5 w-3.5" /> Download
            </a>
            <button
              ref={closeRef}
              onClick={onClose}
              aria-label="Close preview"
              className="p-1.5 rounded-lg text-warm-400 hover:bg-warm-100 hover:text-warm-700 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Document */}
        <div className="relative flex-1 bg-warm-100">
          {loading && (
            <div className="absolute inset-0 flex items-center justify-center gap-2 text-sm text-warm-400">
              <Loader2 className="h-4 w-4 animate-spin" /> Rendering…
            </div>
          )}
          <iframe
            src={src}
            title={`${title} document`}
            className="w-full h-full border-0"
            onLoad={() => setLoading(false)}
          />
        </div>
      </div>
    </div>
  );
}
