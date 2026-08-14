# Food Service Operations Design

## Purpose

Correct the existing food-service workflow from catalog setup through recipes, weekly menus, shopping lists, purchase orders, receiving, actual population, budgets, and reports. Reuse current NutriScope modules and data paths. Add only behavior needed to make the workflow accurate, usable, and auditable.

## Scope

Included:

- Ingredient and supply reference catalog
- Food-service recipes and baseline yields
- Weekly menu cycles and reusable templates
- Suggested and manual shopping lists
- Purchase-order release, vendor receiving, and evidence
- Planned versus actual purchasing values
- Actual population served
- Fiscal-year budget checks and reconciliation
- Procurement and accomplishment reports
- Demo seed data, help content, operational docs, developer maintenance guide, and external video storyboard script

Excluded:

- Stock-on-hand, inventory deductions, shortages, leftovers, and pantry balances
- Separate seasoning catalog
- Waste, warehouse, temperature, sanitation, bidding, invoice payment, and accounting workflows
- Per-day estimate entry and per-slot serving overrides
- Event-management, event attendance, and automatic linked-event PO systems
- Formal approval workflows for shopping-list exclusions
- Help-page storyboard tab implementation

## Core Principles

1. One obvious primary action per stage.
2. Values entered once whenever one value can safely drive the span.
3. Preserve planned values; receiving records actual values separately.
4. Require evidence before vendor receiving and final locking.
5. Keep food and supplies as separate procurement tracks.
6. Use existing models, routes, resources, and screens where practical.
7. Documentation and demo data must match tested behavior before completion.
8. Search for and reuse existing services, actions, resources, form controls, tables, upload galleries, status badges, pagination, and audit helpers before creating anything new.
9. Keep one source of truth for scaling, lifecycle, receiving totals, attachment requirements, and budget readiness. UI code consumes these rules; it must not reimplement them independently.

## Low-Code Architecture Guardrails

- Extend `RecipeScaler`, `MenuCycleCostService`, `ShoppingListPopulationService`, `PurchaseOrderLifecycleService`, `ReceivingService`, existing API resources, and existing procurement/menu pages instead of creating parallel workflows.
- Reuse `ImageUploadGallery`, shared buttons, pagination, audit trail, status styling, search controls, and existing service clients.
- Prefer small focused request, action, or service methods for new business rules. Controllers coordinate dependencies and responses; React pages render returned readiness and lifecycle state.
- Do not duplicate completion rules in RND UI, FSS UI, reports, and seeders. Expose one backend readiness result and reuse it everywhere.
- Do not introduce a generic workflow engine, repository layer, event framework, new state-management library, or broad refactor.
- Split an existing large file only when the changed concern has a clear reusable boundary, such as a readiness card or receiving-line editor. Do not reorganize unrelated code.
- Add the fewest schema fields that preserve required distinctions: catalog generation behavior, shopping-row source/exclusion, and PO actual receiving values.

## Roles

### RND

- Maintains catalog, recipes, templates, and weekly menus.
- Generates and reviews shopping lists.
- Adjusts planned purchase quantities, suppliers, and expected prices.
- Excludes calculated lines or adds manual lines.
- Releases reviewed shopping lists as POs.
- May assist with receiving evidence, corrections, and actual-population backfill.
- Reviews budget readiness and final variance.

### FSS

- Views released POs and vendor groups.
- Reviews prefilled actual receiving values.
- Enters actual quantities and prices when purchases differ.
- Uploads receipts and proofs of purchase.
- Enters an OR number when available; OR remains optional.
- Explicitly marks each vendor group received.
- Records actual population served for menu-generated food service.

## Catalog

`fs_items` remains a reference catalog, not a stock ledger. Each item is an ingredient or supply.

Ingredients gain `include_in_generated_lists`, defaulting to true. False is displayed as `Purchase only when needed`. This applies to any bulk pantry ingredient, not only literal seasonings.

Recipe ingredient rows show a read-only purchasing badge inherited from the catalog. Recipes retain exact ingredient quantities regardless of purchasing behavior.

## Recipes and Scaling

