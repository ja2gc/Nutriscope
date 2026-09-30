import type { PaginationMeta } from "@/components/ui/Pagination";

const AI_DRAFTS_PER_PAGE = 2;

export function paginateAiDrafts<T>(drafts: T[], requestedPage: number): { items: T[]; meta: PaginationMeta } {
  const lastPage = Math.max(1, Math.ceil(drafts.length / AI_DRAFTS_PER_PAGE));
  const currentPage = Math.min(Math.max(1, requestedPage), lastPage);
  const start = (currentPage - 1) * AI_DRAFTS_PER_PAGE;

  return {
    items: drafts.slice(start, start + AI_DRAFTS_PER_PAGE),
    meta: {
      current_page: currentPage,
      per_page: AI_DRAFTS_PER_PAGE,
      total: drafts.length,
      last_page: lastPage,
    },
  };
}
