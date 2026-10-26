// @vitest-environment jsdom

import React, { act, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import userEvent from '@testing-library/user-event';
import { afterEach, expect, test, vi } from 'vitest';
import EditFSSRecipePage from './page';
import { listCatalog } from '@/services/fsCatalogService';

vi.mock('next/navigation', () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock('@/services/fsCatalogService', () => ({ listCatalog: vi.fn() }));

const rice = {
  id: 'rice', name: 'Rice', kind: 'ingredient' as const, category: 'grain', base_unit: 'kg',
  purchase_unit: 'sack', purchase_price: '250', units_per_purchase: '1', unit_cost: 250,
  default_supplier_id: null, vendor: null, include_in_generated_lists: true,
};

const recipe = {
  id: 'recipe-1', name: 'Rice bowl', category: 'meal', prep_notes: '', servings: 5, cost: 10,
  ingredients: [{ id: 1, fs_item_id: 'existing-rice', quantity: 2, unit: 'kg', adjust_to_taste: false, fs_item: { ...rice, id: 'existing-rice', name: 'Existing Rice' } }],
};

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

afterEach(() => { document.body.innerHTML = ''; vi.unstubAllGlobals(); vi.clearAllMocks(); });

test('loads inventory ingredients before typing and selects a paginated ingredient into the added row', async () => {
  vi.mocked(listCatalog).mockResolvedValue({ data: [rice], meta: { current_page: 1, per_page: 10, total: 11, last_page: 2 } });
  vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, json: async () => ({ data: recipe }) })));
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const user = userEvent.setup();
  try {
    await act(async () => root.render(<Suspense fallback={null}><EditFSSRecipePage params={Promise.resolve({ id: 'recipe-1' })} searchParams={Promise.resolve({})} /></Suspense>));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 0)); });
    await act(async () => user.click(Array.from(container.querySelectorAll('button')).find(button => button.textContent?.trim() === 'Add Row')!));
    await act(async () => user.click(container.querySelector('[aria-label="Choose ingredient 2"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(listCatalog).toHaveBeenLastCalledWith('ingredient', 1, '', '');
    const picker = container.querySelector('[role="dialog"]')!;
    for (const label of ['All', 'Non-auto grocery', 'Auto grocery']) expect(Array.from(picker.querySelectorAll('button')).some(button => button.textContent?.trim() === label)).toBe(true);
    await act(async () => user.click(picker.querySelector('[aria-label="Next page"]')!));
    await act(async () => { await new Promise(resolve => setTimeout(resolve, 250)); });
    expect(listCatalog).toHaveBeenLastCalledWith('ingredient', 2, '', '');
    await act(async () => user.click(picker.querySelector('[aria-label="Add Rice"]')!));
    expect((container.querySelector('[aria-label="Recipe unit"]:last-of-type') as HTMLSelectElement)?.value).toBe('kg');
    expect(container.textContent).toContain('Existing Rice');
    expect(container.textContent).toContain('Rice');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
