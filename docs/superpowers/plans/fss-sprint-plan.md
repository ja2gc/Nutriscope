# FSS Sprint Plan — Execution

> **Source of truth for scope:** [`docs/modules/fss.md`](../../modules/fss.md). This file is the **execution plan** (exact tasks + file references) — an agent should read `fss.md` for *what/why*, then this file for *what to do and where*.
> **For backend tasks:** consult `backend/.agents/skills/laravel-best-practices/skills.md` first (follow its own "How to Apply" routing; delegate reading `rules/` to a sub-agent).
> **Conventions:** work on `main`; **NO `Co-Authored-By`** (author = jared). Verify backend `cd backend && php artisan test`; frontend `cd frontend && npx tsc --noEmit`.
> **Target platform:** FSS app is React Native (Expo) per the original architecture (§3 below); the **reconciliation tasks (Task R*) are backend, platform-agnostic** and must land regardless.

> **⚠ Cross-role:** Tasks touching budget-per-head / population affect **RND** too (shared food-service module) — see `fss.md` §3 **[cross-role]**. Don't treat these as FSS-local.

---

## Task R0: Reconciliation — revert off-scope code (`baf8fbf`→HEAD + Phase A)

The client's change-of-mind notes (now folded into `fss.md`) postdate both Phase A and the codex/antigravity work, so some shipped code now contradicts scope. **Verdict: revert off-scope, keep aligned** (decided in conversation). Audit each, then act.

### R0.1 — Remove FSS PO/shopping-list authoring (`fss.md` §5)
- [ ] **Revert `storeItem`:** delete `ShoppingListController::storeItem` in [`backend/app/Http/Controllers/FSS/ShoppingListController.php`](../../../backend/app/Http/Controllers/FSS/ShoppingListController.php) and the route `Route::post('shopping-lists/{shopping_list}/items', ...)` in [`backend/routes/api.php`](../../../backend/routes/api.php). **Alternative if RND still needs manual line-add:** move the route under a `role:RND`-gated group instead of deleting (confirm with planning before choosing).
- [ ] Confirm no remaining FSS-reachable *create/edit* path for purchase orders. FSS keeps **only** `POST /purchase-orders/{id}/attachments` + `DELETE /purchase-order-attachments/{id}` (receipts/proof).
- [ ] Update/trim any test asserting FSS can author shopping-list items (`backend/tests/Feature/FoodServiceOpsTest.php`).

### R0.2 — De-surface FSS suppliers / budget / insights (`fss.md` §6)
- [ ] **Re-home, don't delete, `ProcurementCostEfficiencyService`:** [`backend/app/Services/FSS/ProcurementCostEfficiencyService.php`](../../../backend/app/Services/FSS/ProcurementCostEfficiencyService.php) computes procurement-cash ÷ served-heads. It must **not** back an FSS insights endpoint. Keep the math only where it serves the **[cross-role]** budget-per-head calc (Task R1); if it isn't wired into the budget/reporting path, move it there or drop it. Resolve `backend/tests/Feature/ProcurementCostEfficiencyServiceTest.php` accordingly.
- [ ] Confirm no FSS-exposed supplier CRUD / budget planned-vs-actual / insights analytics surface remains for `role:FSS`. (Inventory stays — §6.)

### R0.3 — Reports scope (`fss.md` §8)
- [ ] FSS report scope = **accomplishment report only** (Task A* below). Frontend Reports browser is already RND-only (`(rnd)/reports` + `/api/rnd/reports/*`) — **nothing to trim on FSS frontend**. Backend: confirm no FSS-only generator exists beyond the accomplishment report.

### R0.4 — Keep (aligned) — do NOT revert
- [ ] **Keep** `add_menu_cycle_id_to_budgets` + `drop_population_and_budget_per_head_from_menu_cycles` migrations, `Budget::menuCycle`, and the `MenuCycle`/`MenuCycleController::compute` changes (population moved cycle→budget). This is the population redesign and matches `fss.md` §3.

---

## Task R1: Budget-per-head calc — confirm + align to spec (`fss.md` §3) [cross-role]

**Files:** [`backend/app/Services/BudgetActualService.php`](../../../backend/app/Services/BudgetActualService.php), `backend/app/Services/FSS/ProcurementCostEfficiencyService.php`, tests.

- [ ] **Confirm** `BudgetActualService` matches spec: planned cap = `budget_per_head_day × population` (estimate); per-day `per_head` = `total_value ÷ served_population`; `per_head_actual` = Σserved-value ÷ Σserved-heads. (Verified present 2026-06-18 — this task is a guard + test, not a rewrite.)
- [ ] **Span cost/head (RND-facing):** ensure a span figure of **total food cost ÷ total patients served** over the PO/menu-plan span is exposed for RND cost-per-head reporting (the figure the client described). Decide whether this is `per_head_actual` (served-value basis) or the procurement-cash basis (`ProcurementCostEfficiencyService::forSpan`, which currently divides by **avg** served, not total) — reconcile the two definitions and pick one for the report.
- [ ] Regression test: a budget with a known cycle, served days, and POs returns the documented planned cap, daily per-head, and span cost/head.

