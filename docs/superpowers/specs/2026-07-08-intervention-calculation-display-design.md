# Intervention Calculation Display Design

## Context

NutriScope already has an authoritative nutrition prescription calculation engine in the Laravel backend (`NutritionPrescriptionService`) and a TypeScript live-preview mirror in `frontend/lib/nutritionCalculations.ts`. Both are documented as deriving from `docs/logic/intervention-goals.md`, which consolidates the original non-Asia-Pacific calculation research (`intervention-goals-0.md`) and the Asia-Pacific / Filipino classification research (`intervention-goals-asia-pacific-research.md`).

This feature does not change the clinical formulas, goal catalog, stage mapping, or persisted prescription behavior. It adds an inline, optional explanation panel so RND users can see how the displayed prescription values were calculated and whether manual edits differ from the calculated baseline.

## Goals

- Show a live formula and substituted calculation for the selected intervention goal and disease stage.
- Update the calculation display when the user changes intervention goal or disease stage.
- Update comparison statuses when the user manually edits prescribed nutrient targets.
- Keep the display hidden by default and expose a clear `Show calculations` / `Hide calculations` action.
- Include all prescribed nutrients: energy, protein, carbohydrates, fat, fluid, and all micronutrients displayed or required by the selected goal.
- Preserve the existing NutriScope visual language: white cards, warm borders, emerald accents, readable dense dashboard spacing, and existing typography utilities.
- Avoid hardcoded pixel font sizes in new UI. Use existing Tailwind type scale and `font-numeric` for tabular numeric values.

## Non-Goals

- No clinical formula changes.
- No changes to the backend authoritative autofill endpoint.
- No persistence of the calculation panel open/closed state beyond the current page session.
- No audit/export trail for formula traces in this phase.
- No fake numeric targets for micronutrients that are only flagged for monitoring.

## UX Design

The calculation display appears inline inside `NutritionPrescriptionForm`, directly below the macro input grid and before micronutrient limit rows. It is hidden by default.

The panel header contains:

- Title: `Calculations`
- Secondary text: `Review how current targets were derived from the selected goal.`
- Action button: `Show calculations` when collapsed, `Hide calculations` when expanded

When expanded, the panel uses compact grouped sections:

1. **Inputs Used**
   - Goal label and disease stage label
   - Weight used for calculation, including dry weight when edema is present
   - Height, age, sex
   - PAL key and PAL factor when relevant
   - Pregnancy/lactation modifier when present

2. **Weight Basis**
   - IBW formula and result
   - %IBW formula and result
   - AjBW formula and result only when `%IBW > 120`
   - Working weight rule and result
   - Protein weight rule and result

3. **Prescribed Targets**
   - One row for each macro and fluid target:
     - `Prescribed`: current editable value from the form
     - `Calculated`: current goal baseline from the frontend mirror
     - `Formula`: human-readable formula
     - `Calculation`: substituted numbers
     - Status badge: `Matches`, `Modified`, `Manual`, or `Missing`
   - One row for each displayed or required micronutrient:
     - Calculated micros from the goal output use concrete numeric targets, such as sodium max, fiber min, cholesterol max, or free sugars converted from percent energy to grams.
     - Goal-required micros without numeric targets, such as potassium, phosphate, and magnesium during refeeding, are shown as `Flagged for monitoring`.
     - User-added micros without calculated goal targets are shown as `Manual prescribed target`.

Status behavior:

- `Matches`: prescribed value equals calculated value after numeric normalization and rounding tolerance.
- `Modified`: prescribed value differs from calculated baseline.
- `Manual`: no calculated baseline exists, but user entered a prescribed value.
- `Missing`: selected goal has a calculated baseline or required flag, but the form does not currently contain a prescribed value.

## Component Design

Add a focused frontend explanation layer instead of embedding trace logic in the page component.

New calculation trace model:

- `CalculationTrace`
  - `inputs`: display-safe patient and goal context
  - `weights`: IBW, %IBW, AjBW, working weight, protein weight rows
  - `targets`: nutrient rows for macros, fluid, and micros
  - `notes`: goal notes and safety warnings already surfaced by the prescription flow

New helper responsibilities:

- Build trace data from the selected `goalType`, `stage`, `PatientMetrics`, current `PrescriptionFormState`, and current goal labels.
- Reuse existing calculation helpers in `nutritionCalculations.ts` instead of duplicating formula math.
- Keep formulas in a structured, display-oriented form so tests can assert both values and labels.

