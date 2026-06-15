# Architectural & Sprint Plan Review

**Date:** 2026-06-15
**Scope:** `2026-06-15-admin-console-sprint.md`, `fss-sprint-plan.md`, `implementation_plan.md`

## 1. System Rules Compliance (CRITICAL)

*   **API Resources Rule Broken (Admin Sprint):**
    *   *Flaw:* Task 1 of the Admin sprint plan returns `response()->json(['data' => $page->items()])`. 
    *   *Rule Broken:* "All API responses must use Laravel API Resources."
    *   *Fix Required:* The plan must be updated to use `AuditLogResource::collection($page)`.
*   **Form Request Validation Rule Broken (Admin Sprint):**
    *   *Flaw:* Task 2 puts `$request->validate(['password' => ...])` directly inside the `resetPassword` controller method.
    *   *Rule Broken:* "All inputs must use Form Requests validation."
    *   *Fix Required:* The plan must create and use an `AdminResetPasswordRequest` class instead of inline validation.

## 2. Laravel Boost & Security Guidelines Review

*   **Configuration Security (`env()` vs `config()`):**
    *   *Status:* **PASSED.** The backend plans do not rely on raw `env()` calls, adhering to the standard of using configuration caching safely via `config()`.
*   **Database Query Safety:**
    *   *Status:* **PASSED.** Eloquent ORM is used exclusively in the planned controller logic (e.g. `Activitylog\Models\Activity`). No raw SQL execution.
*   **Testing Over Tinkering:**
    *   *Status:* **PASSED.** The `2026-06-15-admin-console-sprint.md` explicitly adheres to the Laravel Boost guideline *"Prefer tests with factories instead of manual tinkering."* It scaffolds tests like `AuditLogTest` and `UserManagementTest` *before* the controller implementation.
*   **Form Request Validation:**
    *   *Flaw:* (See Section 1). The Admin plan fails this Laravel Boost security guideline by writing inline validation. It must be refactored to use Form Requests.

## 3. Admin <-> RND Operational Disconnects

*   **Who Manages `clinical_rules`? (Major Hole):**
    *   *Flaw:* You have a hard rule that `"clinical_rules drives all food-disease logic; never hardcode rules."* While we caught the backend hardcoding it, **there is no UI in the Admin or RND plans to actually edit the `clinical_rules` table.** If nobody can edit it, the rules are effectively hardcoded in database seeders, defeating the entire purpose of the dynamic engine.
    *   *Fix Required:* Add a "Clinical Rules Configuration" CRUD page to the Admin Console (or RND Settings) so chief dietitians can dynamically update disease-to-nutrient mappings.

## 4. FSS <-> RND Operational Disconnects

*   **Missing FSS Shortfall Feedback Loop:**
    *   *Flaw:* The `MealPrepLogLine` database model tracks `shortfall_qty`. The FSS app allows staff to log that they couldn't cook a meal because ingredients ran out. **However, the RND is never notified.** If the FSS cannot cook the prescribed meal, the RND must know immediately to prescribe a substitute, otherwise patients starve or get the wrong diet.
    *   *Fix Required:* When FSS submits a `complete-day` prep log with `has_shortfall = true`, the backend must dispatch a system `Notification` to the RND dashboard alerting them of the substitution requirement.
*   **`complete-day` API — CLAIM RETRACTED (verified 2026-06-15):**
    *   *Correction:* This endpoint **already exists** — `routes/api.php:215` maps `POST menu-cycles/{menuCycle}/complete-day` → `FSS\MealPrepLogController@complete`, which records `meal_prep_log_lines.shortfall_qty`. The original "endpoint does not exist / add `FSS/MealPrepController@store`" finding was wrong and would have produced a duplicate controller.
    *   *Actual gap:* `MealPrepLogController@complete` records the shortfall but does **not** notify the RND. Extend it to dispatch a `Notification` when `shortfall_qty > 0` (see `implementation_plan.md` §2.5).
*   **Bulk Cooking vs Individual Trays:**
    *   *Status:* The system has the RND generate individualized patient meal plans, but the FSS app only shows the bulk Menu Cycle. FSS cooks the bulk food, but how do they assemble individual patient trays? This relies entirely on the RND printing the `PatientMenuPlan` PDF report and handing it to the kitchen manually. This is an accepted operational constraint but must be documented so developers don't try to build a "Tray Ticket" screen in the FSS app that isn't planned.

## 5. Logical Flow & Security Flaws

*   **Missing Backend KPI Endpoints (Admin):** 
    *   *Flaw:* The Admin Sprint Plan tasks the frontend with building KPI cards. However, the Backend Implementation Plan **does not include any steps to build these aggregate endpoints**.
    *   *Fix Required:* Add `AdminDashboardController` to the implementation plan.
*   **PHI Redaction Location (Critical):** 
    *   *Flaw:* The Admin Sprint Plan states: *"Ensure PHI is redacted safely in the UI."* This is a massive security breach. If PHI reaches the React client, it's already compromised.
    *   *Fix Required:* PHI redaction must happen strictly on the **backend** before the JSON payload is sent.
*   **Missing Rate Limiting:**
    *   *Flaw:* The `Route::post('users/{user}/reset-password')` lacks explicit rate limiting.
    *   *Fix Required:* Apply Laravel's `throttle` middleware to auth-mutating endpoints.

## 6. Cache & Token Tracking

*   **Dashboard Aggregates (N+1 and Live Math):**
    *   *Flaw:* Admin KPIs require scanning thousands of rows. Calculating this live will tank server speed. 
    *   *Fix Required:* Must use **Redis caching** (`Cache::remember()`) for these dashboard aggregates.
*   **AI token tracking — CLAIM CORRECTED (verified 2026-06-15):**
    *   *Correction:* `ai_usage_logs` is **already** populated. `AIService` writes `AiUsageLog::create([...])` with real input/output token counts (lines 53 & 115), and AI entry points (e.g. `RND\AiDiagnosisController`) call through `AIService`. A separate `AiTokenObserver` is redundant.
    *   *Actual task:* Audit that **all** AI call sites route through `AIService` (none bypass logging), and ensure the planned sync→async refactor of AI calls preserves the `AiUsageLog` write inside the job. The Admin chart then reflects genuine usage with no new observer.
