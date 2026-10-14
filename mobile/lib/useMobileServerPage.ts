import { useState } from 'react';
import type { InfiniteData } from '@tanstack/react-query';
import type { PaginatedResponse } from './pagination';

export function useMobileServerPage<T>(data: InfiniteData<PaginatedResponse<T>> | undefined, loadNext: () => Promise<{ isError: boolean }>) {
  const [requestedPage, setPage] = useState(1);
  const meta = data?.pages[0]?.meta;
  const pages = Math.max(1, meta?.last_page ?? 1);
  const page = Math.min(requestedPage, pages);
  const items = data?.pages.find((entry) => entry.meta.current_page === page)?.data ?? [];
  const next = async () => {
    if (page >= pages) return;
    if (!data?.pages.some((entry) => entry.meta.current_page === page + 1)) {
      const result = await loadNext();
      if (result.isError) return;
    }
    setPage(page + 1);
  };
  return { items, page, pages, total: meta?.total ?? 0, next, previous: () => setPage(Math.max(1, page - 1)), setPage };
}
