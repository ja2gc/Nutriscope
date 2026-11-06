// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import { ReportsBrowser } from './ReportsBrowser';
import { getBranding, listInstances, listTemplates, prepareReport, saveTemplate } from '@/services/reportService';

vi.mock('@/services/reportService', () => ({ getBranding: vi.fn(), listInstances: vi.fn(), listTemplates: vi.fn(), prepareReport: vi.fn(), saveTemplate: vi.fn() }));
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

test('browsing saved PPAs preserves frozen reports without a layout update button', async () => {
  const instance = { key: 'po-1', label: 'PPA', params: { purchase_order_id: 'po-1' }, date: '2026-11-06' };
  vi.mocked(listInstances).mockResolvedValue({ data: { axis: 'entity', instances: [instance] }, meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
  vi.mocked(prepareReport).mockResolvedValue({ id: 'existing-report' } as never);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<ReportsBrowser catalog={[{ type: 'program_project_activity', name: 'PPA', desc: 'Report', icon: () => null, group: 'Food Service' }]} apiPrefix="rnd" />));
  expect(prepareReport).not.toHaveBeenCalled();
  const update = [...container.querySelectorAll('button')].find(button => button.textContent === 'Update existing reports');
  expect(update).toBeUndefined();
  await act(async () => root.unmount()); container.remove(); vi.clearAllMocks();
});

test('AIR names and titles stay editable and the procurement-pack card is omitted', async () => {
  const positions = ['OIC-PGSO', 'Administrative Officer V', 'Nutritionist-Dietitian II/End User', 'Chief of Hospital II'];
  const template = { id: 'air-template', type: 'inspection_report', name: 'Acceptance & Inspection Report', description: '', signatories: ['inspected_by', 'verified_by', 'conforme', 'approved_by'].map((role, index) => ({ role, label: `${role}:`, name: '', title: positions[index] })) };
  vi.mocked(listInstances).mockResolvedValue({ data: { axis: 'entity', instances: [] }, meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
  vi.mocked(getBranding).mockResolvedValue({} as never);
  vi.mocked(listTemplates).mockResolvedValue([template, { id: 'pack-template', type: 'procurement_pack', name: 'Procurement Pack', description: '', signatories: [] }]);
  vi.mocked(saveTemplate).mockResolvedValue(template);
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<ReportsBrowser catalog={[{ type: 'program_project_activity', name: 'PPA', desc: '', icon: () => null, group: 'Food Service' }]} apiPrefix="rnd" />));
  await act(async () => [...container.querySelectorAll('button')].find(button => button.textContent === 'Template Edit')!.click());
  const heading = [...container.querySelectorAll('h3')].find(element => element.textContent === template.name)!;
  expect([...container.querySelectorAll('h3')].some(element => element.textContent === 'Procurement Pack')).toBe(false);
  const card = heading.closest('[data-slot="card"]') ?? heading.parentElement!.parentElement!.parentElement!;
  await act(async () => [...card.querySelectorAll('button')].find(button => button.textContent === 'Edit')!.click());
  expect(card.querySelectorAll('input')).toHaveLength(8);
  expect([...card.querySelectorAll('span')].filter(span => span.textContent === 'Name')).toHaveLength(1);
  expect([...card.querySelectorAll('span')].filter(span => span.textContent === 'Position')).toHaveLength(1);
  for (const position of positions) expect([...card.querySelectorAll('input')].some(input => input.value === position)).toBe(true);
  expect(card.textContent).not.toContain('inspected_by:');
  expect(card.textContent).not.toContain('verified_by:');
  expect(card.textContent).not.toContain('approved_by:');
  expect(card.textContent).toContain('Conforme:');
  await act(async () => [...card.querySelectorAll('button')].find(button => button.textContent?.trim() === 'Save')!.click());
  expect(saveTemplate).toHaveBeenCalledWith(template.id, { signatories: template.signatories }, 'rnd');
  await act(async () => root.unmount()); container.remove(); vi.clearAllMocks();
});
