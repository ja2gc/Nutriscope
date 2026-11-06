// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import { ReportsBrowser } from './ReportsBrowser';
import { listInstances, prepareReport } from '@/services/reportService';

vi.mock('@/services/reportService', () => ({ listInstances: vi.fn(), prepareReport: vi.fn() }));
vi.mock('@/components/reports/PatientsNcpTab', () => ({ PatientsNcpTab: () => null }));
vi.mock('@/components/reports/CensusPanel', () => ({ CensusPanel: () => null }));
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);

test.each(['program_project_activity', 'procurement_pack'] as const)('%s filters the server feed with All selected initially', async type => {
  vi.mocked(listInstances).mockResolvedValue({ data: { axis: 'entity', instances: [] }, meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  await act(async () => root.render(<ReportsBrowser catalog={[{ type, name: 'Purchasing report', desc: 'Report', icon: () => null, group: 'Food Service' }]} apiPrefix="rnd" />));
  const filter = container.querySelector<HTMLSelectElement>('[aria-label="Procurement type"]');
  expect(filter?.value).toBe('all');
  expect([...filter!.options].map(option => option.textContent)).toEqual(['All', 'Menu-suggested food', 'Manual food', 'Supplies']);
  await act(async () => { filter!.value = 'supplies'; filter!.dispatchEvent(new Event('change', { bubbles: true })); });
  expect(listInstances).toHaveBeenLastCalledWith(type, expect.objectContaining({ page: 1, procurement_kind: 'supplies' }), 'rnd');
  await act(async () => root.unmount()); container.remove(); vi.clearAllMocks();
});

test('updating saved PPAs is an explicit action and sends the layout refresh flag', async () => {
  const instance = { key: 'po-1', label: 'PPA', params: { purchase_order_id: 'po-1' }, date: '2026-11-06' };
  vi.mocked(listInstances).mockResolvedValue({ data: { axis: 'entity', instances: [instance] }, meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
  vi.mocked(prepareReport).mockResolvedValue({ id: 'existing-report' } as never);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<ReportsBrowser catalog={[{ type: 'program_project_activity', name: 'PPA', desc: 'Report', icon: () => null, group: 'Food Service' }]} apiPrefix="rnd" />));
  expect(prepareReport).not.toHaveBeenCalled();
  const update = [...container.querySelectorAll('button')].find(button => button.textContent === 'Update existing reports');
  expect(update).toBeDefined();
  await act(async () => update!.click());
  expect(listInstances).toHaveBeenLastCalledWith('program_project_activity', expect.objectContaining({ page: 1, per_page: 10 }), 'rnd');
  expect(prepareReport).toHaveBeenCalledWith('program_project_activity', { purchase_order_id: 'po-1', refresh_layout: 1 }, 'rnd');
  await act(async () => root.unmount()); container.remove(); vi.clearAllMocks();
});
