import api from './api';
import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { getToken } from './auth';
import { MOBILE_PAGE_SIZE, PaginatedResponse } from './pagination';

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
    params: { page, per_page: MOBILE_PAGE_SIZE, status: 'archived', search: search || undefined },
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

  const prepared = await api.post<{ data: Report }>('/api/fss/reports/accomplishment_report/prepare', { start, end });
  const token = await getToken();
  if (!token || !FileSystem.cacheDirectory) throw new Error('Please sign in again.');
  const base = String(api.defaults.baseURL ?? '').replace(/\/$/, '');
  const destination = `${FileSystem.cacheDirectory}nutriscope-accomplishment-${start}-${end}.pdf`;
  const result = await FileSystem.downloadAsync(
    `${base}/api/fss/reports/${prepared.data.data.id}/download`,
    destination,
    { headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf' } },
  );
  if (result.status !== 200) throw new Error('The report file could not be downloaded.');
  if (!await Sharing.isAvailableAsync()) throw new Error('No PDF viewer is available on this device.');
  await Sharing.shareAsync(result.uri, { mimeType: 'application/pdf', dialogTitle: 'Open or save accomplishment report' });
}
