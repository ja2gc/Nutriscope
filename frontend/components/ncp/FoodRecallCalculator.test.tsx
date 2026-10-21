// @vitest-environment jsdom
import React, { act, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { fireEvent, screen } from '@testing-library/dom';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { FoodRecallCalculator } from './FoodRecallCalculator';
import type { FoodRecallPlan } from '@/lib/foodRecall';

const plan: FoodRecallPlan = { days_count: 1, displayed_micros: ['sodium'], days: [{ items: [{
  id: 'line-1', food_item_id: 'rice', recipe_id: null, quantity: 100, unit: 'g',
  nutrient_snapshot: { name: 'Saved rice', calories: 120, protein: 3, carbs: 25, fat: 1, serving_size: 100, serving_unit: 'g', micronutrients: { sodium: 2 } },
}] }] };
let root: Root;
beforeEach(() => { Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true }); const container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); vi.restoreAllMocks(); });
const click = async (name: string | RegExp) => act(async () => { fireEvent.click(screen.getByRole('button', { name })); });

function Editor() {
  const [value, setValue] = useState<FoodRecallPlan | null>(plan);
  return <FoodRecallCalculator value={value} onSave={setValue} />;
}

it('starts collapsed and reuses the leave dialog to discard a replacement', async () => {
  await act(async () => root.render(<Editor />));
  expect(screen.queryByRole('table')).toBeNull();
  await click('Show Food Recall Calculator');
  expect(screen.queryByRole('checkbox', { name: /Day recorded/ })).toBeNull();
  await click('New calculation');
  expect(screen.queryByRole('button', { name: /Edit Saved rice/ })).toBeNull();
  await click('Back to food recall');
  expect(screen.getByRole('dialog', { name: 'Unsaved changes' })).toBeTruthy();
  await click('Discard');
  await click('Show Food Recall Calculator');
  expect(screen.getByRole('button', { name: /Edit Saved rice/ })).toBeTruthy();
});

it('keeps entered quantity when changing to a compatible unit and recalculates nutrients', async () => {
  await act(async () => root.render(<Editor />));
  await click('Show Food Recall Calculator');
  await click(/Edit Saved rice/);
  const units = screen.getByRole('combobox', { name: 'Unit' });
  expect(Array.from(units.querySelectorAll('option')).map(o => o.value)).toEqual(['mg', 'g', 'kg', 'oz', 'lb']);
  act(() => fireEvent.change(screen.getByRole('spinbutton', { name: 'Quantity' }), { target: { value: '1' } }));
  act(() => fireEvent.change(units, { target: { value: 'kg' } }));
  expect((screen.getByRole('spinbutton', { name: 'Quantity' }) as HTMLInputElement).value).toBe('1');
  expect(screen.getByText('1200')).toBeTruthy();
  await click('Apply item');
  await click('Save Plan');
  await click('Back to food recall');
  expect(screen.queryByRole('dialog')).toBeNull();
  await click('Show Food Recall Calculator');
  expect(screen.getByText('1 kg · 1200 kcal')).toBeTruthy();
});

it('requires at least one item in every day and does not overwrite the accepted plan', async () => {
  await act(async () => root.render(<Editor />));
  await click('Show Food Recall Calculator');
  await click('Add Weekend');
  await click('Save Plan');
  expect(screen.getByRole('alert').textContent).toContain('every selected day');
  await click('Back to food recall'); await click('Discard'); await click('Show Food Recall Calculator');
  expect(screen.getAllByRole('columnheader')).toHaveLength(7);
  expect(screen.queryByText('Weekend 1')).toBeNull();
});

it('starts with no days and adds grouped weekdays and weekends across seven-column pages', async () => {
  await act(async () => root.render(<FoodRecallCalculator />));
  await click('Show Food Recall Calculator');
  expect(screen.queryByText('Recall Coverage')).toBeNull();
  expect(screen.queryByRole('combobox', { name: 'Recall period' })).toBeNull();
  expect(screen.getAllByRole('columnheader')).toHaveLength(7);
  expect(screen.queryByRole('button', { name: /Add item to/ })).toBeNull();
  await click('Add Weekend');
  expect(screen.queryByText('Recall Coverage')).toBeNull();
  await click('Add Weekday');
  expect(screen.getAllByRole('columnheader').slice(0, 2).map(cell => cell.textContent)).toEqual(['Weekday 1', 'Weekend 1']);
  for (let index = 0; index < 6; index++) await click('Add Weekday');
  expect(screen.getByRole('columnheader', { name: 'Weekday 7' })).toBeTruthy();
  expect(screen.queryByRole('columnheader', { name: 'Weekend 1' })).toBeNull();
  await click('Next days');
  expect(screen.queryByRole('columnheader', { name: 'Weekday 7' })).toBeNull();
  expect(screen.getByRole('columnheader', { name: 'Weekend 1' })).toBeTruthy();
  await click('Add Weekend');
  expect(screen.getByRole('columnheader', { name: 'Weekend 2' })).toBeTruthy();
  await click('Previous days');
  expect(screen.getByRole('columnheader', { name: 'Weekday 1' })).toBeTruthy();
  await click('Remove Weekday 1');
  expect(screen.getByRole('columnheader', { name: 'Weekday 6' })).toBeTruthy();
  expect(screen.queryByRole('columnheader', { name: 'Weekday 7' })).toBeNull();
});

it('shows recall coverage only from an accepted plan in editable and view-only entries', async () => {
  const saved = { ...plan, days_count: 8, days: Array.from({ length: 8 }, (_, index) => ({ kind: index < 6 ? 'weekday' as const : 'weekend' as const, items: plan.days[0].items })) };
  await act(async () => root.render(<FoodRecallCalculator value={saved} />));
  expect(screen.getByText('Recall Coverage')).toBeTruthy();
  expect(screen.getByText('6 weekdays · 2 weekends')).toBeTruthy();
  await click('Show Food Recall Calculator'); await click('Add Weekend');
  expect(screen.getByText('6 weekdays · 2 weekends')).toBeTruthy();
  expect(screen.queryByText('6 weekdays · 3 weekends')).toBeNull();
  await act(async () => root.render(<FoodRecallCalculator value={saved} readOnly />));
  expect(screen.getByText('6 weekdays · 2 weekends')).toBeTruthy();
});

it('keeps the accepted draft when saving through the leave dialog', async () => {
  await act(async () => root.render(<Editor />));
  await click('Show Food Recall Calculator'); await click(/Edit Saved rice/);
  act(() => fireEvent.change(screen.getByRole('spinbutton', { name: 'Quantity' }), { target: { value: '200' } }));
  await click('Apply item'); await click('Back to food recall'); await click('Save');
  expect(screen.queryByRole('dialog')).toBeNull();
  await click('Show Food Recall Calculator');
  expect(screen.getByText('200 g · 240 kcal')).toBeTruthy();
});

it('lets saved logs show profiles and choose micros without exposing editing actions', async () => {
  await act(async () => root.render(<FoodRecallCalculator value={plan} readOnly defaultMicros={['sodium']} />));
  await click('Show Food Recall Calculator');
  expect(screen.queryByRole('button', { name: 'Save Plan' })).toBeNull();
  expect(screen.queryByRole('button', { name: 'New calculation' })).toBeNull();
  expect(screen.getByRole('button', { name: /Display Micros/ })).toBeTruthy();
  await click(/View Saved rice/);
  expect(screen.queryByRole('spinbutton')).toBeNull();
  expect(screen.getByText('Consumed amount: 100 g')).toBeTruthy();
  expect(screen.queryByRole('button', { name: 'Apply item' })).toBeNull();
});
