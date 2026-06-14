# Menu Cost Freeze — make menu-derived reports immutable at activation (Spec 6 #1)

- **Date:** 2026-06-14
- **Status:** Approved (user delegated the trigger choice → activation), building.
- **Closes:** Spec 6 #1 (menu-derived reports were live, not frozen).

## Problem

`MenuCycleCostService::forCycle()` costs a menu cycle from the **current** catalog price (`fs_item.unit_cost` ← `purchase_price`). So PPA and Menu Calendar reports for a **past** cycle re-price at today's cost — editing an ingredient's price retroactively changes an old report. This violates the rule: an old report's numbers must reflect the cost as it was; price changes apply forward only.

(PO-derived reports — Cash Book, Procurement, Budget actuals — are already frozen at receipt, so they are out of scope.)

## Design — freeze on activation

A menu cycle's plan is committed when it is **activated** (the lock moment; drafts stay editable/live). At activation we snapshot the costed figures and store them on the cycle; reports read the snapshot.

- **Schema:** `menu_cycles.cost_snapshot` (JSON, nullable) + `cost_snapshot_at` (timestamp, nullable).
- **`MenuCycleController::activate`:** after activating, store `cost_snapshot = MenuCycleCostService::forCycle($cycle)` (computed live at that instant) + `cost_snapshot_at = now()`.
- **`MenuCycleCostService::forReport(MenuCycle $cycle): array`:** returns `$cycle->cost_snapshot` if present, else `forCycle($cycle)` (live). **Report generators only.**
- **`forCycle()` stays live, unchanged** — the planner (`compute`), insights, and consumption/procurement keep current-data behavior (you plan/buy at today's price; only the *filed report* freezes).
- **PPA + Menu Calendar generators** switch their cost call from `forCycle` → `forReport`.

### Why activation (not day-complete / explicit lock)
Activation already exists, is the point where the plan is committed and procurement/consumption run against it, and cleanly separates "draft = live" from "committed = frozen." Re-activating re-snapshots (latest commit wins).

## Non-goals
- Backfilling snapshots for already-active cycles (they render live until next activation — acceptable; re-activate to freeze).
- Freezing insights/planner (intentionally live).
- Branding/letterhead freeze (user accepted letterhead may stay current; values are what must freeze).

## Testing
- Activate a cycle → `cost_snapshot` stored.
- Edit `fs_item.purchase_price` after activation → `forReport($cycle)` (and the PPA generator's total) **unchanged**; `forCycle($cycle)` reflects the new price (planner stays live).
- Draft cycle (no snapshot) → `forReport` falls back to live.
- Full suite green.
