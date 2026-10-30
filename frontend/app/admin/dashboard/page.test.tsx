// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import AdminDashboardPage from './page';
import { fetchDashboard } from '@/services/adminDashboardService';

vi.mock('@/services/adminDashboardService', () => ({ fetchDashboard: vi.fn() }));
vi.mock('@/services/auditLogService', () => ({ listAuditLogs: vi.fn().mockResolvedValue({ data: [] }) }));
vi.mock('@/services/aiUsageLimitService', () => ({ getAiUsageLimits: vi.fn().mockResolvedValue({}), saveAiUsageLimits: vi.fn() }));
vi.mock('@/services/currencyService', () => ({ fetchUsdToPhpRate: vi.fn().mockResolvedValue(56) }));
vi.mock('@/components/admin/AiUsageExplorer', () => ({ AiUsageExplorer: () => null }));

test('announces dashboard loading and clears the placeholder when the request fails', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  let reject!: (error: Error) => void;
  vi.mocked(fetchDashboard).mockReturnValue(new Promise((_, fail) => { reject = fail; }));
  const container = document.createElement('div');
  document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<AdminDashboardPage />));
    const pending = container.querySelector('[role="status"][aria-busy="true"]');
    expect(pending?.getAttribute('aria-label')).toBe('Loading dashboard…');
    expect(pending?.querySelector('button, input, a')).toBeNull();
    await act(async () => reject(new Error('Dashboard unavailable')));
    expect(container.querySelector('[aria-busy="true"]')).toBeNull();
    expect(container.textContent).toContain('Dashboard unavailable');
  } finally {
    await act(async () => root.unmount());
    container.remove();
    vi.clearAllMocks();
  }
});