---

## Task A: Accomplishment Report — per-staff duty + diet-list capture (`fss.md` §4)

Replaces the standalone cleaning log. Decided model: **per-staff tasks, day-level headcount** (diet-list rows sum into `meal_prep_logs.served_population`; no double-entry).

> **Investigate-first (already scoped, see `fss.md` §4):** capture surface today is a single nullable `served_population` int on `meal_prep_logs`, set via `POST menu-cycles/{id}/complete-day` → [`MealPrepLogController@complete`](../../../backend/app/Http/Controllers/FSS/MealPrepLogController.php). There is **no** per-ward/per-staff breakdown. The tasks below add it.

### A1 — Data capture (backend)
- [ ] **Migration:** new table (e.g. `diet_list_counts`) — `service_date`, `menu_cycle_id` (nullable FK), `ward` (string), `fss_user_id` (FK users), `population` (int), and the seven task flags + on/off-duty marker for that staff-day (booleans or a small enum/json). Index `(service_date, menu_cycle_id)`.
- [ ] **Endpoint:** `POST /diet-list-counts` (FSS submits their ward's count + their task marks for the day) + `GET /diet-list-counts?from&to&menu_cycle_id` (week view). Add to the `/fss` route group; **writes limited to the submitting staff**.
- [ ] **Form Request:** validate ward, population (`min:0`), service_date, task flags. Follow the laravel-best-practices Form Request routing.
- [ ] **Aggregation:** on submit (or on `complete-day`), set `meal_prep_logs.served_population` for that service_date = **Σ** of that date's `diet_list_counts.population`. `MealPrepLogController@complete` should read this sum rather than accept a hand-typed `served_population` (keep the manual field only as an override/fallback if a day has no diet-list rows — confirm).
- [ ] **Tests:** three wards × counts sum into the day's `served_population`; per-staff task flags persist; auth (FSS-only, self-scoped writes).

### A2 — Report generator
- [ ] Build the accomplishment-report generator to emit the per-staff weekly sheet (7 task rows × days, ✓/number/off-duty cells, signature blocks: Prepared/Noted/Approved) from `diet_list_counts` joined with the day's summed headcount. Match the form layout in `docs/Nutriscope Forms/accomplishment report for fss.jpg`. Slot into the existing report generator pattern (`backend/app/Services/.../Generators/`).
- [ ] **Tests:** generator output for a seeded week.

### A3 — App UI (Accomplishment / Prep & Clean tab)
- [ ] Daily entry: pick ward(s), enter diet-list count, tick the day's tasks / mark off-duty. Show the day's running summed headcount.
- [ ] Keep the meal-prep "mark served" action adjacent (the two anchor daily tasks).

---

## App Architecture & Navigation (scope-corrected)

**Tech Stack:** React Native (Expo), NativeWind, React Query, Expo SecureStore (Sanctum token).

**Bottom Tabs:**
1. **Dashboard** (Home)
2. **Prep & Accomplishment** (daily execution: meal-prep mark-served + accomplishment/diet-list entry — §3, §4)
3. **Inventory** (stock only — §6; **no** suppliers tab)
4. **Procurement** (receiving: list POs, upload receipts/proof only — §5)

Modals: camera/upload for receipts (UX mirrors RND's receipt-upload flow — same endpoint/field shape), Settings/Profile.

---

## Page Checklists

### Tab 1: Dashboard
- [ ] KPI cards: meals left to prep, accomplishment/diet-list pending, POs awaiting receipt, inventory no-stock.
- [ ] **Announcements feed (read-only, `visibility=FSS|All`), placed BELOW the dashboard content** — mirror RND's feed; reuse the shared announcement component (`fss.md` §7).
- [ ] React Query daily-summary fetch + pull-to-refresh.

### Tab 2: Prep & Accomplishment
- [ ] **Meal Prep:** today's meals from the *active* menu cycle (read-only cycle); mark-served → `POST /menu-cycles/{id}/complete-day`; handle shortfall (`allow_shortfall`) with a clear modal.
- [ ] **Accomplishment:** Task A3 entry UI.
- [ ] **Note (Bulk vs Trays):** FSS cooks bulk; individual patient trays come from RND's `PatientMenuPlan` PDF. **Do not** build tray-level UI.

### Tab 3: Inventory
- [ ] `FlashList` of ingredients/supplies with stock level, unit, status badge (red/green).
- [ ] Inline stock-adjust modal (add/deduct); search/filter. **No suppliers tab** (§6).

### Tab 4: Procurement (receiving)
- [ ] `SectionList` of POs by status (Ordered, Received) — **read-only list**; FSS does **not** create/edit POs (§5).
- [ ] Camera/upload to attach receipts/proof → `POST /purchase-orders/{id}/attachments` (multipart).
- [ ] "Mark received" triggers backend restock (if that flow is RND-owned, surface as read-only status instead — confirm against R0.1).

---

## Self-Review notes
- Every backend task: `php artisan test` green before commit. Frontend/app: type-check + a device/sim check.
- Don't re-litigate retracted findings (`complete-day missing`, `ai_usage_logs never written`) — verified false.
</content>
