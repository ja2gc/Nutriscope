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

test('keeps search and pagination outside the scrollable results', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<MenuItemPicker query="rice" onQueryChange={() => {}} loading={false} items={[{ id: 'rice', name: 'Rice' }]} onSelect={() => {}} onClose={() => {}} filters={<div data-picker-filter>Category filter</div>} meta={{ current_page: 1, per_page: 1, total: 2, last_page: 2 }} page={1} onPageChange={() => {}} />));
    const dialog = container.querySelector('[role="dialog"]')!;
    const results = dialog.querySelector('[data-picker-results]');
    const search = dialog.querySelector('input');
    const filter = dialog.querySelector('[data-picker-filter]');
    const pagination = dialog.querySelector('[aria-label="Next page"]');

    expect(dialog.className).toContain('[@media(max-height:420px)]:p-2');
    expect(dialog.querySelector('[class*="max-h-"]')?.className).toContain('max-h-[calc(100dvh-2rem)]');
    expect(dialog.querySelector('[class*="max-h-"]')?.className).toContain('[@media(max-height:420px)]:h-[calc(100dvh-1rem)]');
    expect(dialog.querySelector('[class*="max-h-"]')?.className).toContain('[@media(max-height:420px)]:max-h-[calc(100dvh-1rem)]');
    expect(dialog.querySelector('[data-picker-pagination]')?.className).toContain('[@media(max-height:420px)]:[&>div]:py-2');
    expect(results).not.toBeNull();
    expect(results?.className).toContain('overflow-y-auto');
    expect(search?.closest('[data-picker-results]')).toBeNull();
    expect(filter?.closest('[data-picker-results]')).toBeNull();
    expect(dialog.querySelector('[data-picker-filters]')?.className).toContain('[@media(max-height:420px)]:overflow-x-auto');
    expect(pagination?.closest('[data-picker-results]')).toBeNull();
  } finally { await act(async () => root.unmount()); container.remove(); }
});

test('uses the existing picker for supplies with a readable plural label', async () => {
  globalThis.IS_REACT_ACT_ENVIRONMENT = true;
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  try {
    await act(async () => root.render(<MenuItemPicker title="Add supply" itemLabel="Supply" query="" onQueryChange={() => {}} loading={false} items={[]} onSelect={() => {}} onClose={() => {}} />));
    expect(container.querySelector('[role="dialog"]')?.getAttribute('aria-label')).toBe('Add Supply');
    expect(container.querySelector('label')?.textContent).toBe('Search Supplies');
    expect(container.textContent).toContain('No Supplies found.');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
