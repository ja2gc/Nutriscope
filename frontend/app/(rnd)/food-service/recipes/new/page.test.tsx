// @vitest-environment jsdom

import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, test, vi } from 'vitest';
import NewFSSRecipePage from './page';
import { listCatalog } from '@/services/fsCatalogService';

vi.mock('next/navigation', () => ({ useRouter: () => ({ push: vi.fn() }), useSearchParams: () => new URLSearchParams() }));
vi.mock('@/services/fsCatalogService', () => ({ listCatalog: vi.fn() }));

const rice = {
  id: 'rice', name: 'Rice', kind: 'ingredient' as const, category: 'grain', base_unit: 'kg',
  purchase_unit: 'sack', purchase_price: '250', units_per_purchase: '1', unit_cost: 250,
  default_supplier_id: null, vendor: null, include_in_generated_lists: true,
};

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

afterEach(() => { document.body.innerHTML = ''; vi.clearAllMocks(); });

test('loads inventory ingredients before typing and filters paginated results', async () => {
  vi.mocked(listCatalog).mockResolvedValue({ data: [rice], meta: { current_page: 1, per_page: 10, total: 11, last_page: 2 } });
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const user = userEvent.setup();
  try {
    await act(async () => root.render(<NewFSSRecipePage />));
    await act(async () => user.click(container.querySelector('[aria-label="Choose ingredient 1"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(listCatalog).toHaveBeenLastCalledWith('ingredient', 1, '', '');
    const picker = container.querySelector('[role="dialog"]')!;
    for (const label of ['All', 'Non-auto grocery', 'Auto grocery']) expect(Array.from(picker.querySelectorAll('button')).some(button => button.textContent?.trim() === label)).toBe(true);
    await act(async () => user.click(picker.querySelector('[aria-label="Next page"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(listCatalog).toHaveBeenLastCalledWith('ingredient', 2, '', '');
    await act(async () => user.click(Array.from(picker.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Non-auto grocery')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(listCatalog).toHaveBeenLastCalledWith('ingredient', 1, '', 'non_auto');
    await act(async () => user.type(picker.querySelector('input[type="search"]')!, 'rice'));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(listCatalog).toHaveBeenLastCalledWith('ingredient', 1, 'rice', 'non_auto');
    await act(async () => user.click(picker.querySelector('[aria-label="Add Rice"]')!));
    expect(container.textContent).toContain('Rice');
    expect((container.querySelector('[aria-label="Recipe unit"]') as HTMLSelectElement).value).toBe('kg');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
