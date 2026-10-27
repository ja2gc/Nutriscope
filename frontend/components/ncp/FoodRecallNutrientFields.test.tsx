// @vitest-environment jsdom
import React, { act, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { fireEvent, screen } from '@testing-library/dom';
import { afterEach, beforeEach, expect, it } from 'vitest';
import { FoodRecallNutrientFields } from './FoodRecallNutrientFields';

let root: Root;
beforeEach(() => { Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true }); const node = document.createElement('div'); document.body.append(node); root = createRoot(node); });
afterEach(() => { act(() => root.unmount()); document.body.replaceChildren(); });
function Editor() { const [values, setValues] = useState({ energy_kcal: '' } as Record<string, string>); return <FoodRecallNutrientFields values={values} onChange={setValues} />; }
const click = (role: string, name: RegExp) => act(() => fireEvent.click(screen.getByRole(role, { name })));

it('starts with energy only and adds macros and micros in the same field grid', async () => {
  await act(async () => root.render(<Editor />));
  expect(screen.getAllByRole('spinbutton')).toHaveLength(1);
  expect(screen.getByRole('spinbutton', { name: 'Energy intake' })).toBeTruthy();
  click('button', /Display Macros/); click('checkbox', /^Protein/); click('button', /Display Macros/);
  const protein = screen.getByRole('spinbutton', { name: 'Protein intake' });
  act(() => fireEvent.change(protein, { target: { value: '65.25' } }));
  click('button', /Display Micros/); click('checkbox', /^Sodium/); click('button', /Display Micros/);
  expect(screen.getAllByRole('spinbutton')).toHaveLength(3);
  expect((protein as HTMLInputElement).value).toBe('65.25');
  expect(screen.getByRole('spinbutton', { name: 'Sodium intake' })).toBeTruthy();
  click('button', /Display Macros/); click('checkbox', /^Protein/); click('button', /Display Macros/);
  expect(screen.queryByRole('spinbutton', { name: 'Protein intake' })).toBeNull();
});

it('keeps intake fields inside a fluid, shrinkable grid', async () => {
  await act(async () => root.render(<Editor />));

  const input = screen.getByRole('spinbutton', { name: 'Energy intake' });
  const grid = input.closest('.grid');

  expect(grid?.classList.contains('w-full')).toBe(true);
  expect(grid?.classList.contains('min-w-0')).toBe(true);
  expect(grid?.getAttribute('style')).toContain('auto-fit');
});

it('shows saved nutrients without selection controls in view-only entries', async () => {
  await act(async () => root.render(<FoodRecallNutrientFields values={{ energy_kcal: '1800', micro_sodium: '1200', protein_g: '' }} onChange={() => undefined} readOnly />));
  expect(screen.queryByRole('button')).toBeNull();
  expect(screen.getAllByRole('spinbutton')).toHaveLength(2);
  expect((screen.getByRole('spinbutton', { name: 'Sodium intake' }) as HTMLInputElement).disabled).toBe(true);
});
