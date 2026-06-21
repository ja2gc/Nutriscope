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

- [x] **Confirm** `BudgetActualService` matches spec: planned cap = `budget_per_head_day × population` (estimate); per-day `per_head` = `total_value ÷ served_population`; `per_head_actual` = Σserved-value ÷ Σserved-heads. (Confirmed unchanged — `dailySeries()` lines 77/113/133. Commit `384c992`.)
- [ ] **Span cost/head (RND-facing):** ensure a span figure of **total food cost ÷ total patients served** over the PO/menu-plan span is exposed for RND cost-per-head reporting (the figure the client described). Decide whether this is `per_head_actual` (served-value basis) or the procurement-cash basis (`ProcurementCostEfficiencyService::forSpan`, which currently divides by **avg** served, not total) — reconcile the two definitions and pick one for the report. *(Still open — regression test asserts the served-value basis; procurement-cash reconciliation deferred to R2.3.)*
- [x] Regression test: a budget with a known cycle, served days, and POs returns the documented planned cap, daily per-head, and span cost/head. (`backend/tests/Feature/BudgetActualServiceTest.php`, commit `384c992`.)

---

## Task R2: Remove / retire dead & off-scope FSS backend

All the controllers below live in `App\Http\Controllers\FSS\` but are registered **only** in the shared `/fss` group (middleware `role:FSS,RND`), so RND authors its planning artifacts through the same controllers. The rule: **shared-with-RND ⇒ RND-gate (do not delete); FSS-only-and-dead ⇒ delete.** Before deleting any class, grep the repo for remaining references. For table removal, add a new **drop migration** (never edit a migration that has run) with a reversible `down()` (laravel-best-practices §16). This supersedes/subsumes R0.1's "delete vs gate" question.

### R2.1 — Delete (FSS-only and dead, replaced by Accomplishment Report — §4 / R0.3) — DONE (commit below)
- [x] Removed `CleaningLogController` + `apiResource('cleaning-logs')` route, `CleaningLog` model + its Form Requests + Resource, added drop migration `2026_06_21_..._drop_cleaning_logs_table`. `CleaningLogTest` rewritten to assert the routes are 404.

### R2.2 — RND-gate (shared; RND still needs these for planning) — DONE
- [x] Suppliers, PO `store`/`update`/`destroy` + `generatePos`, shopping-lists `apiResource` + `generate` + item add/update/delete, and `FsItemController@update` moved under `role:RND`. FSS retains PO `index`/`show`, attachment upload/delete, and `fs-items/{id}/price-trend` (read). `FoodServiceOpsTest` updated.

### R2.3 — Remove FSS routes + delete if unreferenced (off-scope analytics — §6 / R0.2) — DONE
- [x] Three FSS `insights/*` routes removed; `InsightsController` deleted (unreferenced). `ProcurementCostEfficiencyService` kept (still referenced by its own test); re-homing its math to budget/reporting (R1) remains open.

### R2.4 — Tests (scope is enforced, not just documented) — DONE
- [x] FSS token → **403** on supplier/PO/shopping-list-item/fs-item writes + insights; RND token → **2xx** on authoring; cleaning-log routes → **404**. Full suite 570 green.

---

## Task A: Accomplishment Report — per-staff duty + diet-list capture (`fss.md` §4)

Replaces the standalone cleaning log. Decided model: **per-staff tasks, day-level headcount** (diet-list rows sum into `meal_prep_logs.served_population`; no double-entry).

> **Investigate-first (already scoped, see `fss.md` §4):** capture surface today is a single nullable `served_population` int on `meal_prep_logs`, set via `POST menu-cycles/{id}/complete-day` → [`MealPrepLogController@complete`](../../../backend/app/Http/Controllers/FSS/MealPrepLogController.php). There is **no** per-ward/per-staff breakdown. The tasks below add it.

### A1 — Data capture (backend) — DONE (commit `93920e6`)
- [x] **Migration:** `diet_list_counts` — `service_date`, `menu_cycle_id` (nullable FK), `ward`, `fss_user_id` (FK users), `population` (uint = row-4 headcount), seven task flags + `off_duty`. Index `(service_date, menu_cycle_id)`.
- [x] **Endpoint:** `POST /fss/diet-list-counts` (self-scoped — `fss_user_id` forced to `Auth::id()`) + `GET /fss/diet-list-counts?from&to&menu_cycle_id`. In the `/fss` group.
- [x] **Form Request:** `StoreDietListCountRequest` — ward, population `min:0`, service_date, 8 booleans.
- [x] **Aggregation:** `ConsumptionService::completeDay` prefers Σ of the date's `diet_list_counts.population`; hand-typed `served_population` is fallback when no rows exist. Controller also syncs the sum onto an existing `meal_prep_logs` row on submit.
- [x] **Tests:** 8 in `DietListCountTest` (3-ward sum, flag persistence, self-scoped write, complete-day prefers sum) + 5 meal-prep regression — all green.

### A2 — Report generator
- [ ] Build the accomplishment-report generator to emit the per-staff weekly sheet (7 task rows × days, ✓/number/off-duty cells, signature blocks: Prepared/Noted/Approved) from `diet_list_counts` joined with the day's summed headcount. Match the form layout in `docs/Nutriscope Forms/accomplishment report for fss.jpg`. Slot into the existing report generator pattern (`backend/app/Services/.../Generators/`).
- [ ] **Tests:** generator output for a seeded week.

### A3 — App UI (Accomplishment / Prep & Clean tab)
- [ ] Daily entry: pick ward(s), enter diet-list count, tick the day's tasks / mark off-duty. Show the day's running summed headcount.
- [ ] Keep the meal-prep "mark served" action adjacent (the two anchor daily tasks).

---

## Task D: FSS Dashboard — live KPI endpoint + Dashboard tab (`fss.md` §1)

No FSS dashboard controller or endpoint exists today, so the KPIs must be built from real queries — **never hardcoded or seeded**. Each card maps to a count/figure derived from existing tables.

### D1 — Backend (`GET /fss/dashboard/summary`) — DONE (commit `8804495`)
- [x] `App\Http\Controllers\FSS\DashboardController@summary` + `Route::get('dashboard/summary', ...)` in the `/fss` group; logic in `App\Services\FSS\FssDashboardService`.
- [x] Live figures: `meals_to_log_today` (active cycle's `MenuCycleDay` for today's weekday with no completed `MealPrepLog`), `pos_awaiting_receipt` (`status=ordered` + `whereDoesntHave` receipt/proof attachment), `inventory_no_stock` (`quantity_in_stock <= 0`), `today_service` (today's meals + prepped/shortfall state). No active cycle → zeros, no error.
- [x] **Tests:** 17 in `FssDashboardTest` (insert ordered PO → count 1; attach receipt → 0; no-stock increment; served vs unlogged day). Full suite green.

### D2 — App UI (Dashboard tab)
- [ ] Consume `/fss/dashboard/summary` via TanStack Query with pull-to-refresh; render KPI cards (tabular figures, skeletons while loading).
- [ ] Announcements feed (`visibility=FSS|All`) **below** the cards — reuse the shared announcement presentation (mirror the web [`AnnouncementsBoard`](../../../frontend/components/announcements/AnnouncementsBoard.tsx) payload shape; read-only for FSS).

---

## Task N: Notifications — page, bell badge + PO-awaiting-receipt trigger (`fss.md` §9)

The notification backend is already role-agnostic and reused as-is: `Notification` model + `NotificationService::notify()` + shared endpoints `GET /api/notifications`, `PATCH /api/notifications/{id}/read`, `PATCH /api/notifications/read-all` ([`RND/NotificationController`](../../../backend/app/Http/Controllers/RND/NotificationController.php)), all scoped by `Auth::id()`. Announcement fan-out to FSS already works (`NotificationService::fanOutAnnouncement`, visibility `FSS|All`). **No new endpoints.**

### N1 — Backend (new event only) — DONE (commit `354e317`)
- [x] PO-awaiting-receipt notification via `PurchaseOrderController::notifyFssIfOrdered()` (private helper) called from `store()` and `update()`; transition guard captures previous status so an already-`ordered` PO doesn't re-fire; wrapped in `DB::afterCommit`. Targets all `role=FSS` users (type `po_awaiting_receipt`, source_module `food_service`, source_id = PO id).
- [x] Announcement fan-out to FSS + meal-prep shortfall/variance-to-RND confirmed unchanged.
- [x] **Tests:** 4 in `PoAwaitingReceiptNotificationTest` (ordered create → 1/FSS user; draft → 0; transition → notifies; re-update ordered → 0). Full suite 561 green.

### N2 — App UI (Notifications screen + header bell)
- [ ] Notifications screen mirroring the web [`(rnd)/notifications`](../../../frontend/app/(rnd)/notifications/page.tsx): list with unread dot, mark-read on tap (optimistic), "mark all read"; icon by `type`; relative time.
- [ ] Bell + unread badge in the app header mirroring [`TopBar`](../../../frontend/components/layout/TopBar.tsx) (count capped "9+", refresh on screen focus). Bearer token from SecureStore.

---

## Task P: Profile screen (`fss.md` §10) — no backend change

Reuses existing role-agnostic endpoints `GET /api/auth/me`, `PATCH /api/auth/profile`, `POST /api/auth/password` with their `UpdateProfileRequest` / `UpdatePasswordRequest`.

- [ ] App screen mirroring the web [`(rnd)/profile`](../../../frontend/app/(rnd)/profile/page.tsx): account-details card (name/email → `PATCH /api/auth/profile`) and change-password card (current/new/confirm → `POST /api/auth/password`). Pre-fill from `GET /api/auth/me`.
- [ ] UX: visible labels, inline validation on blur, password show/hide toggle, distinct submit→success/error states (ui-ux §8). No avatar (match RND/Admin).

---

## Task S: Settings screen (`fss.md` §11) — device-only, no backend

Mirrors RND/Admin, which persist appearance **on-device only** (no settings backend).

- [ ] App screen mirroring the web [`(rnd)/settings`](../../../frontend/app/(rnd)/settings/page.tsx): appearance (density, reduce-motion) stored via `react-native-mmkv`; "mark all notifications read" (calls the existing read-all endpoint); logout (revoke token via `POST /api/auth/logout` + clear SecureStore).
- [ ] Honor reduce-motion in screen animations (ui-ux §1/§7). No `user_preferences` table.

---

## App Architecture & Navigation (scope-corrected)

### Tech Stack (confirmed — checked against `laravel-boost` Sanctum 13.x docs + `laravel-best-practices` skill)

Backend verified 2026-06-20: Laravel 13.11.2, Sanctum 4.3.2, PHP 8.4, **MySQL** (not SQLite); `HasApiTokens` on `User`. The boost/skill check is Laravel-only — it does not change the frontend picks; it pins the backend contract below.

| Layer | Pick | Notes |
|---|---|---|
| Runtime | **Expo (managed) + EAS** | OTA, camera/upload built-in |
| Navigation | **Expo Router** (file-based) | maps to the 4 bottom tabs below |
| Server state | **TanStack Query** | pull-to-refresh, retry |
| Styling | **NativeWind v4** | Tailwind parity with the Next.js web app |
| Token store | **Expo SecureStore** | encrypted Sanctum Bearer token |
| HTTP | **Axios** + auth interceptor | inject Bearer, 401 → logout |
| Lists | **FlashList** | inventory / PO lists |
| Forms | **react-hook-form + zod** | accomplishment sheet = 7 task flags + ward count/staff-day (§4) |
| Camera/upload | **expo-image-picker + expo-camera + expo-image-manipulator** | receipts/proof, compress before upload (§5) |
| Offline | **TanStack persistQueryClient + react-native-mmkv** | queue mark-served / diet-list, flush on reconnect (kitchen wifi) |
| Storage | **react-native-mmkv** | Query cache + non-secret prefs |
| Dates | **date-fns** | pay-period spans, week views (§4) |
| PDF | **server dompdf → expo-print / share sheet** | do NOT render PDF in RN |

**Skip:** Recharts/Victory (FSS has no analytics — §6 strips insights); Redux (Query covers server state; add tiny `zustand` only for global UI state if needed); tray-level UI (see Note in Tab 2).

### Backend contract for the app (verified — what the API already does / must do)

1. **Auth = token, reuse existing endpoint.** `POST /api/login` ([`AuthController@login`](../../../backend/app/Http/Controllers/Auth/AuthController.php)) already issues a Sanctum token and returns `{ token, user }`. **No `/sanctum/token` endpoint needed** — RN app posts credentials to `/api/login`, stores `token` in SecureStore, sends `Authorization: Bearer`.
2. **Abilities = role string (existing convention).** Token is minted as `createToken('nutriscope-token', [$user->role])`. Per *Consistency First*, gate FSS write-scope (§5/§6) via the existing `role:FSS,RND` middleware — **do not** introduce granular `inventory:write`-style abilities.
3. **✅ FIXED (commit `a55c3ff`) — single-token-per-user.** `login` now scopes revocation by token name: `$tokenName = device_name ?? 'nutriscope-token'`, `$user->tokens()->where('name',$tokenName)->delete()`, then `createToken($tokenName, [$user->role])`. RN app passes a `device_name` (optional) so phone + web sessions coexist. File: [`AuthController.php`](../../../backend/app/Http/Controllers/Auth/AuthController.php), tests in `AuthFeatureTest`.
4. **File uploads (receipts/proof, §5).** Validate in a Form Request with fluent `File::image()->max('10mb')` (real MIME, blocks SVG/XSS). Compress on device first.
5. **Authz/validation (skill):** Form Request per write (diet-list, attachments); `$request->validated()` only; **self-scoped writes** (§A1) via policy/gate, not route role alone; `throttle` on login + API routes. Optional: token `expiration` + `sanctum:prune-expired` for shared devices.

**Tech Stack (one-line):** React Native (Expo), Expo Router, NativeWind, TanStack Query, Expo SecureStore (Sanctum Bearer via existing `/api/login`).

**Bottom Tabs (4 — primary navigation):**
1. **Dashboard** (Home)
2. **Prep & Accomplishment** (daily execution: meal-prep mark-served + accomplishment/diet-list entry — §3, §4)
3. **Inventory** (stock only — §6; **no** suppliers tab)
4. **Procurement** (receiving: list POs, upload receipts/proof only — §5)

**Header / account menu** (mirrors the web `TopBar`, not a bottom tab): a bell with an unread badge → **Notifications** (Task N), and an account control → **Profile** (Task P) and **Settings** (Task S).

Modals: camera/upload for receipts (UX mirrors RND's receipt-upload flow — same endpoint/field shape).

---

## Page Checklists

### Tab 1: Dashboard
- [ ] KPI cards fed by `GET /fss/dashboard/summary` (Task D) — meals left to prep, accomplishment/diet-list pending, POs awaiting receipt, inventory no-stock. **No hardcoded counts.**
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
- [ ] "Mark received" triggers backend restock (if that flow is RND-owned, surface as read-only status instead — confirm against R0.1/R2.2).

### Header: Notifications / Profile / Settings
- [ ] **Notifications** (Task N): list + unread badge on the header bell; mark-read / mark-all via the existing endpoints.
- [ ] **Profile** (Task P): edit name/email + change password via `/api/auth/*`.
- [ ] **Settings** (Task S): appearance (density, reduce-motion, device-only), mark-all-read, logout.

---

## Reproducibility & Workflow Integrity (hard requirement)

Every feature the app displays must trace to a real **input → persist → read** path. Nothing on screen may exist only because it was seeded or hardcoded.
- [ ] **Dashboard** KPIs come from live queries (Task D), proven by tests that insert rows and assert the counts change.
- [ ] **Notifications** are produced by real events (announcement fan-out; PO-awaiting-receipt — Task N), not seeded rows.
- [ ] **Served population** is reproducible: `diet_list_counts` rows sum into `meal_prep_logs.served_population` (Task A1); the hand-typed value remains only as a fallback when a day has no diet-list rows.
- [ ] **Accomplishment report** renders from captured `diet_list_counts` (Task A), not from a fixture.
- [ ] **Scope is enforced in code, not just docs:** off-scope/dead backend removed or RND-gated (Task R2), with 403/404 tests.
- [ ] **Backend hygiene** on every new write: `$request->validated()` only, Form Request per write, self-scoped writes via policy, `File::image()->max('10mb')` for uploads, `throttle` on login/API (laravel-best-practices §3/§6).

---

## Self-Review notes
- Every backend task: `php artisan test` green before commit. Frontend/app: type-check + a device/sim check.
- Don't re-litigate retracted findings (`complete-day missing`, `ai_usage_logs never written`) — verified false.
</content>
