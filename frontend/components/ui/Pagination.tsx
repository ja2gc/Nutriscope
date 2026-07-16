"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

interface PaginationProps {
  meta: PaginationMeta | null | undefined;
  page: number;
  onPageChange: (page: number) => void;
}

export function Pagination({ meta, page, onPageChange }: PaginationProps) {
  if (!meta) return null;

  return (
    <div className="px-5 py-3.5 border-t border-warm-100 flex items-center justify-between select-none">
      <span className="text-xs font-semibold text-warm-400">
        Page {meta.current_page} of {meta.last_page} · {meta.total} items
      </span>
      <div className="flex gap-1">
        <button
          onClick={() => onPageChange(page - 1)}
          disabled={page === 1}
          className="p-1.5 border border-warm-200 bg-white text-warm-600 rounded-lg hover:bg-warm-50 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-colors"
          aria-label="Previous page"
        >
          <ChevronLeft className="h-3.5 w-3.5" />
        </button>
        <button
          onClick={() => onPageChange(page + 1)}
          disabled={page === meta.last_page}
          className="p-1.5 border border-warm-200 bg-white text-warm-600 rounded-lg hover:bg-warm-50 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer transition-colors"
          aria-label="Next page"
        >
          <ChevronRight className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );
}
