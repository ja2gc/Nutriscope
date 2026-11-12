// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it, vi } from 'vitest';
import { fireEvent, getByRole } from '@testing-library/dom';
import { ClinicalMenuItemEditor } from './ClinicalMenuItemEditor';
import type { MealPlanItem } from '@/services/mealPlanService';
vi.mock('@/services/foodLibraryService', () => ({ fetchRecipeById: vi.fn(), fetchFoodItems: vi.fn(), importUsdaFood: vi.fn() }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('keeps mixed servings blank when resetting the recipe and saves without a universal quantity', async () => {
  const item = {
    id: 'rice', recipe_id: 'rice', quantity: '', unit: 'serving', source: 'recipe',
    nutrient_snapshot: {
      name: 'Rice', calories: 100, protein: 1, carbs: 20, fat: 1, serving_size: 1, serving_unit: 'serving', recipe_yield: 1, micronutrients: {},
      recipe_ingredients: [{ id: 1, name: 'Rice', quantity: 100, unit: 'g' }],
      recipe_original_ingredients: [{ id: 1, name: 'Rice', quantity: 100, unit: 'g', food_item: { id: 'food', name: 'Rice', calories: '100', protein: '1', carbs: '20', fat: '1', serving_size: '100', serving_unit: 'g', micronutrients: {} } }],
    },
  } as MealPlanItem;
  const container = document.createElement('div'); document.body.append(container); const root = createRoot(container);
  const save = vi.fn(async () => true);
  try {
    await act(async () => root.render(<ClinicalMenuItemEditor item={item} allowMixedQuantity onBack={() => {}} onSave={save} />));
    await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Reset recipe' })));
    expect((getByRole(container, 'spinbutton', { name: 'Planned portion' }) as HTMLInputElement).value).toBe('');
    await act(async () => fireEvent.click(getByRole(container, 'button', { name: 'Save Changes' })));
    expect(save).toHaveBeenCalledWith({}, item.nutrient_snapshot);
  } finally { await act(async () => root.unmount()); container.remove(); }
});
