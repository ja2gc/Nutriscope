# Workflow and Scope Rules

## Interpret requests literally

- “Investigate/audit/review/diagnose” = read-only + evidence report. No implementation.
- “Plan only” = requested plan only. No app/data/config/assets edits.
- Honor phases; stop at gates.
- Implementation authorized → continue through proportional verification + requested delivery. First fix/test not finish.
- Later addition extends unfinished task unless clearly replacing it.
- Do not re-ask settled/discoverable/convention-safe decisions.
- Ask one concise question only when choice materially changes scope/risk/data/security/external state.
- “Do not reply” = silence.

## Working method

- Before edit: root cause + all consumers.
- Consider 2–3 small approaches; choose safest/simple compatible one.
- Reuse patterns. No unrelated refactor.
- No duplicate plans/handoffs/flowcharts/components/services/engines/storage/provider adapters/state machines.
- Three failed fixes → stop stacking; reassess root cause.
- Code = authority. Conflicting docs: fix only in scope, else report.
- External fact → official current source, not stale memory.

## Communication

- Tool work: short opening update; update during long work.
- Outcome first; no tool diary.
- State assumptions/blast radius/blockers/command mutation.
- Explain visible pages/actions, not routes only.
- Existing source ≠ rendered/live component.
- Never call unfinished work complete. Self-review scope + reasoning.
