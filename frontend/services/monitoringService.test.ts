import { describe, expect, it } from "vitest";

import {
  CLINICAL_LAB_META,
  GOAL_LAB_FLAGS,
  LAB_REFERENCE_RANGES,
  type ClinicalLabKey,
} from "./monitoringService";

describe("monitoring lab metadata", () => {
  it("supports severe nutrition electrolyte labs from monitoring plans", () => {
    const knownKeys = Object.keys(CLINICAL_LAB_META) as ClinicalLabKey[];

    expect(knownKeys).toEqual(expect.arrayContaining(["phosphate", "magnesium"]));
    expect(CLINICAL_LAB_META.phosphate).toMatchObject({
      label: "Phosphate",
      unit: "mg/dL",
      type: "number",
    });
    expect(CLINICAL_LAB_META.magnesium).toMatchObject({
      label: "Magnesium",
      unit: "mg/dL",
      type: "number",
    });
    expect(LAB_REFERENCE_RANGES.phosphate).toMatchObject({
      label: "Phosphate",
      unit: "mg/dL",
      min: 2.5,
      max: 4.5,
    });
    expect(LAB_REFERENCE_RANGES.magnesium).toMatchObject({
      label: "Magnesium",
      unit: "mg/dL",
      min: 1.7,
      max: 2.2,
    });
  });

  it("keeps fallback goal lab flags aligned with renal electrolyte monitoring", () => {
    expect(GOAL_LAB_FLAGS.renal_diet).toEqual(
      expect.arrayContaining(["potassium", "phosphate"])
    );
    expect(GOAL_LAB_FLAGS.custom).toEqual(
      expect.arrayContaining(["phosphate", "magnesium"])
    );
  });
});
