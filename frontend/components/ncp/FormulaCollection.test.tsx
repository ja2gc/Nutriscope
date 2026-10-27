// @vitest-environment jsdom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { fireEvent, getByRole, queryByRole } from '@testing-library/dom';
import { expect, it, vi } from 'vitest';
import { FormulaCollection } from './FormulaCollection';
import { formulaFromRecipe } from '@/lib/formulaPlan';
import type { Recipe } from '@/services/foodLibraryService';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
vi.mock('@/services/foodLibraryService', () => ({ fetchRecipes: vi.fn(), fetchRecipeById: vi.fn() }));

const recipe = { id: 'formula', name: 'Fictional formula', servings: 2, ingredients: [{ id: 1, quantity: '100', unit: 'g', food_item: {
  id: 'ingredient', name: 'Ingredient A', serving_size: '100', serving_unit: 'g', calories: '100', protein: '10', carbs: '10', fat: '2', micronutrients: { sodium: 20 },
} }] } as unknown as Recipe;

it('preserves an ingredient edit when changing 2x to 3x, and explicitly resets the captured recipe', async () => {
  function Harness() {
    const [items, setItems] = useState([formulaFromRecipe(recipe)]);
    return <FormulaCollection items={items} onChange={setItems} selectedMicros={['sodium']} />;
  }
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<Harness />));
  try {
    await act(async () => getByRole(container, 'button', { name: 'Edit Fictional formula' }).click());
    const portion = getByRole(container, 'spinbutton', { name: 'Portion multiplier' });
    await act(async () => fireEvent.input(portion, { target: { value: '2' } }));
    const ingredient = getByRole(container, 'spinbutton', { name: 'Ingredient A quantity' }) as HTMLInputElement;
    expect(ingredient.value).toBe('200');
    await act(async () => fireEvent.input(ingredient, { target: { value: '150' } }));
    await act(async () => fireEvent.input(portion, { target: { value: '3' } }));
    expect(ingredient.value).toBe('225');
    expect(container.textContent).toContain('Sodium');
    await act(async () => getByRole(container, 'button', { name: 'Reset recipe' }).click());
    expect(ingredient.value).toBe('100');
    expect((portion as HTMLInputElement).value).toBe('1');
    expect(recipe.ingredients[0].quantity).toBe('100');
  } finally { await act(async () => root.unmount()); container.remove(); }
});

it('keeps template profiles view-only while still allowing item selection and removal', async () => {
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<FormulaCollection items={[formulaFromRecipe(recipe)]} onChange={vi.fn()} editPortions={false} />));
  try {
    expect(getByRole(container, 'button', { name: 'Select formula' })).toBeTruthy();
    expect(getByRole(container, 'button', { name: 'Remove Formula 1' })).toBeTruthy();
    await act(async () => getByRole(container, 'button', { name: 'View Fictional formula' }).click());
    expect(queryByRole(container, 'spinbutton')).toBeNull();
    expect(queryByRole(container, 'button', { name: 'Reset recipe' })).toBeNull();
  } finally { await act(async () => root.unmount()); container.remove(); }
});

it('uses name-only clickable slots and switches each individual nutrient profile', async () => {
  const first = formulaFromRecipe(recipe), second = formulaFromRecipe({ ...recipe, id: 'second', name: 'Second formula' });
  first.portion_multiplier = 3;
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<FormulaCollection items={[first, second]} onChange={vi.fn()} selectedMicros={['sodium']} onCreate={vi.fn()} />));
  try {
    const slot = getByRole(container, 'article', { name: 'Formula 1' });
    expect(slot.textContent).toContain('Fictional formula');
    expect(slot.textContent).not.toMatch(/300 g|30 g|3×|3x|Recipe makes|View profile|Remove item|kcal/);
    expect(getByRole(slot, 'button', { name: 'Edit Fictional formula' })).toBeTruthy();
    expect(getByRole(slot, 'button', { name: 'Remove Formula 1' }).querySelector('svg')).toBeTruthy();
    expect(getByRole(container, 'region', { name: 'Formula 1 nutrient profile' }).textContent).toContain('300');
    await act(async () => getByRole(container, 'button', { name: 'Formula 2' }).click());
    const selected = getByRole(container, 'region', { name: 'Formula 2 nutrient profile' });
    expect(selected.textContent).toContain('100');
    expect(selected.textContent).toContain('20 mg');
    expect(selected.textContent).not.toContain('60 mg');
    const next = getByRole(container, 'region', { name: 'Add formula' });
    expect(getByRole(next, 'button', { name: 'Create formula' })).toBeTruthy();
    expect(getByRole(next, 'button', { name: 'Select formula' })).toBeTruthy();
  } finally { await act(async () => root.unmount()); container.remove(); }
});

it('commits Save from the existing leave dialog and discards only the uncommitted profile draft', async () => {
  function Harness() {
    const [items, setItems] = useState([formulaFromRecipe(recipe)]);
    return <FormulaCollection items={items} onChange={setItems} />;
  }
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  await act(async () => root.render(<Harness />));
  try {
    await act(async () => getByRole(container, 'button', { name: 'Edit Fictional formula' }).click());
    await act(async () => fireEvent.input(getByRole(container, 'spinbutton', { name: 'Portion multiplier' }), { target: { value: '2' } }));
    await act(async () => getByRole(container, 'button', { name: 'Back to formula plan' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Save' }).click());
    expect(getByRole(container, 'region', { name: 'Formula 1 nutrient profile' }).textContent).toContain('200');
    await act(async () => getByRole(container, 'button', { name: 'Edit Fictional formula' }).click());
    await act(async () => fireEvent.input(getByRole(container, 'spinbutton', { name: 'Portion multiplier' }), { target: { value: '3' } }));
    await act(async () => getByRole(container, 'button', { name: 'Back to formula plan' }).click());
    await act(async () => getByRole(container, 'button', { name: 'Discard' }).click());
    expect(getByRole(container, 'region', { name: 'Formula 1 nutrient profile' }).textContent).toContain('200');
  } finally { await act(async () => root.unmount()); container.remove(); }
});
