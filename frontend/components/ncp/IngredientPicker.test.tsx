// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import { fireEvent, getByRole } from '@testing-library/dom';
import { IngredientPicker } from './IngredientPicker';
import { fetchFoodItems, searchUsda, importUsdaFood } from '@/services/foodLibraryService';
vi.mock('@/services/foodLibraryService', () => ({ fetchFoodItems: vi.fn(async () => ({ data: [], meta: null })), searchUsda: vi.fn(), importUsdaFood: vi.fn() }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let root: Root;
afterEach(async () => { if (root) await act(async () => root.unmount()); document.body.innerHTML = ''; vi.clearAllMocks(); });
it('imports a USDA selection and passes the saved Ingredient to the active menu row', async () => {
  const food = { id: 'saved-food', name: 'Fictional USDA oats', serving_size: '100', serving_unit: 'g', calories: '100' };
  vi.mocked(searchUsda).mockResolvedValue([{ fdc_id: 123, name: food.name, calories: 100 }] as never);
  vi.mocked(importUsdaFood).mockRejectedValueOnce(new Error('Import failed. Try again.')).mockResolvedValueOnce(food as never);
  const onSelect = vi.fn(); const close = vi.fn();
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<IngredientPicker onSelect={onSelect} onClose={close} />));
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 210)); });
  expect(fetchFoodItems).toHaveBeenCalledWith('', 'all', 1);
  await act(async () => fireEvent.change(getByRole(container, 'searchbox'), { target: { value: 'oats' } }));
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Import from USDA' })));
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 210)); });
  expect(searchUsda).toHaveBeenCalledWith('oats');
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: `Add ${food.name}` })));
  expect(container.textContent).toContain('Import failed. Try again.');
  expect(onSelect).not.toHaveBeenCalled();
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: `Add ${food.name}` })));
  expect(importUsdaFood).toHaveBeenLastCalledWith(123);
  expect(onSelect).toHaveBeenCalledWith(food);
});
it('does not query USDA with a blank search or import merely by switching sources', async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<IngredientPicker onSelect={() => {}} onClose={() => {}} />));
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Import from USDA' })));
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 210)); });
  expect(searchUsda).not.toHaveBeenCalled();
  expect(importUsdaFood).not.toHaveBeenCalled();
});

it('uses an already imported Ingredient instead of attempting a duplicate import', async () => {
  const food = { id: 'saved-food', name: 'Saved oats', serving_size: '100', serving_unit: 'g', calories: '100' };
  vi.mocked(searchUsda).mockResolvedValue([{ fdc_id: 123, name: 'USDA oats', library_food: food }] as never);
  const select = vi.fn();
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  await act(async () => root.render(<IngredientPicker onSelect={select} onClose={() => {}} />));
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Import from USDA' })));
  await act(async () => fireEvent.change(getByRole(container, 'searchbox'), { target: { value: 'oats' } }));
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 210)); });
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Add USDA oats' })));
  expect(select).toHaveBeenCalledWith(food);
  expect(importUsdaFood).not.toHaveBeenCalled();
});
