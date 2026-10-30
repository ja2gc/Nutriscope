// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import AdminSettingsPage from './page';
import SettingsPage from '../../(rnd)/settings/page';
import { getFoodServiceSetting } from '@/services/budgetService';

vi.mock('@/services/budgetService', () => ({ getFoodServiceSetting: vi.fn(), setFoodServiceSetting: vi.fn() }));

test.each([AdminSettingsPage, SettingsPage])('keeps preferences available while budget settings load', async (Page) => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  let resolve!: (value: Awaited<ReturnType<typeof getFoodServiceSetting>>) => void;
  vi.mocked(getFoodServiceSetting).mockReturnValue(new Promise((done) => { resolve = done; }));
  const container = document.createElement('div');
  document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<Page />));
    expect(container.querySelector('[role="status"][aria-busy="true"]')?.textContent).toContain('Loading budget setting…');
    expect(container.textContent).toContain('Appearance');
    expect(container.querySelectorAll('[role="switch"]')).toHaveLength(2);
    await act(async () => resolve({ per_head_day_limit: '150.00', updated_by: null, updated_at: null }));
    expect(container.querySelector('[aria-busy="true"]')).toBeNull();
    expect(container.querySelector<HTMLInputElement>('input[type="number"]')?.value).toBe('150.00');
  } finally {
    await act(async () => root.unmount());
    container.remove();
    vi.clearAllMocks();
  }
});
