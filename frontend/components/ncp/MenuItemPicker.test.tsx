// @vitest-environment jsdom
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, test, vi } from 'vitest';
import { MenuItemPicker } from './MenuItemPicker';

test('shared menu picker focuses search, selects a result, and closes with Escape', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container), select = vi.fn(), close = vi.fn();
  try {
    await act(async () => root.render(<MenuItemPicker query="" onQueryChange={() => {}} loading={false} items={[{ id: 'rice', name: 'Rice' }]} onSelect={select} onClose={close} />));
    expect(document.activeElement).toBe(container.querySelector('input'));
    await act(async () => container.querySelector<HTMLButtonElement>('[aria-label="Add Rice"]')!.click());
    expect(select).toHaveBeenCalledWith('rice');
    await act(async () => container.querySelector('input')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    expect(close).toHaveBeenCalledOnce();
  } finally { await act(async () => root.unmount()); container.remove(); }
});

test('reuses the picker for ingredients with matching accessible labels', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<MenuItemPicker title="Add Ingredient" itemLabel="Ingredient" query="" onQueryChange={() => {}} loading={false} items={[]} onSelect={() => {}} onClose={() => {}} />));
    expect(container.querySelector('[role="dialog"]')?.getAttribute('aria-label')).toBe('Add Ingredient');
    expect(container.querySelector('label')?.textContent).toBe('Search Ingredients');
    expect(container.textContent).toContain('No Ingredients found.');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
