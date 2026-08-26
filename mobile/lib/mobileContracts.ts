export interface PageMeta {
  current_page: number;
  last_page: number;
}

export interface Page<T> {
  data: T[];
  meta: PageMeta;
}

export function absoluteApiUrl(baseUrl: string, path: string): string {
  const origin = baseUrl.replace(/\/+$/, '').replace(/\/api$/, '');
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;

  return `${origin}${normalizedPath}`;
}

export function authenticatedImageSource(baseUrl: string, path: string, token: string | null) {
  return {
    uri: absoluteApiUrl(baseUrl, path),
    ...(token ? { headers: { Authorization: `Bearer ${token}` } } : {}),
  };
}

export function reportDownloadPath(reportId: string): string {
  return `/api/fss/reports/${encodeURIComponent(reportId)}/download`;
}

export function isValidServedPopulation(value: number): boolean {
  return Number.isInteger(value) && value > 0;
}

export async function collectAllPages<T>(fetchPage: (page: number) => Promise<Page<T>>): Promise<T[]> {
  const rows: T[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await fetchPage(page);
    rows.push(...response.data);
    lastPage = response.meta.last_page;
    page += 1;
  } while (page <= lastPage);

  return rows;
}
