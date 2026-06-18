# Architectural & Sprint Plan Review

**Date:** 2026-06-15 (findings below annotated 2026-06-18 — several have since been resolved or superseded by a later role-scope decision; original findings preserved, not deleted)
**Scope:** `2026-06-15-admin-console-sprint.md`, `fss-sprint-plan.md`, `implementation_plan.md`

## 1. System Rules Compliance (CRITICAL)

*   **API Resources Rule Broken (Admin Sprint):**
    *   *Flaw:* Task 1 of the Admin sprint plan returns `response()->json(['data' => $page->items()])`. 
    *   *Rule Broken:* "All API responses must use Laravel API Resources."
    *   *Fix Required:* The plan must be updated to use `AuditLogResource::collection($page)`.
    *   **RESOLVED (2026-06-18):** The current sprint plan's Task 1 already returns `AuditLogResource::collection($page)`. This finding is stale.
*   **Form Request Validation Rule Broken (Admin Sprint):**
    *   *Flaw:* Task 2 puts `$request->validate(['password' => ...])` directly inside the `resetPassword` controller method.
    *   *Rule Broken:* "All inputs must use Form Requests validation."
    *   *Fix Required:* The plan must create and use an `AdminResetPasswordRequest` class instead of inline validation.
    *   **RESOLVED (2026-06-18):** The current sprint plan's Task 2 already type-hints `AdminResetPasswordRequest`. This finding is stale.

## 2. Laravel Boost & Security Guidelines Review

*   **Configuration Security (`env()` vs `config()`):**
    *   *Status:* **PASSED.** The backend plans do not rely on raw `env()` calls, adhering to the standard of using configuration caching safely via `config()`.
*   **Database Query Safety:**
    *   *Status:* **PASSED.** Eloquent ORM is used exclusively in the planned controller logic (e.g. `Activitylog\Models\Activity`). No raw SQL execution.
*   **Testing Over Tinkering:**
    *   *Status:* **PASSED.** The `2026-06-15-admin-console-sprint.md` explicitly adheres to the Laravel Boost guideline *"Prefer tests with factories instead of manual tinkering."* It scaffolds tests like `AuditLogTest` and `UserManagementTest` *before* the controller implementation.
*   **Form Request Validation:**
    *   *Flaw:* (See Section 1). The Admin plan fails this Laravel Boost security guideline by writing inline validation. It must be refactored to use Form Requests.
    *   **RESOLVED (2026-06-18):** See §1 above — already fixed in the current plan.

## 3. Admin <-> RND Operational Disconnects

