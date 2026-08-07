import api from './api';
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
