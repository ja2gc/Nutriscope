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

export function paginateItems<T>(items: readonly T[], requestedPage: number) {
  const pages = Math.max(1, Math.ceil(items.length / MOBILE_PAGE_SIZE));
  const page = Math.min(Math.max(1, requestedPage), pages);
  return { items: items.slice((page - 1) * MOBILE_PAGE_SIZE, page * MOBILE_PAGE_SIZE), page, pages, total: items.length };
}

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
