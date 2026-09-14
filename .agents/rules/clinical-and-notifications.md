# Clinical, Appointments, and Notifications

## NCP and ADIME

- One current `draft`/`active` ADIME cycle. Terminal records → separate Past Records, 2/page.
- New cycle never mutates history. Complete/protect explicit; requires complete Assessment + Diagnosis + Intervention.
- Appointment time never auto-starts/finishes. Explicit start binds current cycle. Visit finish ≠ cycle finish.
- Preserve source/purpose/status/administering RND/worked-on/newly-completed/cancel reason/reschedule links.
- Shared RND clinical access valid. Forbidden actor tests use Admin/FSS.
- Clinical attribution = qualifying save only; not view/preview/download/export/audit read. None → “No action recorded.”
- Historical meal plans/nutrient snapshots stay historical.

## Meal-plan behavior

- Goal authority: `docs/logic/intervention-goals.md`, unless approved current-code change supersedes.
- Template copy preserves every child + snapshot; owner scope on list/view/load/delete.
- Prescription scale = practical quantity only; never insert/substitute food.
- Snack-free redistributes targets when allowed; keep liver frequent-intake exception.
- Use practical g/cup/piece, not vague serving multiplier. Fluid guidance stays plan-level.

## Notifications

- Deep link prefers public `source_uuid`; fallback only per contract.
- Open = mark read + exact record. Read ≠ resolve.
- Informational/resolved → dismissible.
- Unresolved action-required → visible, Dismiss disabled until workflow resolves.
- Completing work anywhere resolves notification; direct notification entry not required.
- Reminders use real idempotent command/service. Never seed fake reminder state.
- Shared behavior applies all relevant roles, not RND only.
