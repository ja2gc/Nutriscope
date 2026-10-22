import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, test } from "vitest";
import NutritionPrescriptionForm from "./NutritionPrescriptionForm";
import GoalSelectorModal from "./GoalSelectorModal";
import { emptyPrescriptionForm } from "@/lib/interventionGoalState";

test("shows explicit energy direction and keeps prescribed fluid a maximum", () => {
  const html = renderToStaticMarkup(<NutritionPrescriptionForm goalType="general_diet"
    values={{ ...emptyPrescriptionForm(), energy_adjustment_mode: "deficit", energy_adjustment_kcal: "300",
      fluid_mode: "restricted", fluid_ml: "1700", nutrient_operators: { fluid_ml: "max" } }}
    onChange={() => {}} onSave={() => {}} saving={false} />);
  expect(html).toContain("Add Surplus");
  expect(html).toContain("Add Deficit");
  expect(html).toContain("Adjustment amount");
  expect(html.match(/<select[^>]*aria-label="Fluid bound"[^>]*>/)?.[0]).toContain(' disabled=""');
  expect(html).not.toContain("Maternal additions reviewed");
});

test("pediatric selector explains the smaller menu and cannot confirm an adult preset", () => {
  const html = renderToStaticMarkup(<GoalSelectorModal isPediatric initialGoal="renal_diet" initialStage="ckd_1_2"
    onConfirm={() => {}} onClose={() => {}} />);
  expect(html).toContain("About pediatric goal options");
  expect(html).toContain("General Diet");
  expect(html).toContain("Diabetic Control");
  expect(html).toContain("Custom Plan");
  expect(html).not.toContain("Renal Diet");
  expect(html).toMatch(/<button[^>]*disabled[^>]*>Save Goal/);
});