A recipe stores its baseline yield and baseline ingredient quantities. Scaling uses:

```text
factor = estimated servings / recipe baseline servings
scaled ingredient = baseline ingredient quantity * factor
```

The existing `RecipeScaler` remains the scaling primitive.

## Weekly Menus

Manual creation, template loading, and duplication create an independent working menu. Menu creation asks only for week start and menu structure.

Default name:

```text
Weekly Menu — August 17–23, 2026
```

Users may replace the generated name. Changing week start updates the name only while the name remains auto-generated.

Templates store structure only: weekdays, meal slots, recipes or individual items, and base item quantity. They do not store purchase estimates, actual populations, shopping lists, or PO data.

## Menu Food Profile

Before a shopping estimate exists, a menu-slot food profile shows recipe baseline yield and quantities plus `Purchase estimate: Not set`. It must not silently scale to one serving.

After a suggested shopping list covers the date, the profile shows the uniform estimate and scaled quantities. Lowest-risk implementation may reuse existing `menu_cycle_days.estimate_population` fields by cascading the one shopping-list estimate to covered menu days. No per-day or per-slot estimate input is exposed.

`servings_override` is not used by new grocery calculations and is removed from user-facing planning controls. Existing historical values remain readable.

## Shopping-List Generation

The generate form requires:

- Start date
- End date
- Estimated servings per service day

The value is entered once and applied uniformly to all scheduled menu entries in the selected span. Generation is blocked when any selected date lacks a covering menu or menu items.

Ingredients with `include_in_generated_lists = false` are skipped.

## Shopping-List Rows

Rows have a persistent source: `generated` or `manual`.

### Generated Rows

- Ingredient and calculated requirement remain read-only.
- Planned purchase quantity, purchase unit, supplier, and expected price remain editable while draft.
- User may exclude a line from the PO with an optional short note.
- Excluded lines remain visible and keep their calculated requirement.

### Manual Rows

- User selects an ingredient catalog item and supplies quantity, unit, supplier, and expected price.
- Rows remain editable and removable while draft.
- Population or menu recalculation never deletes or rescales them.
- Purchase-only-when-needed pantry items use this path.

Suggested food lists accept manual ingredient rows. Supplies remain in supplies lists.

## Manual Purchases and Events

Manual food lists and manual supplies lists are both supported. They require no recipe or menu slots.

Mixed events use two lists with a shared purpose name:

```text
Nutrition Month — Food
Nutrition Month — Supplies
```

No event parent record or automatic linked-PO subsystem is introduced.

## PO Readiness and Release

Before release, require:

- Non-empty shopping list
- Uniform estimate for suggested food lists
- Full selected-date menu coverage
- Supplier assignment for every included line
- Fiscal-year budget
- Planned total within remaining available budget

One primary action, `Create and release PO`, converts the shopping list, freezes planned structure, creates vendor groups, and makes the PO executable by FSS.

User-facing PO identity displays the existing unique PO number plus shopping-list purpose. Sequential numbering is not required by this design.

## Planned and Actual Purchase Values

PO items preserve planned values copied from the shopping list:

- Calculated/base requirement
- Planned purchase quantity and unit
- Expected unit price and total

Receiving adds actual values:

- Actual received quantity, supporting three decimal places
- Actual unit price, supporting two decimal places
- Effective actual total, calculated as quantity times unit price

Actual fields are prefilled from planned values. Users edit only differences.

Normal calculation uses editable actual quantity and actual unit price with a calculated total. A secondary `Calculate quantity from receipt total` action handles variable-weight receipts where total and unit price are known. No permanent multi-mode calculator is added.

## Vendor Receiving and Evidence

Each vendor group must have:

- Supplier
- Reviewed actual values for every line
- At least one receipt attachment
- At least one proof-of-purchase attachment
- Explicit `Mark vendor received` confirmation

OR number is optional and never blocks receiving, completion, locking, or final reports.

Uploading a receipt or proof does not automatically mark the vendor received. Evidence upload and receiving confirmation remain separate actions.

