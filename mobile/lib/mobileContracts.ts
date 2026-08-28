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

export interface MobileNotificationTargetInput {
  type?: string | null;
  source_module?: string | null;
  sourceId: string | number | null;
}

export function mobileNotificationTarget({ type, source_module, sourceId }: MobileNotificationTargetInput) {
  const kind = `${type ?? ''} ${source_module ?? ''}`.toLowerCase();

  if (sourceId && kind.includes('announcement')) {
    return { pathname: '/(tabs)/announcements', params: { announcementId: String(sourceId) } };
  }
  if (sourceId && (kind.includes('po') || kind.includes('purchase') || kind.includes('food_service'))) {
    return { pathname: '/(tabs)/procurement', params: { poId: String(sourceId) } };
  }
  if (kind.includes('report') || kind.includes('accomplishment')) {
    return { pathname: '/(tabs)/accomplishments', params: { section: 'reports' } };
  }

  return null;
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
