# NutriScope — AI Multi-Agent Engineering & Workflow Guide

This document defines how AI tools, agents, and workflows are used across the project.

It ensures:
- consistent architecture decisions
- reproducible development flow
- capstone-level engineering discipline
- safe multi-agent collaboration
- no lost work between sessions

---

# 1. CORE PRINCIPLE: AI IS A TOOLSET, NOT A DEVELOPER

We do not depend on a single AI model.

We treat AI as specialized tools:

- Claude Code → deep reasoning, architecture, complex debugging
- Codex / lightweight agents → fast implementation, boilerplate, quick fixes
- Antigravity → structured planning and Superpowers workflow execution
- Other chat models → fallback for debugging, explanation, brainstorming

No AI output is trusted without validation.

---

# 2. MCP BOOST SERVER REQUIREMENT (MANDATORY)

Before any AI-assisted development:

- MCP Boost Server must be active
- Laravel Boost MCP must be connected
- Agent must confirm MCP availability before execution

If MCP is not active:
- stop workflow
- do not proceed with implementation

---

# 3. SUPERPOWERS WORKFLOW (STRICT ENFORCEMENT)

Every AI-driven task must follow this sequence:

1. Brainstorm
   - clarify requirements
   - define constraints
   - identify risks

2. Write Plan
   - step-by-step breakdown
   - files affected
   - expected outputs

3. Approval Gate
   - human must approve plan before execution

4. Execute Plan
   - incremental implementation
   - small verifiable steps

5. Review
   - correctness
   - security
   - performance
   - architecture consistency

No code generation is allowed before a plan exists.

---

# 4. ARTIFACT CONTINUITY SYSTEM (CRITICAL)

All AI work must be saved in:

artifacts/superpowers/

## Required files:

### plan.md
- task breakdown
- assumptions
- risks
- architecture notes
- step-by-step plan

### execution.md
- progress tracking
- completed steps
- partial work checkpoints
- current state of implementation

---

## Purpose:

This system ensures:
- work is resumable across AI tools
- no dependency on session memory
- safe model switching
- no loss of progress

---

# 5. MULTI-AGENT CONTINUITY SYSTEM

AI sessions are temporary and interchangeable.

Any agent must be able to resume work using:

- artifacts/superpowers/plan.md
- artifacts/superpowers/execution.md
- project /docs directory

---

## Resume protocol:

When switching AI tools or models:

1. Load plan.md
2. Load execution.md
3. Identify last completed checkpoint
4. Continue from that point
5. Do not restart from scratch

---

# 6. AI TOOL RESPONSIBILITY MODEL

## Claude Code
Use for:
- architecture design
- complex backend logic
- multi-file refactoring
- debugging systemic issues

Avoid for:
- trivial edits
- simple UI adjustments

---

## Codex / lightweight agents
Use for:
- boilerplate generation
- repetitive code
- fast implementation tasks

Risk:
- may ignore architecture constraints
- requires review

---

## Antigravity workflow system
Use for:
- structured planning
- Superpowers execution
- task tracking
- milestone control

Required for all multi-step features.

---

# 7. AI FAILURE RECOVERY SYSTEM

If any AI tool:
- runs out of quota
- becomes unavailable
- stops mid-task

Then:

1. Stop execution immediately
2. Save current progress to execution.md
3. Update plan.md with completed steps
4. Switch AI tool or model
5. Resume using execution.md checkpoint

---

## Resume command pattern:

Continue from artifacts/superpowers/execution.md

---

# 8. AI PROMPTING STANDARD (ANTI-SLOP RULE)

## Bad prompts:
- fix this
- make it better
- optimize this code

## Good prompts:
- clearly define goal
- include constraints
- specify expected behavior
- include file context

Example:
Identify why this Laravel validation fails and propose a minimal fix without changing architecture.

---

# 9. SECURITY-FIRST AI THINKING

Every AI-generated solution must be validated for:

- input validation
- authentication bypass risks
- exposed secrets
- unsafe database writes
- trust boundary violations

If security is not explicitly addressed → reject output.

---

# 10. PERFORMANCE & SCALABILITY RULES

AI-generated solutions must consider:

- database indexing strategy
- query optimization (avoid N+1 problems)
- caching (Redis for expensive operations)
- minimizing API payload size
- avoiding redundant computation

---

# 11. ARCHITECTURE DISCIPLINE RULE

AI must not:

- introduce new architecture layers without approval
- modify system structure without justification
- add unnecessary services or abstractions

All changes must respect:

- Laravel backend structure
- Next.js frontend structure
- Docker service boundaries

---

# 12. CAPSTONE-LEVEL THINKING STANDARD

Before approving any solution, verify:

- Does it scale beyond demo level?
- Is it maintainable by another developer?
- Is it secure under real usage?
- Is it consistent with existing architecture?

If any answer is no → reject or revise AI output.

---

# 13. GOLD STANDARD RULE

If all AI tools disappear tomorrow:

The system must still be:
- understandable
- debuggable
- maintainable
- extendable

AI is an accelerator, not a dependency.

---

# 14. SYSTEM CONSISTENCY & REUSE RULES

## 14.1 REUSE BEFORE CREATE RULE

Before creating anything new:
- check if it already exists
- reuse existing logic
- extend instead of duplicating

---

## 14.2 BACKEND VARIABLE CONSISTENCY

Never duplicate variables:
- use existing names
- do not create alternate naming systems

---

## 14.3 SINGLE SOURCE OF TRUTH RULE

Every data must have one canonical source:
- database OR API OR service

Never multiple conflicting sources.

---

## 14.4 DATA AVAILABILITY RULE

If data does not exist:
- do not hallucinate
- define where it should come from
- propose valid injection point

---

## 14.5 QUESTION-FIRST RULE

If unclear:
- ask questions first
- do not guess system behavior

---

## 14.6 SHORT SESSION RULE

After completing a task:
- write to artifacts
- end session
- start fresh next task

---

## 14.7 COMPONENT INTEGRATION RULE

All features must integrate with:
- existing backend structure
- existing frontend components
- existing APIs

No isolated features.

---

## 14.8 CONSISTENCY OVER INNOVATION RULE

Consistency is higher priority than clever solutions.

---

# FINAL RULE (REUSE)

If something already exists:

Use it.

Do not recreate it.

Do not duplicate it.

Do not override it.

---

# 15. RESPONSIVE DESIGN RULE

## 15.1 MOBILE-FIRST THINKING

Design starts from mobile, then scales upward.

---

## 15.2 RESPONSIVENESS VALIDATION

Must work on:
- mobile
- tablet
- desktop

No horizontal overflow allowed.

---

## 15.3 TAILWIND RESPONSIVENESS

Use:
- sm:
- md:
- lg:
- xl:

Avoid fixed layouts.

---

## 15.4 FLEXIBILITY RULE

Prefer:
- flexbox
- grid
- responsive widths

Avoid rigid pixel layouts.

---

## FINAL RULE

If it is not responsive, it is not complete.
```
