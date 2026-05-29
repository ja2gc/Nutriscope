---
name: superpowers-plan
description: Writes an implementation plan with small steps, exact files to touch, and verification commands. Use before making non-trivial changes.
---

# Planning Skill

## When to use this skill
- any multi-file change
- any change that impacts behavior, data, auth, billing, or production workflows
- any debugging that needs systematic isolation

## Planning rules
- Steps should be **small** (2–10 minutes each).
- Every step must include **verification**.
- Prefer **incremental deliverables** (avoid “big bang” edits).
- Identify **rollback** and **risk controls** early.
Always TDD:
- write failing test
- run test must fail
- minimal code
- run test must pass
- commit

## Plan format (use this exact structure)
### Goal
Convert spec to executable steps for dev with no context
Assume skilled dev no system knowledge
Prioritize TDD DRY YAGNI small steps frequent commits

### Assumptions
### Plan
1. Step name
   - Files: `path/to/file.ext`, `...`
   - Change: (1–2 bullets)
   - Verify: (exact commands or checks)
2. ...

### Risks & mitigations
### Rollback plan

# SELF CHECK
- all spec covered
- no TODO/TBD/vague steps
- consistent names/types
- every step has code + commands
- fix inline


refer to laravel boost for guides on how we can plan efficiently