## Goal
Restructure `milestones.md` for NutriScope to fully integrate the Superpowers workflow (Plan -> Execute -> Verify -> Review), while preserving already completed tasks and enforcing strict testing rules (Backend = tests pass, Frontend = renders).

## Constraints
1. Must retain all currently completed items (`[x]`) accurately.
2. Must not mark any future items done without verifying backend tests pass and frontend renders.
3. Test verification must explicitly follow Superpowers workflows (e.g., `/superpowers-tdd`, `/superpowers-review`).
4. Must organize the remaining modules (NCP, Food Service, etc.) into actionable Superpowers iterations.

## Known context
- NutriScope is a clinical and operational system with RND, FSS, and Admin roles.
- Phase 0 and Phase 1 (Foundation & Routes) are mostly completed. Phase 1, Milestone 1 & 2 are partially completed.
- Existing milestones are grouped by features with Backend and Frontend separated.
- The Superpowers workflow requires planning before execution, writing tests first (backend), and thorough review.

## Risks
- The `milestones.md` document could become too verbose and unreadable if every single step is overly detailed.
- We might lose the big picture (End-to-End workflow) by focusing too much on individual `/superpowers-tdd` micro-steps.

## Options (2???4)
1. **Option 1:** Add a standard "Superpowers Checklist" template under each existing milestone (e.g., 1. `/superpowers-write-plan`, 2. `/superpowers-execute-plan`, 3. `/superpowers-review`). Keep the feature grouping.
2. **Option 2:** Rewrite the entire document into "Superpowers Epochs", converting each feature into an "Epoch" containing specific "Iterations" that enforce the Plan -> Test -> Build -> Review cycle.
3. **Option 3:** Prepend a "Workflow Rules" section at the top of `milestones.md` detailing the required Superpowers steps, and modify the uncompleted tasks to explicitly group Backend and Frontend into a unified "Superpowers Iteration".

## Recommendation
**Option 1** with elements of **Option 3**. We will add a strong "Rules & Workflow" header at the top. This preserves the existing logical feature grouping (Milestones 1-10) while explicitly injecting the Superpowers workflow steps (Plan, Backend TDD/Build, Frontend Build, Review) into the checklist for all uncompleted milestones.

## Acceptance criteria
1. `milestones.md` is updated and restructured.
2. Existing `[x]` marks remain intact.
3. The rules (tests pass, renders, superpowers workflow) are prominently displayed at the top.
4. Each incomplete milestone is restructured to include explicit Plan, Execute, and Review steps based on Superpowers workflows.
