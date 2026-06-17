# 08 — Open Questions

Minor, none blocking the documentation pass.

1. **Cycle re-activation across weeks.** Can a single cycle be activated for multiple future
   weeks (each with its own `week_start_date` + population log), or one activation only?
   - Lean: allow re-activation per week. Resolve during Phase A implementation.

2. **Event scope (single vs multi-day).** Are events always single-day, or can one event span
   multiple days/meals?
   - Lean: per-day `is_event` covers both (multi-day = several flagged days). Revisit only if
     events need a named header/grouping across days. See [06](06-events-and-cashbook.md).

3. **`servings_override` retirement.** Under per-day `estimate_population`, is
   `menu_cycle_days.servings_override` still needed as a distinct per-meal override, or fully
   redundant?
   - Lean: redundant — remove. Confirm no meal-level (vs day-level) override requirement exists.
     See [01](01-population-redesign.md) / [03](03-schema-business-logic.md).

4. **Single-ingredient path.** Model single-ingredient entries as a real recipe (servings=1) or
   reuse the existing `item` branch in `MenuCycleCostService::aggregate()`?
   - Lean: real recipe (servings=1) for one consistent code path. See [05](05-missing-functionality.md).

## Resolved (recorded for traceability)

- **Scope of this pass:** documentation only — no code/schema changes.
- **Weekday picker "to before from":** disallow in UI (from ≤ to, no wraparound).
- **Migrations:** fresh rebuild OK (`migrate:fresh --seed`); FS data is disposable.
- **Calendar tie:** cycle = reusable Mon–Sun pattern; activation anchors to a real Monday
  `week_start_date`; dated output (lists, logs) resolves to real calendar dates.
- **Dietary Cash Book:** keep (auto-derived); fix the three gaps in [06](06-events-and-cashbook.md).
