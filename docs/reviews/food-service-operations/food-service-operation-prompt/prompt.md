# Food Service Implementation Prompt

Use:
- Laravel Boost MCP Server
- /backend/.agents/laravel-best-practices/skills
- Superpowers
- Caveman Mode

Read first:

1. docs/reviews/food-service-operations/2026-06-26-food-service-workflow-deep-audit.md
2. docs/reviews/food-service-operations/2026-06-26-food-service-workflow-audit.md
3. docs/reviews/food-service-operations/2026-06-26-food-service-current-vs-proposed-gap-analysis.md
4. docs/reviews/food-service-operations/2026-06-26-food-service-defense-implementation-plan.md

The Implementation Plan is the only authority on what gets built and in
what order. All other documents are reference and context only.

Objective:

Implement the full Food Service plan in sequence:

- Phase 1: all items classified as Implement Before Defense
  (FS-GAP-001 through FS-GAP-020 as ordered in the plan)
- Phase 2 onwards: the expanded C+B/D+E redesign phases in sequence
  (FS-RED-001 through FS-RED-017 across Phases 2-10)

Do not skip phases.
Do not start a phase until the previous phase is fully done, tested,
and verified.
Do not implement Implement If Time Allows or Defer Until After Defense
items unless explicitly requested.

Requirements:

- Follow the implementation order and phase sequence in the plan exactly.
- Use TDD when practical.
- Keep changes minimal and defense-focused within each phase.
- Do not redesign architecture beyond what the plan specifies.
- The Implementation Plan is the authoritative scope. the rest were just for reference and analysis purposes.
- Maintain backward compatibility where possible.
- If conflicts exist between documents, follow the Implementation Plan.
- Follow all Expanded Scope Guardrails in the plan — do not violate any
  of them.
- Follow all Resolved Decision Locks in the plan — do not reopen any of
  them.
- Do not implement findings that are not classified as "Implement Before Defense" unless explicitly instructed.
- Implement ONLY the items marked "Implement Before Defense".

Before coding:

- Produce a full implementation checklist covering all phases, mapped
  to gap IDs (FS-GAP-* and FS-RED-*).
- Identify affected controllers, services, requests, policies, models,
  migrations, routes, and frontend components for each phase.
- Flag hard dependencies between phases before starting.

During implementation:

- Complete one checklist item at a time.
- Run tests after each completed item. Use superpowers.
- Commit only after the item passes validation.
- Check blast radius after each item — what other pages, components,
  API callers, and tests consume what you just changed.
- After completing each full phase run the full backend suite and
  frontend tsc --noEmit before starting the next phase.

Definition of done:

Phase 1 complete:
- All Implement Before Defense gaps resolved.
- FSS cannot enter RND web pages.
- FSS report access is consistent across all report actions.
- Menu cycles require defensible completeness before activation.
- Active menu cycle cost snapshot cannot diverge from menu rows.
- PO receiving is idempotent and single-effect.
- Diet-list entries cannot duplicate or corrupt served population.
- Meal-prep population and consumption basis is consistent.
- Reversed service days can be recovered and completed again.
- Dashboard no-stock count matches inventory list.
- Reports only appear from defensible source states.
- Dietary cash book browse and generation date basis are aligned.

Phase 2 complete:
- estimate_population and served_population never affect each other.
- Shopping-list-level population cascade works with last-write-wins.
- Menu-linked draft shopping lists cannot overlap on the same date.
- Menu cycle activation blocks on missing population for menu days.
- Shopping list generation blocks on missing population for menu days
  that have items assigned.

Phase 3 complete:
- Shopping list has exactly draft and converted states only.
- RND is the only role that can generate, create, edit, or convert.
- Ingredients tab supports auto-generated and manual lines.
- Supplies tab exists and is included in PO conversion.
- Converted shopping lists are permanently read-only.

Phase 4 complete:
- One shopping list converts into one PO with vendor groups.
- Structural data freezes at conversion permanently.
- RND and FSS can upload receipt and proof photos on vendor groups.
- FSS cannot create, delete, or structurally edit POs.
- PO-converted event fires with correct payload in same transaction.
- PO-completed event fires when all receipts and served population
  requirements are met.
- Old per-vendor PO model is removed from normal workflow.

Phase 5 complete:
- Procurement page shows three-level event drilldown with breadcrumbs.
- Procurement settings tab is removed.
- Menu cycle list is shared RND/FSS with correct active indicator.
- FSS is read-only on menu cycle list and detail.

Phase 6 complete:
- PPA is auto-generated at PO conversion in same transaction.
- Planning columns are frozen at conversion.
- Execution columns update through Phase 2 and freeze at Phase 3.
- FSS cannot access PPA.

Phase 7 complete:
- One fiscal-year allocation per year exists.
- Budget ledger entries are append-only and immutable.
- Remaining balance calculates correctly from allocation and ledger.
- PO-converted event triggers auto-deduction in same transaction.
- No fiscal-year allocation surfaces clear RND warning on PO creation.
- FSS has no budget management UI.

Phase 8 complete:
- Reports page reads stored snapshots only.
- No live preview anywhere in Food Service reports.
- No generate button or manual archive button in redesigned reports.
- Budget report is generated by scheduled month-end job.
- FSS sees only their own accomplishment reports.
- RND sees all Food Service reports and all staff accomplishments.

Phase 9 complete:
- Spend by supplier uses vendor groups not old per-supplier PO rows.
- All graph data returns full selected date range including blank days.
- Phase 2 PO spans show as pending markers not omitted.
- Budget burn uses fiscal allocation and ledger.
- All graphs live in Insights only.

Phase 10 complete:
- Seeded cycles vary menus and costs meaningfully.
- At least three procurement spans have different actual per-head values.
- migrate:fresh --seed is green.
- Full backend suite passes.
- Frontend tsc --noEmit clean.
- No code path allows served_population and estimate_population to
  affect each other.
- No seeded or hardcoded values required for any feature to function
  through normal user flow.