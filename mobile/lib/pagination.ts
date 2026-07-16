import type { InfiniteData } from '@tanstack/react-query';

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export const MOBILE_PAGE_SIZE = 5;

export function getNextPageParam<T>(page: PaginatedResponse<T>): number | undefined {
  return page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined;
}

export function flattenUniquePages<T extends { id: string | number }>(pages: PaginatedResponse<T>[] | undefined): T[] {
  const seen = new Set<string | number>();
  return (pages ?? []).flatMap((page) => page.data.filter((item) => {
    if (seen.has(item.id)) return false;
    seen.add(item.id);
    return true;
  }));
}

export function mapPageItems<T>(
  data: InfiniteData<PaginatedResponse<T>>,
  mapper: (item: T) => T,
): InfiniteData<PaginatedResponse<T>> {
  return { ...data, pages: data.pages.map((page) => ({ ...page, data: page.data.map(mapper) })) };
}
