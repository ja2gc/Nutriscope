// @vitest-environment jsdom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { expect, it } from 'vitest';
import { WeeklyMenuCalendar } from './WeeklyMenuCalendar';
import { MenuEditorSlots } from './MenuEditorSlots';
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

it('pads shorter days with noninteractive empty item spaces and keeps removed snack rows absent', async () => {
  const container = document.createElement('div'); document.body.append(container);
  const root = createRoot(container);
  await act(async () => root.render(<WeeklyMenuCalendar snacksHidden getItemCount={(day, meal) => meal === 'breakfast' ? day === 'Monday' ? 3 : 2 : 0} renderCell={(day, meal, minimumItems) => <MenuEditorSlots minimumItems={minimumItems} items={Array.from({ length: meal === 'breakfast' ? day === 'Monday' ? 3 : 2 : 0 }, (_, index) => <span key={index}>Food</span>)} readOnly addLabel="Add" />} />));
  expect(container.querySelectorAll('[data-day="Tuesday"][data-meal="breakfast"] [data-menu-editor-placeholder]')).toHaveLength(1);
  expect(container.querySelectorAll('[data-day="Tuesday"][data-meal="breakfast"] [data-menu-editor-placeholder] button')).toHaveLength(0);
  expect(container.querySelectorAll('[data-meal="am_snack"], [data-meal="pm_snack"]')).toHaveLength(0);
  await act(async () => root.unmount()); container.remove();
});
