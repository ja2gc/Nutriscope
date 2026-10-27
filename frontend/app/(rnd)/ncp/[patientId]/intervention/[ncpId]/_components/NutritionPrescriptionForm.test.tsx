import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { expect, test } from "vitest";
import NutritionPrescriptionForm from "./NutritionPrescriptionForm";
import GoalSelectorModal from "./GoalSelectorModal";
import { emptyPrescriptionForm } from "@/lib/interventionGoalState";

test("adds only energy adjustment controls and keeps existing nutrient fields editable", () => {
  const html = renderToStaticMarkup(<NutritionPrescriptionForm goalType="general_diet"
    values={{ ...emptyPrescriptionForm(), energy_adjustment_mode: "deficit", energy_adjustment_kcal: "300",
      fluid_mode: "restricted", fluid_ml: "1700", nutrient_operators: { fluid_ml: "max" } }}
    onChange={() => {}} onSave={() => {}} saving={false} />);
  expect(html).toContain("Add Surplus");
  expect(html).toContain("Add Deficit");
  expect(html).toContain("Adjustment amount");
  expect(html.match(/<select[^>]*aria-label="Fluid bound"[^>]*>/)?.[0]).not.toContain(' disabled=""');
  for (const label of ["fluid-mode", "Clinical inputs and notes", "Energy coefficient", "Protein coefficient", "Clinical calculation weight", "Protein calculation weight", "Include assessed refeeding", "Include assessed malnutrition"]) {
    expect(html).not.toContain(label);
  }
  expect(html).not.toContain("Maternal additions reviewed");
});

test("explains a legacy selection before recalculation without locking saved targets", () => {
  const html = renderToStaticMarkup(<NutritionPrescriptionForm goalType="diabetic_control"
    calculationBlockedReason="Choose a current goal and type to recalculate. Saved targets remain editable."
    values={emptyPrescriptionForm()} onCalculate={() => {}} onChange={() => {}} onSave={() => {}} saving={false} />);
  expect(html).toContain("Saved targets remain editable.");
  expect(html).toMatch(/<button[^>]*disabled[^>]*>Calculate targets/);
  expect(html.match(/<input[^>]*aria-label="Energy"[^>]*>/)?.[0]).not.toContain(' disabled=""');
});

test("flattens the prescription card at narrow widths", () => {
  const html = renderToStaticMarkup(<NutritionPrescriptionForm values={emptyPrescriptionForm()}
    onChange={() => {}} onSave={() => {}} saving={false} />);
  expect(html).toMatch(/<div class="[^"]*border-0[^"]*sm:border/);
  expect(html).toMatch(/<div class="[^"]*bg-transparent[^"]*sm:bg-white/);
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
