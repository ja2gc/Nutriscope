// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import { fireEvent } from '@testing-library/dom';
import { MealPlanOptionsDialog } from './MealPlanOptionsDialog';

vi.mock('@/services/foodLibraryService', () => ({ fetchRecipes: vi.fn(async () => ({ data: [{ id: 'rice', name: 'White Rice', category: 'carb', servings: 1 }], meta: null })) }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let root: Root;
afterEach(async () => { if (root) await act(async () => root.unmount()); document.body.innerHTML = ''; vi.clearAllMocks(); });

it('keeps generation options inside a cancellable dialog and generates without a carb when excluded', async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  const confirm = vi.fn(), close = vi.fn();
  await act(async () => root.render(<MealPlanOptionsDialog onClose={close} onConfirm={confirm} />));
  expect(confirm).not.toHaveBeenCalled();
  await act(async () => fireEvent.click(container.querySelector('[aria-label="Exclude carbs"]')!));
  await act(async () => fireEvent.click(container.querySelector('[aria-label="Exclude snacks"]')!));
  await act(async () => fireEvent.click(Array.from(container.querySelectorAll('button')).find(button => button.textContent === 'Generate')!));
  expect(confirm).toHaveBeenCalledWith(expect.objectContaining({ exclude_carbs: true, exclude_snacks: true }), null);
  await act(async () => fireEvent.keyDown(container.querySelector('[role="dialog"]')!, { key: 'Escape' }));
  expect(close).toHaveBeenCalled();
});
