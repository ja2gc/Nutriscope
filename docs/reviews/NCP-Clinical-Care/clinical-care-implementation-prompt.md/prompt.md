# Clinical Care Implementation Prompt

Use:
- Laravel Boost MCP Server
- /backend/.agents/laravel-best-practices/skills
- Superpowers
- Caveman Mode

Read first:

1. docs/reviews/2026-06-25-clinical-care-workflow-deep-audit.md
2. docs/reviews/2026-06-25-clinical-care-ncp-workflow-audit.md
3. docs/reviews/2026-06-25-clinical-care-current-vs-proposed-gap-analysis.md
4. docs/reviews/2026-06-25-clinical-care-defense-focused-implementation-plan.md

Objective:

Implement ONLY the items marked "Implement Before Defense".

Requirements:

- Follow the implementation order in the plan.
- Use TDD when practical.
- Keep changes minimal and defense-focused.
- Do not redesign architecture.
- Do not implement deferred items.
- Do not implement "Implement If Time Allows" items unless explicitly requested.
- The Implementation Plan is the authoritative implementation scope, the rest were just for reference and analysis purposes.
- Maintain backward compatibility where possible.
- If conflicts exist, follow the Implementation Plan.
- Do not implement findings that are not classified as "Implement Before Defense" unless explicitly instructed.

Before coding:

- Produce an implementation checklist mapped to the gap IDs.
- Identify affected controllers, services, requests, policies, models, migrations, routes, and frontend components.

During implementation:

- Complete one checklist item at a time.
- Run tests after each completed item. use superpowers
- Commit only after the item passes validation.

Definition of done:

- All "Implement Before Defense" gaps are resolved.
- Existing functionality still works.
- Reports are clinically defensible.
- ADIME progression cannot be bypassed through empty records.
- Meal plans respect prescription and restriction requirements.
- Report generation cannot produce misleading final outputs.