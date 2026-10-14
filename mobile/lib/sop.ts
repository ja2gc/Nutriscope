import api from './api';
import { MOBILE_PAGE_SIZE, type PaginatedResponse } from './pagination';

export interface Sop {
  id: number;
  title: string;
  body: string;
  author?: { id: number; name: string; role: string } | null;
  created_at: string;
}

export async function fetchSop(): Promise<Sop | null> {
  const res = await api.get<{ data: Sop | null }>('/api/sop');
  return res.data.data;
}

export async function fetchSopHistory(page: number): Promise<PaginatedResponse<Sop>> {
  const res = await api.get<PaginatedResponse<Sop>>('/api/sop/history', { params: { page, per_page: MOBILE_PAGE_SIZE } });
  return res.data;
}
