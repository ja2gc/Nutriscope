// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import { fireEvent, getByRole, queryByRole } from '@testing-library/dom';
import { CarbMenuEditor } from './CarbMenuEditor';

vi.mock('@/services/foodLibraryService', () => ({ fetchRecipes: vi.fn(async () => ({ data: [], meta: null })) }));
vi.mock('./ClinicalMenuItemEditor', () => ({ ClinicalMenuItemEditor: ({ item, onSave }: { item: { nutrient_snapshot: { name: string } }; onSave: (value: { quantity: number }, snapshot: null) => void }) => <><p>{item.nutrient_snapshot.name} profile</p><button onClick={() => onSave({ quantity: 3 }, null)}>Save Changes</button></> }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let root: Root;
afterEach(async () => { if (root) await act(async () => root.unmount()); document.body.innerHTML = ''; vi.clearAllMocks(); });
const entries = [
  { recipeId: 'rice', name: 'White Rice', category: 'carb', quantity: 1 },
  { recipeId: 'rice', name: 'White Rice', category: 'carb', quantity: 2 },
  { recipeId: 'oats', name: 'Oats', category: 'carb', quantity: 1 },
  { recipeId: 'meal', name: 'Chicken', category: 'meal', quantity: 1 },
];
it('lists matching carb cards with pencil and delete actions, separate from adding', async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  const apply = vi.fn();
  await act(async () => root.render(<CarbMenuEditor mode="edit" items={entries} onBack={() => {}} onApply={apply} />));
  expect(container.textContent).toContain('2 items');
  expect(document.activeElement).toBe(getByRole(container, 'heading', { name: 'Edit carbs' }));
  expect(container.textContent).not.toContain('Chicken');
  expect(queryByRole(container, 'button', { name: 'Add carbs' })).toBeNull();
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Edit White Rice' })));
  expect(container.textContent).toContain('White Rice profile');
  expect(container.textContent).not.toContain('Change carb');
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Save Changes' })));
  expect(apply).toHaveBeenCalledWith({ source_recipe_id: 'rice', quantity: 3 }, null, null);
});
it('confirms removal of every matching item without removing another recipe', async () => {
  const container = document.createElement('div'); document.body.append(container); root = createRoot(container);
  const apply = vi.fn();
  await act(async () => root.render(<CarbMenuEditor mode="edit" items={entries} onBack={() => {}} onApply={apply} />));
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Delete White Rice' })));
  expect(apply).not.toHaveBeenCalled();
  expect(getByRole(container, 'dialog').textContent).toContain('2');
  await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Delete' })));
  expect(apply).toHaveBeenCalledWith({ source_recipe_id: 'rice', remove: true }, null, null);
});
