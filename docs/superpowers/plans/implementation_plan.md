# Backend API Implementation Plan (FSS & Admin)

> This document defines the immediate technical execution steps for the backend architecture connecting RND, FSS, and Admin.

## RND ↔ FSS ↔ Admin Data Flow
```
┌─────────────────────────────────────┐
│          RND (Web - Planning)       │
│                                     │
│  1. Create FS Recipes               │
│  2. Build Menu Cycle (population,   │
│     budget/head, weekly grid)       │
│  3. Generate Shopping Lists         │
│  4. Split into Purchase Orders      │
│  5. Set Budgets                     │
│  6. Activate Menu Cycle             │
│     (costs freeze at activation)    │
└──────────────┬──────────────────────┘
               │ Shared /api/fss/* routes
               │ Same DB tables
               ▼
┌─────────────────────────────────────┐
│       FSS (Mobile - Execution)      │
│                                     │
│  1. View Active Menu Cycle (R/O)    │
│  2. Prep meals → complete-day       │
│     (deducts from inventory)        │
│  3. Log cleaning tasks              │
│  4. Receive deliveries → attach     │
│     receipt photos → mark received  │
│     (restocks inventory +           │
│      feeds budget actuals)          │
│  5. CRUD inventory / suppliers      │
│  6. View budget summary (R/O)       │
└─────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│       Admin (Web - Oversight)       │
│                                     │
│  1. Provision RND/FSS accounts      │
│  2. Monitor audit logs              │
│  3. View ALL reports (cross-role)   │
│  4. Configure hospital branding     │
│  5. Track AI token usage            │
│  6. Post announcements              │
└─────────────────────────────────────┘
```

## Technical Execution Steps

### 1. FSS CleaningLog Schema & API
- **Schema**: Add `fss_user_id` (FK), `item_name`, `category`, `status`, `notes`, `cleaned_at` to the `cleaning_logs` migration.
- **Model**: Fix namespace and location for `CleaningLog`, add `$fillable`, relationships (`user()`).
- **Controller**: Add full CRUD to `CleaningLogController` mapped to `/api/fss/cleaning-logs`.
- **TDD**: Write `CleaningLogTest.php`.

### 2. FSS Read-Only Permissions Enforcement
- **Problem**: `/api/fss/*` currently allows both RND and FSS full CRUD.
- **Fix**: Apply role-check guards to `store`, `update`, `destroy` methods in `MenuCycleController`, `BudgetController`, and `FoodServiceRecipeController`. FSS receives `403 Forbidden` on write attempts.
- **TDD**: Write `FssPermissionTest.php`.

### 2.5. FSS Meal Prep Shortfall Feedback Loop
- **Problem**: RND is never notified when FSS logs a prep shortfall.
- **Correction (verified 2026-06-15)**: The `complete-day` endpoint **already exists** — `routes/api.php:215` maps `POST menu-cycles/{menuCycle}/complete-day` → `FSS\MealPrepLogController@complete` (logs `meal_prep_log_lines.shortfall_qty`). Do **not** create a new `FSS/MealPrepController@store`; that would duplicate it.
- **Fix**: Extend the existing `MealPrepLogController@complete`. When any line has `shortfall_qty > 0`, dispatch a system `Notification` to the RND who owns the active menu cycle. Add coverage to `MealPrepLogTest`.

### 3. FSS Announcements Support
- **Fix**: Adjust `AnnouncementController@index` to ensure FSS users can fetch announcements where `visibility` is `FSS` or `All`.

### 4. Admin Audit Log Fixes
- **Problem**: Unpaginated audit log endpoint will crash browser at scale. PHI exposure.
- **Fix**: Update `Admin\AuditLogController` to use server-side pagination (`->paginate(25)`). Ensure PHI is redacted from payload before returning to the frontend.

### 5. Admin Password Reset Fix
- **Problem**: Admin cannot manually reset passwords for RND/FSS. Missing rate limit.
- **Fix**: Add `Route::post('users/{user}/reset-password')` mapped to an Admin controller action and apply `throttle` middleware. Use Form Requests.

### 6. Admin Dashboard Aggregates & Token Tracking
- **Problem**: Dashboard requires KPIs. Live calculations cause N+1 and tank server speed. Missing real AI token data.
- **Fix**: Create `AdminDashboardController` returning aggregated KPIs using `Cache::remember()`.
- **Correction (verified 2026-06-15)**: `ai_usage_logs` is **already** populated — `AIService` writes `AiUsageLog::create([...])` (token in/out) at lines 53 & 115, and `RND\AiDiagnosisController` calls AI through `AIService`. Do **not** build a redundant `AiTokenObserver`. Residual task is narrower: audit that **every** AI entry point routes through `AIService` (so none bypass logging), and when AI calls move to background jobs (§7), ensure the job still records `AiUsageLog`.

### 7. RND Clinical Logic & Algorithms Fixes
- **Problem**: Hardcoded clinical rules, synchronous AI/Reports, missing caching, N+1 queries.
- **Fix**: Update `RND/InterventionController.php` (`mapGoalTypeToConditions`, ~line 153) to query the `clinical_rules` table via the `ClinicalRule` model instead of the hardcoded `match`. Note `NutritionPrescriptionService.php:189` also branches on `goal_type` — confirm whether that path likewise needs to consult `clinical_rules`. Switch AI diagnosis/reports to background jobs (return 202). Implement `Cache::remember()` in `MonitoringController` for AI-reviews. Eager load relations in `NcpRecordController`.
- **Logic Sync**: Update `NutritionPrescriptionService` to fully implement `prescription-targets.json` (e.g. `free_sugar_max_pct_energy` and `bmi_range` thresholds).
- **Algorithm Fixes**: Add AI fallback to `MealPlanService.php` when <5 recipes match. Update `ProcurementService` to subtract `quantity_on_hand` from suggested purchases. Ensure PHI is stripped from AI payloads.

*Refer to the respective Sprint Plans for the long-term UI roadmap.*
