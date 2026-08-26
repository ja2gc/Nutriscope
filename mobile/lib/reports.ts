import api from './api';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { getToken } from './auth';
import { MOBILE_PAGE_SIZE, PaginatedResponse } from './pagination';
import { absoluteApiUrl, reportDownloadPath } from './mobileContracts';

export interface ReportStaffSheet {
  user: { id: number | null; name: string | null; role: string | null };
  task_rows: Record<string, Record<string, string | number>>;
}

export interface AccomplishmentSnapshot {
  from: string;
  to: string;
  period_label: string;
  days: string[];
  tasks: Record<string, string>;
  numeric_task: string;
  daily_population: Record<string, number>;
  staff_sheets: ReportStaffSheet[];
}

export interface Report {
  id: string;
  user_id: number;
  title: string;
  type: string;
  status: string;
  generated_at: string | null;
  created_at: string;
  snapshot: { accomplishment?: AccomplishmentSnapshot } | null;
  parameters: Record<string, unknown> | null;
}

/** FSS sees only their own accomplishment reports (backend-scoped). */
export async function listReports(page: number, search = ''): Promise<PaginatedResponse<Report>> {
  const res = await api.get<PaginatedResponse<Report>>('/api/fss/reports', {
    params: { page, per_page: MOBILE_PAGE_SIZE, search: search || undefined },
  });
  return res.data;
}

export async function getReport(id: string): Promise<Report> {
  const res = await api.get<{ data: Report }>(`/api/fss/reports/${id}`);
  return res.data.data;
}

export async function downloadReportPdf(report: Report): Promise<void> {
  const start = String(report.parameters?.start ?? report.snapshot?.accomplishment?.from ?? '');
  const end = String(report.parameters?.end ?? report.snapshot?.accomplishment?.to ?? '');
  if (!start || !end) throw new Error('This report has no period information.');

  const token = await getToken();
  if (!token || !FileSystem.cacheDirectory) throw new Error('Please sign in again.');
  const destination = `${FileSystem.cacheDirectory}nutriscope-accomplishment-${start}-${end}.pdf`;
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/pdf' };
  let result = await FileSystem.downloadAsync(
    absoluteApiUrl(String(api.defaults.baseURL ?? ''), reportDownloadPath(report.id)),
    destination,
    { headers },
  );
  if (result.status === 409) {
    const refreshed = await api.post<{ data: Report }>(
      '/api/fss/reports/accomplishment_report/prepare',
      report.parameters ?? { start, end },
    );
    result = await FileSystem.downloadAsync(
      absoluteApiUrl(String(api.defaults.baseURL ?? ''), reportDownloadPath(refreshed.data.data.id)),
      destination,
      { headers },
    );
  }
  if (result.status !== 200) throw new Error('The report file could not be downloaded.');
  if (!await Sharing.isAvailableAsync()) throw new Error('No PDF viewer is available on this device.');
  await Sharing.shareAsync(result.uri, { mimeType: 'application/pdf', dialogTitle: 'Open or save accomplishment report' });
}
