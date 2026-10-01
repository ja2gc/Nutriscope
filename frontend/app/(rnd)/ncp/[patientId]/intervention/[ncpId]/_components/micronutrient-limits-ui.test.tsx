// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import NutritionPrescriptionForm from "./NutritionPrescriptionForm";

(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

describe("micronutrient limit direction", () => {
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

    expect(container.textContent).toMatch(/Fiber\s*min/);
    expect((container.querySelector('input[aria-label="Fiber min"]') as HTMLInputElement).value).toBe("25");
    expect(container.textContent).toMatch(/Sodium\s*max/);
    expect((container.querySelector('input[aria-label="Sodium max"]') as HTMLInputElement).value).toBe("2000");
  });

  it("shows minimum and maximum targets in the meal tracker", () => {
    const source = readFileSync(join(process.cwd(), "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx"), "utf8");
    expect(source).toContain("limit?.min != null");
    expect(source).toContain("/ ≥{limit.min}");
    expect(source).toContain("/ ≤{limit.max}");
  });
});
