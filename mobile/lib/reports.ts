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

async function preparedReport(report: Report): Promise<Report> {
  const start = String(report.parameters?.start ?? report.snapshot?.accomplishment?.from ?? '');
  const end = String(report.parameters?.end ?? report.snapshot?.accomplishment?.to ?? '');
  if (!start || !end) throw new Error('This report has no period information.');
  const refreshed = await api.post<{ data: Report }>('/api/fss/reports/accomplishment_report/prepare', {
    ...(report.parameters ?? {}), start, end,
  });
  return refreshed.data.data;
}

async function fetchReportPdf(report: Report): Promise<{ uri: string; filename: string }> {
  const start = String(report.parameters?.start ?? report.snapshot?.accomplishment?.from ?? '');
  const end = String(report.parameters?.end ?? report.snapshot?.accomplishment?.to ?? '');
  if (!start || !end) throw new Error('This report has no period information.');

  const token = await getToken();
  if (!token || !FileSystem.cacheDirectory) throw new Error('Please sign in again.');
  const destination = `${FileSystem.cacheDirectory}nutriscope-accomplishment-${start}-${end}.pdf`;
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/pdf' };
  const current = await preparedReport(report);
  const result = await FileSystem.downloadAsync(
    absoluteApiUrl(String(api.defaults.baseURL ?? ''), reportDownloadPath(current.id)),
    destination,
    { headers },
  );
  if (result.status !== 200) throw new Error('The report file could not be downloaded.');
  return { uri: result.uri, filename: `nutriscope-accomplishment-${start}-${end}.pdf` };
}

export async function viewReportPdf(report: Report): Promise<void> {
  const result = await fetchReportPdf(report);
  if (!await Sharing.isAvailableAsync()) throw new Error('No PDF viewer is available on this device.');
  await Sharing.shareAsync(result.uri, { mimeType: 'application/pdf', dialogTitle: 'View PDF' });
}

export async function downloadReportPdf(report: Report): Promise<void> {
  const result = await fetchReportPdf(report);
  if (FileSystem.StorageAccessFramework?.requestDirectoryPermissionsAsync) {
    const access = await FileSystem.StorageAccessFramework.requestDirectoryPermissionsAsync();
    if (access.granted) {
      const target = await FileSystem.StorageAccessFramework.createFileAsync(access.directoryUri, result.filename, 'application/pdf');
      const base64 = await FileSystem.readAsStringAsync(result.uri, { encoding: FileSystem.EncodingType.Base64 });
      await FileSystem.writeAsStringAsync(target, base64, { encoding: FileSystem.EncodingType.Base64 });
      return;
    }
  }
  if (!await Sharing.isAvailableAsync()) throw new Error('Choose a folder or install a PDF viewer, then retry.');
  await Sharing.shareAsync(result.uri, { mimeType: 'application/pdf', dialogTitle: 'Save PDF' });
}