UI component responsibilities:

- `NutritionPrescriptionForm` owns the collapsed/expanded UI state.
- A new `PrescriptionCalculationPanel` renders the trace.
- The panel is present only when there is a selected goal and enough patient metrics to build at least partial trace data.
- Missing inputs render as a compact warning row rather than an empty panel.

## Data Flow

1. User opens Intervention page.
2. Existing page code loads assessment, patient, intervention, and derives `patientMetrics`.
3. User selects or changes an intervention goal.
4. Existing flow computes instant frontend preview, then backend authoritative autofill, then updates the prescription form with persisted backend values.
5. The calculation trace builder recomputes from:
   - current `intervention.goal_type`
   - current `intervention.disease_stage`
   - current `patientMetrics`
   - current `prescription`
   - current required/displayed micronutrients
6. User edits prescribed values manually.
7. The panel updates statuses live. Calculated baseline stays unchanged; `Prescribed` changes and the row becomes `Modified` or `Manual`.

This keeps backend persistence authoritative while giving immediate client-side transparency.

## Formula Trace Rules

Adult shared formulas:

- IBW: Hamwi formula with 30 kg floor.
- %IBW: `weightKg / IBW * 100`.
- AjBW: `IBW + 0.25 * (actual - IBW)`, shown only when `%IBW > 120`.
- Working weight: `%IBW > 120 ? AjBW : actual weight`.
- Protein weight: IBW for adult goal protein targets.
- BMR: Mifflin-St Jeor when the selected goal uses TEE.
- TEE: `BMR * PAL`.
- Fluid baseline: `working weight * 32.5`.
- Macro split: fat from goal-specific percent, carbs as remaining energy after protein and fat.

Pediatric shared formulas:

- Schofield BMR by age and sex.
- TEE: `Schofield BMR * PAL + growth allowance`.
- Fluid: Holliday-Segar.
- Protein: age-banded pediatric protein per kg.

Goal-specific target rows follow the existing TypeScript mirror behavior. If the current mirror does not expose a named intermediate directly, the trace helper derives display values using the same helper functions and constants already used by `autofillPrescription`.

## Visual And Accessibility Requirements

- Use the existing `Card`/warm border/emerald status language where practical.
- Use `font-numeric`, `font-semibold`, and existing Tailwind text sizes such as `text-xs`, `text-sm`, `text-base`, and `text-lg`.
- Do not introduce fixed pixel font sizes.
- Use a semantic `<button>` for show/hide.
- Add `aria-expanded` and `aria-controls` to the show/hide button.
- Keep all rows readable at 375 px width with stacked labels and no horizontal scrolling.
- Do not rely on color alone for status; status text must be visible.
- Keep touch targets at least 44 px high where the action is interactive.

## Error And Empty States

- No goal selected: do not render the panel.
- Goal selected but patient metrics missing: render the collapsed header with disabled or explanatory text, and show missing inputs when expanded.
- Custom goal: show manual prescribed rows and state that no formula applies.
- Backend autofill warning: keep existing warning banner behavior; calculation panel should not hide or replace it.
- Frontend/backend drift: existing dev-only console warning remains. The panel displays the current frontend mirror baseline and current prescribed values.

## Testing

Add focused frontend tests:

- Trace builder returns expected adult weight basis rows for normal and `%IBW > 120` cases.
- Trace builder returns expected target rows for representative goals:
  - `diabetic_control` stage 2: TEE deficit and free sugars conversion.
  - `renal_diet` hemodialysis: fluid base and sodium/protein rows.
  - `malnutrition` severe: refeeding energy start, target range note, potassium/phosphate/magnesium monitoring flags.
  - `custom`: manual/no-formula rows.
- Manual edits produce `Modified` status while calculated baseline remains unchanged.
- Required flagged micros appear even without numeric limits.
- `PrescriptionCalculationPanel` is hidden by default and toggles with accessible `aria-expanded` state.

Run existing nutrition calculation tests to confirm no formula regressions.

## Acceptance Criteria

- Calculation panel is hidden by default.
- User can show and hide calculations from the Nutrition Prescription card.
- Changing goal/stage changes calculated formulas and values.
- Editing prescribed targets updates comparison statuses live.
- All prescribed macros, fluid, and displayed/required micros are represented.
- Flag-only micros are clearly labeled as monitoring flags, not numeric calculations.
- UI matches existing NutriScope theme and remains readable on mobile.
- Existing save/autofill behavior remains unchanged.