*   **Who Manages `clinical_rules`? (Major Hole):**
    *   *Flaw:* You have a hard rule that `"clinical_rules drives all food-disease logic; never hardcode rules."* While we caught the backend hardcoding it, **there is no UI in the Admin or RND plans to actually edit the `clinical_rules` table.** If nobody can edit it, the rules are effectively hardcoded in database seeders, defeating the entire purpose of the dynamic engine.
    *   *Fix Required:* Add a "Clinical Rules Configuration" CRUD page to the Admin Console (or RND Settings) so chief dietitians can dynamically update disease-to-nutrient mappings.
    *   **SUPERSEDED (2026-06-18):** This finding correctly identified the gap, but the "(or RND Settings)" hedge is now resolved as a decision, not left open. **Clinical-rules CRUD belongs to RND, not Admin.** Reasoning settled after this review was written: Admin's role was scoped down to system administration only (RBAC, accounts, audit, system/operational health) — modeled on RPDH's IT department, which has no clinical authority to govern disease-to-nutrient mappings even if it had the UI to do so. RND holds that clinical authority and is the correct owner. See `admin.md` §3 and §5 for full reasoning. **Still an open implementation gap, now correctly scoped:** no RND-side task yet exists to build this page — it was pulled out of the Admin sprint plan's Task 8 (marked HOLD there) but hasn't been written into an RND task. Also note: Phase A (§7 of the implementation plan) already wired the *read* path of `clinical_rules` through `config/clinical.php` rather than the table directly — whoever builds this CRUD page must first confirm which one is the actual source of truth going forward, or the page will write somewhere nothing reads from.

## 4. FSS <-> RND Operational Disconnects

*   **Missing FSS Shortfall Feedback Loop:**
    *   *Flaw:* The `MealPrepLogLine` database model tracks `shortfall_qty`. The FSS app allows staff to log that they couldn't cook a meal because ingredients ran out. **However, the RND is never notified.** If the FSS cannot cook the prescribed meal, the RND must know immediately to prescribe a substitute, otherwise patients starve or get the wrong diet.
    *   *Fix Required:* When FSS submits a `complete-day` prep log with `has_shortfall = true`, the backend must dispatch a system `Notification` to the RND dashboard alerting them of the substitution requirement.
    *   **RESOLVED (2026-06-15, per implementation plan §2.5):** Implemented. `ConsumptionService::completeDay` now supports an opt-in `allow_shortfall` path; `completeDay` writes a `notifications` row to the cycle's `rnd_user_id` on shortfall (`type=meal_prep_shortfall`) and/or population variance (`type=meal_prep_variance`). Tested in `MealPrepShortfallTest.php` (5 tests, green). This finding is resolved, not stale — it correctly identified a real gap that has since been built.
*   **`complete-day` API — CLAIM RETRACTED (verified 2026-06-15):**
    *   *Correction:* This endpoint **already exists** — `routes/api.php:215` maps `POST menu-cycles/{menuCycle}/complete-day` → `FSS\MealPrepLogController@complete`, which records `meal_prep_log_lines.shortfall_qty`. The original "endpoint does not exist / add `FSS/MealPrepController@store`" finding was wrong and would have produced a duplicate controller.
    *   *Actual gap:* `MealPrepLogController@complete` records the shortfall but does **not** notify the RND. Extend it to dispatch a `Notification` when `shortfall_qty > 0` (see `implementation_plan.md` §2.5).
    *   **(No change — already self-correcting as written; superseded by the §2.5 resolution noted just above.)**
*   **Bulk Cooking vs Individual Trays:**
    *   *Status:* The system has the RND generate individualized patient meal plans, but the FSS app only shows the bulk Menu Cycle. FSS cooks the bulk food, but how do they assemble individual patient trays? This relies entirely on the RND printing the `PatientMenuPlan` PDF report and handing it to the kitchen manually. This is an accepted operational constraint but must be documented so developers don't try to build a "Tray Ticket" screen in the FSS app that isn't planned.
    *   **(No change — already documented in `fss-sprint-plan.md` Tab 2 as a Note; confirmed consistent.)**

## 5. Logical Flow & Security Flaws

*   **Missing Backend KPI Endpoints (Admin):** 
    *   *Flaw:* The Admin Sprint Plan tasks the frontend with building KPI cards. However, the Backend Implementation Plan **does not include any steps to build these aggregate endpoints**.
    *   *Fix Required:* Add `AdminDashboardController` to the implementation plan.
    *   **RESOLVED:** `implementation_plan.md` §6 now specifies `AdminDashboardController` with `Cache::remember()` aggregates. This finding is stale.
*   **PHI Redaction Location (Critical):** 
    *   *Flaw:* The Admin Sprint Plan states: *"Ensure PHI is redacted safely in the UI."* This is a massive security breach. If PHI reaches the React client, it's already compromised.
    *   *Fix Required:* PHI redaction must happen strictly on the **backend** before the JSON payload is sent.
    *   **CORRECTED FURTHER (2026-06-18):** This finding was right that redaction must be backend-side, but the actual mechanism turned out to be different from what either this review or the original sprint plan assumed. PHI is redacted at **write-time** (when the activity row is created), via the `AuditsChanges` trait (`$auditRedactValues` on clinical models) — not at read-time in the controller. The sprint plan's original Task 1 had drafted a placeholder controller-level redaction block (`phi_fields_to_redact`, matching no real field) that would have been redundant with the trait, not a fix for this finding. Current sprint plan Task 1 removes that controller-level block entirely and instead adds a regression test confirming the write-time trait is working. Task 7's frontend step is corrected to match (verification only, no client-side redaction logic). See `admin.md` §2 for the full corrected explanation.
*   **Missing Rate Limiting:**
    *   *Flaw:* The `Route::post('users/{user}/reset-password')` lacks explicit rate limiting.
    *   *Fix Required:* Apply Laravel's `throttle` middleware to auth-mutating endpoints.
    *   **RESOLVED:** Current sprint plan Task 2 Step 2 applies `throttle:6,1`. This finding is stale.

## 6. Cache & Token Tracking

*   **Dashboard Aggregates (N+1 and Live Math):**
    *   *Flaw:* Admin KPIs require scanning thousands of rows. Calculating this live will tank server speed. 
    *   *Fix Required:* Must use **Redis caching** (`Cache::remember()`) for these dashboard aggregates.
    *   **(No change — still the correct fix; reflected in `implementation_plan.md` §6.)**
*   **AI token tracking — CLAIM CORRECTED (verified 2026-06-15):**
    *   *Correction:* `ai_usage_logs` is **already** populated. `AIService` writes `AiUsageLog::create([...])` with real input/output token counts (lines 53 & 115), and AI entry points (e.g. `RND\AiDiagnosisController`) call through `AIService`. A separate `AiTokenObserver` is redundant.
    *   *Actual task:* Audit that **all** AI call sites route through `AIService` (none bypass logging), and ensure the planned sync→async refactor of AI calls preserves the `AiUsageLog` write inside the job. The Admin chart then reflects genuine usage with no new observer.
    *   **(No change — already correct and current.)**

---

## 7. NEW SECTION (2026-06-18) — Reports scope, not flagged in the original review

This wasn't a finding in the original review, but it directly affects Section 3 above and the Admin sprint plan, so it's recorded here for traceability rather than only in `admin.md`.

The implementation plan's RND↔FSS↔Admin diagram (see `implementation_plan.md`, "Admin (Web - Oversight)" box, item 3) lists Admin as viewing **"ALL reports (cross-role)."** This has since been narrowed: **Admin's report access is limited to non-clinical, non-patient-identified report types only** — census aggregates (provided they stay true aggregates with no narrow drill-down capability), budget, and procurement. The two patient-identified report types (NCP Summary, Menu Plan) are RND-only and Admin has no standing path to them, full stop — not a "deliberate access with stated purpose" model, but no access at all, because the role's job description doesn't require it.

This is a genuine narrowing from what's shown in the implementation plan's diagram, made after this review and the implementation plan were both written. The implementation plan's diagram needs updating to reflect this — see the companion update to that document.