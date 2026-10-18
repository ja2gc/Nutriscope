// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import NutritionPrescriptionForm from "./NutritionPrescriptionForm";
import MacroTrackerBar from './MacroTrackerBar';
import { renderToStaticMarkup } from 'react-dom/server';
import { fireEvent, screen } from "@testing-library/dom";

(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe("micronutrient limit direction", () => {
  it('treats a macro ceiling as satisfied below the ceiling', () => {
    const html = renderToStaticMarkup(<MacroTrackerBar targets={[{label: 'Protein', current: 20, target: 60, unit: 'g', operator: 'max'}]} />);
    expect(html).toContain('≤');
    expect(html).toContain('text-emerald-700');
  });
  it('fits bound inputs to available panel width instead of the viewport breakpoint', () => {
    const html = renderToStaticMarkup(<NutritionPrescriptionForm values={{ energy_kcal: '1800', protein_g: '75', carbs_g: '225', fat_g: '60', fluid_ml: '2000', micronutrient_limits: {}, displayed_nutrients: [] }} onChange={vi.fn()} onSave={vi.fn()} saving={false} />);
    expect(html).toContain('repeat(auto-fit,minmax(min(100%,180px),1fr))');
  });
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it("edits minimum targets as minimums and maximum targets as maximums", () => {
    act(() => root.render(<NutritionPrescriptionForm
      values={{
        energy_kcal: "1800", protein_g: "75", carbs_g: "225", fat_g: "60", fluid_ml: "2000",
        micronutrient_limits: {
          fiber: { min: 25, unit: "g" },
          sodium: { max: 2000, unit: "mg" },
        },
        displayed_nutrients: ["fiber", "sodium"],
      }}
      onChange={vi.fn()}
      onSave={vi.fn()}
      saving={false}
      showSave={false}
    />));

    expect((screen.getByRole('combobox', { name: 'Fiber bound' }) as HTMLSelectElement).value).toBe('min');
    expect((container.querySelector('input[aria-label="Fiber min"]') as HTMLInputElement).value).toBe("25");
    expect((screen.getByRole('combobox', { name: 'Sodium bound' }) as HTMLSelectElement).value).toBe('max');
    expect((container.querySelector('input[aria-label="Sodium max"]') as HTMLInputElement).value).toBe("2000");
  });

  it("allows goal defaults to be unchecked and removes their saved limits", () => {
    const change = vi.fn();
    act(() => root.render(<NutritionPrescriptionForm values={{ energy_kcal: '1800', protein_g: '75', carbs_g: '225', fat_g: '60', fluid_ml: '2000', micronutrient_limits: { sodium: { max: 2000, unit: 'mg' } }, displayed_nutrients: ['sodium'] }} requiredMicros={['sodium']} onChange={change} onSave={vi.fn()} saving={false} />));
    act(() => { fireEvent.click(screen.getByRole('button', { name: /Display Micros/ })); });
    const sodium = screen.getByRole('checkbox', { name: /Sodium/ });
    expect(sodium.hasAttribute('disabled')).toBe(false);
    act(() => { fireEvent.click(sodium); });
    expect(change).toHaveBeenCalledWith(expect.objectContaining({ displayed_nutrients: [], micronutrient_limits: {} }));
  });

  it("changes a generated maximum into a plain target without changing its number", () => {
    const change = vi.fn();
    act(() => root.render(<NutritionPrescriptionForm values={{ energy_kcal: '1800', protein_g: '75', carbs_g: '225', fat_g: '60', fluid_ml: '2000', micronutrient_limits: { sodium: { max: 2000, unit: 'mg' } }, displayed_nutrients: ['sodium'] }} onChange={change} onSave={vi.fn()} saving={false} />));
    act(() => { fireEvent.change(screen.getByRole('combobox', { name: 'Sodium bound' }), { target: { value: '' } }); });
    expect(change).toHaveBeenCalledWith(expect.objectContaining({ micronutrient_limits: { sodium: { target: 2000, unit: 'mg' } } }));
    expect(screen.getByRole('button', { name: 'About prescription bounds' })).toBeTruthy();
    expect((screen.getByRole('combobox', { name: 'Protein bound' }) as HTMLSelectElement).value).toBe('');
  });

  it("shows minimum and maximum targets in the meal tracker", () => {
    const source = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx"), "utf8");
    expect(source).toContain("limit?.min != null");
    expect(source).toContain("/ ≥{limit.min}");
    expect(source).toContain("/ ≤{limit.max}");
  });

  it("defaults weight to IBW and offers an editable one-decimal range", () => {
    const change = vi.fn();
    act(() => root.render(<NutritionPrescriptionForm values={{ energy_kcal: '1800', protein_g: '75', carbs_g: '225', fat_g: '60', fluid_ml: '2000', micronutrient_limits: {}, displayed_nutrients: [] }} defaultWeightTarget={60.5} onChange={change} onSave={vi.fn()} saving={false} />));
    expect((screen.getByRole('textbox', { name: 'Target weight' }) as HTMLInputElement).value).toBe('60.5');
    act(() => { fireEvent.change(screen.getByRole('combobox', { name: 'Target weight type' }), { target: { value: 'range' } }); });
    expect(change).toHaveBeenCalledWith(expect.objectContaining({ weight_target: { min: 60.5, max: 60.5 } }));
  });
});
