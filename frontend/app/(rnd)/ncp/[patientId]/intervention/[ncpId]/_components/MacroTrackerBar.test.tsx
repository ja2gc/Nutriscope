import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { expect, it } from 'vitest';
import MacroTrackerBar from './MacroTrackerBar';
import InterventionPlansTab from './InterventionPlansTab';

it('keeps fluid beside the macros and secondary fluid details inside the same card', () => {
  const html = renderToStaticMarkup(<MacroTrackerBar targets={[{ label: 'Energy', current: 100, target: 2000, unit: 'kcal' }, { label: 'Fluid', current: 250, target: 1800, unit: 'mL' }]}><p>Remaining drinking fluid: 1550 mL</p></MacroTrackerBar>);
  expect(html).toContain('Fluid');
  expect(html).toContain('250');
  expect(html).toContain('Remaining drinking fluid');
});

it('keeps the selected plan white by default with the same hover behavior as other plans', () => {
  const html = renderToStaticMarkup(<InterventionPlansTab plans={[{ id: 'active', plan_date: '2026-10-27', goal_type: 'custom', disease_stage: null, source_monitoring_id: null, source_monitoring_date: null, has_meal_plan: false, meal_plan_id: null }]} meta={null} page={1} selectedPlanId="active" latestPlanId="active" loading={false} error={null} onCreate={() => {}} onSelect={() => {}} onDelete={async () => {}} onPageChange={() => {}} />);
  const classes = html.match(/aria-current="true" class="([^"]+)"/)?.[1] ?? '';
  expect(classes.split(' ')).toContain('bg-white');
  expect(classes).toContain('hover:bg-warm-50/60');
  expect(classes.split(' ')).not.toContain('bg-warm-50');
});