Before PO completion, actual values and evidence may be corrected. A short correction reason is required only when changing a previously confirmed actual value. Completed POs are final locked.

Confirmed receiving updates catalog reference price and latest supplier from actual values, subject to the existing supplier lock.

## Completion

### Suggested Food PO

Complete only when:

- Every vendor group satisfies receiving and evidence requirements.
- Actual population served exists for every service date in the shopping span.
- Final actual budget variance is valid within the fiscal-year budget.

### Manual Food PO

Complete after all vendor groups satisfy receiving and evidence requirements. No served-population requirement and no cost-per-served-patient metric.

### Supplies PO

Complete after all vendor groups satisfy receiving and evidence requirements. No served-population requirement.

## Actual Population Served

Actual population is entered after service by FSS or RND. It does not recalculate a released PO. Suggested Food PO completion requires every covered date. Values lock when the related PO completes.

Meal Prep completion must not leave required actual population blank.

FSS Accomplish entries remain separate from receiving and actual population.

## Budget

Release checks planned total against remaining budget. Receiving calculates final actual total. Completion reconciles the difference. A PO cannot silently complete when actual expenditure exceeds available budget; RND must use the existing budget-adjustment path.

## Reports

Final reports are eligible only after PO completion and final locking. Earlier states may produce clearly marked previews, not final archived evidence.

Rename `Actual cost/head/day` to `Food purchase cost per served patient-day`.

Procurement Pack must include private-storage receipt and proof images for every vendor group. OR displays when present and `Not provided` when absent.

Prepared-report cache and manual archive remain distinct concepts in documentation.

## Menu Date Resolution

FSS resolves the menu covering the requested service date. Publishing a future menu must not displace the current week. User-facing action is `Publish Weekly Menu`; date coverage, not a single global active flag, determines operational visibility.

## UX Rules

- Keep existing green/warm visual system.
- Use visible labels, inline errors, and 44px minimum controls.
- One primary CTA per stage.
- Separate calculated, planned, and actual values with plain labels.
- Use compact readiness checklists with direct links to missing fields.
- Hide advanced or uncommon actions until relevant.
- Preserve existing 10-item pagination conventions.

## Documentation Deliverables

Implementation is incomplete until these match tested behavior:

- `docs/modules/Flowcharts/Food Service Operations.md`
- `docs/modules/fss.md`
- `docs/STORYBOARD.md`
- Relevant `frontend/lib/helpContent.ts` entries
- New developer maintenance guide describing models, services, controllers, resources, UI files, lifecycle rules, tests, and extension points
- External Documents-folder video storyboard script with user actions, narration, expected results, budget effects, and report outputs

The Help-page storyboard tab is explicitly deferred.

## Demo Data

Update food-service seeders so demo dates are relative to the current week and demonstrate:

- Current and future weekly menus
- Templates
- Auto and purchase-when-needed ingredients
- Suggested list estimate and scaled rows
- Manual rows and exclusions
- Food and supplies manual lists
- Released and received vendor groups
- Optional OR behavior
- Receipt/proof evidence
- Actual population and completed-report outcomes

Seed data must remain deterministic enough for tests and idempotent where existing seeders require it.

## Acceptance Criteria

1. No generated list uses a silent serving count of one.
2. One estimate entered at generation drives covered recipe scaling and profile display.
3. Manual rows and exclusions survive recalculation.
4. Purchase-only-when-needed ingredients stay out of automatic generation.
5. PO release blocks missing suppliers and insufficient remaining budget.
6. Receiving defaults actual values from planned values and supports variable-weight corrections.
7. Receipt and proof are required per vendor group; OR is optional.
8. Evidence upload never auto-confirms receiving.
9. Completion rules differ correctly for suggested food, manual food, and supplies.
10. Final reports use locked actual values and include private attachments.
11. Catalog references update only after confirmed receiving.
12. Current-date menu resolution works when future menus exist.
13. Seeded demo supports a start-to-finish walkthrough.
14. Operational docs, help, developer guide, and external video script match implementation.
15. Backend, frontend, formatting, type, build, and relevant end-to-end verification pass before push.
