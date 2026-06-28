# FSS Mobile Login, Workflow, And Production Readiness Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use  `superpowers:executing-plans` to implement this plan task-by-task. Keep the checklist updated as work lands.

**Goal:** Make FSS mobile login work from Expo Go tunnel, production VPS, and final APK; align FSS screens with actual backend behavior; close gaps where required inputs or displays are missing; then build a demo-ready APK. and after youre done tell the user how to update the env file of the vps, and if i need to do anything to the web console of my droplet, and if i need to migrate fresh and reseed the vps. after all is done make sure to verify if what you did aligns with the plan and mobile is production ready, if not fix whats needed and when youre actually satisfied and done commit to main including the apk file.

**Architecture decision:** Laravel remains the source of truth for auth and FSS data. Next.js API routes remain cookie-based web proxies. Mobile must call Laravel directly through a public API origin and use Sanctum Bearer tokens.

**Tech stack:** Laravel 13.11.2, Sanctum 4.3.2, MySQL, Redis, Next.js API routes, Expo SDK 54, React Native 0.81, EAS Android APK.

**Flowchart companion:** `docs/modules/Flowcharts/FSS Mobile Execution Flow.md`.

---

## Source-Of-Truth Order

Use this order when code, docs, and old demo guides disagree:

1. `docs/superpowers/plan/foodservice redesign plan.md` - approved workflow intent.
2. `docs/superpowers/plan/foodservice-data-variables.md` - approved Food Service input/display whitelist.
3. Current backend/mobile code - implementation truth.
4. `docs/modules/Flowcharts/Food Service Operations.md` - high-level RND/FSS workflow.
5. `DEMO_GUIDE.md`, `docs/modules/fss.md` - reference only after code confirms them, may be outdated.

---

## Verified Current State

### Login And API Routing

**Current behavior**

- Mobile login posts `platform: 'app'` from `mobile/app/login.tsx`.
- Laravel rejects Admin/RND mobile login and allows only active FSS users for app login in `backend/app/Http/Controllers/Auth/AuthController.php`.
- Laravel returns a Sanctum token for direct mobile login.
- `mobile/lib/api.ts` sends `Authorization: Bearer <token>` after login.
- `frontend/app/api/auth/login/route.ts` is a web login proxy. It returns `{ user }` and sets httpOnly cookies; it does not return a mobile token.

**Gap**

- `mobile/eas.json` points preview/production at `https://nutriscope.live`. That domain is the Next.js web app, so mobile hits web cookie proxies instead of Laravel token endpoints.
- Expo tunnel only tunnels the JS bundle. It does not make the local Laravel backend reachable.
- Production `docker-compose.prod.yml` migrates but does not seed demo users automatically, so a VPS can correctly return `401 Invalid credentials` when demo users are absent.
- Existing mobile login error handling does not clearly distinguish invalid credentials from "wrong API origin returned no token".

**Recommendation**

- Create `https://api.nutriscope.live` as the public Laravel API origin.
- Point EAS preview/production `EXPO_PUBLIC_API_URL` at `https://api.nutriscope.live`.
- Keep `https://nutriscope.live` for web only.
- Add mobile token-shape validation so the wrong API origin fails with a clear message.
- Add a production demo seed checklist and smoke command before APK build.

**Related files**

- `mobile/app/login.tsx` - mobile login payload, response handling, token save.
- `mobile/lib/api.ts` - base URL, Bearer interceptor, 401 handling.
- `mobile/eas.json` - APK build-time API URL.
- `mobile/.env.example` - local Expo Go API URL guidance.
- `backend/app/Http/Controllers/Auth/AuthController.php` - role/platform gate and token issuance.
- `backend/app/Http/Requests/Auth/LoginRequest.php` - validates login payload and `platform`.
- `backend/database/seeders/AdminUserSeeder.php` - demo users and roles.
- `frontend/app/api/auth/login/route.ts` - web cookie login proxy that must not be used by mobile.
- `docker-compose.prod.yml` - production backend exposure and migrations.
- `deployment.md` - VPS nginx, DNS, SSL, and deployment commands.

### FSS Navigation

**Current behavior**

- Bottom tabs should be `Dashboard`, `Menu`, `Prep & Accomp.`, and `Procurement`.
- Current code has a Menu screen and it should be visible in bottom navigation.
- FSS can also open Menu from Prep, but Menu should still be visible in bottom navigation.
- Header buttons route to `announcements`, `notifications`, and `settings`.
- Settings routes to Profile.

**Gap**

- Old docs mention an Inventory bottom tab. The redesign says Inventory is backend reference only and must not be FSS navigation.
- Older navigation hid the Menu tab, which conflicts with the redesign's two FSS menu-cycle views.

**Recommendation**

- Keep exactly four visible FSS bottom tabs: Dashboard, Menu, Prep & Accomp., Procurement.
- Keep Menu Cycle reachable from Prep as a convenience path, but also visible as the Menu tab.
- Remove Inventory from FSS docs and demos.
- Update the FSS flowchart and demo guide to show header routes separately from bottom navigation.

**Related files**

- `mobile/app/(tabs)/_layout.tsx` - bottom tab wiring with visible Menu route.
- `mobile/components/AppHeader.tsx` - header notification, announcements, settings navigation.
- `mobile/app/settings.tsx` - Profile entry point.
- `mobile/app/profile.tsx` - account profile screen.
- `DEMO_GUIDE.md` - stale mobile navigation reference.
- `docs/modules/fss.md` - FSS module documentation to update after fixes.

### Dashboard Display

**Current behavior**

- Backend dashboard summary returns:
  - `meals_to_log_today`
  - `pending_pos`
  - `pending_pos_count`
  - `today_service`
- `pending_pos[]` includes:
  - `id`
  - `po_number`
  - `procurement_track`
  - `waiting_on`
- For food POs, `waiting_on` can include `receipts` and `served_population`.
- Mobile dashboard displays meals-to-log count, pending PO count, and today's service.
- The active menu week is available through the Menu screen, but Dashboard does not currently show an active menu week summary/card.

**Gap**

- Mobile defines `pending_pos[].waiting_on` in its type but currently does not display the per-PO waiting reasons. FSS can see a count, but not what action is blocking each PO.
- Dashboard does not currently show the current active menu plan week; adding a compact active-week card would match FSS workflow and give the Menu tab a clear entry point.
- If backend later blocks completion because the procurement-year `Budget` record is missing, the dashboard does not surface that administrative blocker.

**Recommendation**

- Add a Pending PO list/expandable section on Dashboard:
  - PO number.
  - Procurement track.
  - Waiting on receipts.
  - Waiting on served population.
  - Optional admin/RND blocker if backend adds `budget_allocation`.
- Keep forbidden dashboard content absent:
  - no inventory/stock card;
  - no budget-per-head KPI;
  - no graph/insight widgets.
- Add an Active Menu Week section/card on Dashboard:
  - active cycle name;
  - week start date;
  - summary of planned service days;
  - link to the Menu tab.

**Related files**

- `backend/app/Services/FSS/FssDashboardService.php` - dashboard source data and `waiting_on` logic.
- `mobile/app/(tabs)/index.tsx` - dashboard cards and service list.
- `backend/app/Http/Controllers/FSS/DashboardController.php` - dashboard endpoint.
- `backend/app/Http/Resources/PurchaseOrderResource.php` - PO fields used elsewhere.

### Prep, Diet List, And Served Population

**Current behavior**

- Prep screen loads today's service from `GET /api/fss/dashboard/summary`.
- `Mark served` posts to `POST /api/fss/menu-cycles/{cycleId}/complete-day`.
- `MealPrepLogController::complete()` calls `ConsumptionService::completeDay()` and refreshes purchase-order lifecycle for the service date.
- Ingredient stock shortfall returns validation failure; mobile can ask FSS to proceed with `allow_shortfall`.
- This is backend stock validation during meal completion, not an FSS inventory workflow or inventory page.
- Diet-list/accomplishment form posts:
  - `service_date`
  - `ward`
  - `population`
  - `off_duty`
  - seven task flags
  - optional `menu_cycle_id`
- Diet-list store syncs served population into the matching Meal Prep Log when a log exists, refreshes lifecycle, and archives the week.
- Menu Cycle screen can backfill served population by PATCHing `served-population`.
- Backfill can create/update a Meal Prep Log and refresh PO lifecycle, unless the covered food PO is already completed.

**Gap**

- `DietListCountController@index()` returns all matching diet-list rows to any authenticated FSS/RND route user. For ordinary FSS mobile use, FSS should see their own diet-list/accomplishment rows only.
- The app has both day-by-day Prep and full Menu Cycle views, but old docs do not explain how data flows between them.
- Dashboard pending PO display does not tell FSS that served population is the missing completion step.
- make sure accomplishment reports fields and what is generated in the accomplishment report after FSS completes the task is aligned to docs/Nutriscope Forms/accomplishment report for fss.jpg, current accomplishment report field for diet list collected in .. should be a number as stated in the image, or if system already lets you input number it should reflect the generated report, and the seeded data should be similar to that of the accomplishment report for fss.jpg, the blank days usually means off days so if users didnt log anything that will show

**Recommendation**

- Scope `DietListCountController@index()` by current user when `Auth::user()->isFss()`.
- Keep RND/Admin broader visibility through report/admin endpoints.
- Preserve multiple ward rows per day, but prevent exact duplicate `fss_user_id + service_date + ward` if duplicate entries become a real data issue.
- Label served population consistently as actual/served headcount, never estimated procurement population.
- Add empty states:
  - no active cycle -> "Contact RND to activate a menu cycle."
  - no meals today -> "No meals scheduled for today."
  - no accomplishment rows -> "No accomplishment rows saved for today."

**Related files**

- `mobile/app/(tabs)/prep.tsx` - day-by-day Prep, mark served, diet-list/accomplishment form.
- `mobile/app/(tabs)/menu.tsx` - full menu cycle, recipe/item profiles, served population backfill.
- `mobile/lib/foodService.ts` - menu and meal-prep API wrappers.
- `backend/app/Http/Controllers/FSS/MealPrepLogController.php` - complete-day and served-population behavior.
- `backend/app/Http/Controllers/FSS/DietListCountController.php` - diet-list/accomplishment create/index scope.
- `backend/app/Services/FSS/ConsumptionService.php` - backend inventory consumption when meals are completed.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php` - PO completion refresh after served-population changes.

### Procurement Execution

**Current behavior**

- Mobile Procurement screen lists RND-created POs.
- FSS can open a PO, open a vendor group, save OR number, and upload receipt/proof images.
- Current mobile UI displays line details read-only.
- Receipt upload marks the vendor group received server-side, runs receiving once, sets `stocked_at`, and refreshes PO lifecycle.
- Food PO completion requires receipts plus served population for all covered service dates.
- Supplies PO completion requires receipts only.

**Gap**

- Backend `PurchaseOrderController::updateVendorGroup()` currently accepts `status` and `items.*` fields on a route allowed for `FSS,RND`. Even though mobile UI no longer exposes item editing, an FSS token can directly call the API and attempt item/price/status changes.
- Attachment preview depends on `/storage/...` URLs resolving from the configured API origin. Production needs Laravel storage link, public disk, and correct API host.
- If budget allocation is missing for the procurement year, food PO completion silently remains open from FSS perspective.

**Recommendation**

- Enforce role-level backend permission:
  - FSS allowed: `or_number`.
  - FSS allowed: receipt/proof attachment upload.
  - FSS allowed: delete own unlocked attachments if intended by policy.
  - FSS denied: `status`.
  - FSS denied: `items`.
  - FSS denied: prices, quantities, units, vendor grouping, supplier edits.
  - RND allowed: price correction during open execution, with audit.
- Add backend tests proving FSS can update OR only and cannot patch items/status.
- Keep procurement UI read-only for line details.
- Add dashboard/pending PO text for "waiting on RND budget setup" if lifecycle service exposes that blocker.

**Related files**

- `mobile/app/(tabs)/procurement.tsx` - FSS PO list/detail, OR save, attachment upload/preview.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php` - vendor group update and attachment upload behavior.
- `backend/app/Services/FSS/ReceivingService.php` - receipt-driven inventory receiving.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php` - food/supplies completion rules.
- `backend/app/Models/PurchaseOrderVendorGroup.php` - OR/status/attachment state.
- `backend/app/Models/PurchaseOrderVendorGroupItem.php` - line items that FSS should not mutate.
- `backend/routes/api.php` - FSS/RND route middleware and boundaries.

### Profile Contents

**Current behavior**

- Backend `UserResource` returns `contact_number`, `profile_photo`, `role`, and `is_active`.
- Backend profile update request accepts `contact_number` and `profile_photo`.
- Mobile Profile currently focuses on name/email and password.

**Gap**

- FSS cannot see role/status context in the profile.
- FSS cannot edit contact number even though backend supports it.

**Recommendation**

- Add read-only Role and Status display.
- Add editable Contact Number.
- Display existing Profile Photo only if already present.
- Defer real photo upload until auth/API/APK flow is stable.

**Related files**

- `mobile/app/profile.tsx` - profile UI and update mutation.
- `backend/app/Http/Resources/UserResource.php` - user fields available to mobile.
- `backend/app/Http/Requests/Auth/UpdateProfileRequest.php` - profile update validation.
- `backend/app/Http/Controllers/Auth/ProfileController.php` - profile update endpoint.

### Reports And Accomplishment Flow

**Current behavior**

- Prep has a Reports button.
- Diet-list/accomplishment entries archive weekly through backend services.
- FSS should use reports for accomplishment review, not inventory or procurement planning.

**Gap**

- Existing docs do not tie diet-list entries, weekly archives, and mobile reports into one FSS-facing flow.
- If FSS index endpoints are not scoped, accomplishment history can leak broader rows.

**Recommendation**

- Document the FSS accomplishment flow in `docs/modules/fss.md`.
- Scope FSS data reads to current user where the mobile endpoint is meant for personal FSS entry/history.
- Keep broad reporting under explicit RND/Admin report endpoints.

**Related files**

- `mobile/app/(tabs)/prep.tsx` - entry point to reports.
- `mobile/app/reports.tsx` - mobile reports screen.
- `backend/app/Http/Controllers/FSS/DietListCountController.php` - accomplishment row source.
- `backend/app/Services/FSS/WeeklyAccomplishmentArchiveService.php` - archive generation.
- `backend/app/Http/Controllers/ReportController.php` - broader report behavior.

---

## Functional Data Flow

### FSS Login

1. FSS enters credentials in mobile.
2. Mobile sends `POST /api/auth/login` with `platform: app`.
3. Laravel validates active user and role `FSS`.
4. Laravel returns Sanctum token and user.
5. Mobile stores token and calls FSS endpoints with Bearer auth.
6. Wrong role returns 403.
7. Wrong credentials or missing production seed returns 401.
8. Wrong API origin returns no token and must show an API-origin error.

### FSS Prep And Served Population

1. Dashboard/Prep read active menu cycle and today's service.
2. FSS marks meal served.
3. Backend validates stock and logs meal prep.
4. Backend deducts inventory through service logic.
5. Backend refreshes PO lifecycle for the service date.
6. FSS enters diet-list/accomplishment rows by ward.
7. Backend stores rows under the current FSS user.
8. Backend syncs served population to Meal Prep Log when possible.
9. Food PO remains open until every covered date has served population.

### FSS Procurement

1. RND creates shopping list, converts it to PO, and opens execution.
2. FSS sees open POs in Procurement and Dashboard.
3. FSS opens a vendor group.
4. FSS saves OR number.
5. FSS uploads proof and receipt.
6. Receipt marks vendor group received and triggers backend receiving.
7. Backend refreshes PO lifecycle.
8. Supplies PO completes when all vendor groups have receipts.
9. Food PO completes when all vendor groups have receipts and all covered service dates have served population.
10. Completion locks final values and records budget ledger deduction.

---

## Implementation Plan

### 1. Split Web And Mobile API Origins

- [ ] Add `api.nutriscope.live` DNS pointing to the VPS. *(manual VPS step — see deployment.md)*
- [x] Expose Laravel backend to host on `127.0.0.1:8080:80` in production compose.
- [x] Add nginx reverse proxy for `api.nutriscope.live` to Laravel. (`nginx/api.nutriscope.live.conf`)
- [ ] Add SSL with certbot. *(manual VPS step — see deployment.md)*
- [x] Update `deployment.md` with direct Laravel API smoke tests and setup instructions.
- [ ] Verify `https://api.nutriscope.live/api/auth/login` returns Laravel JSON on POST. *(requires live VPS)*

**Files**

- `docker-compose.prod.yml`
- `deployment.md`
- `nginx/api.nutriscope.live.conf`

### 2. Point Expo/EAS At Laravel API

- [x] Update `mobile/eas.json` preview and production env to `https://api.nutriscope.live`.
- [x] Update `mobile/.env.example` with three supported development modes.
- [x] Document that `npx expo start --tunnel` does not tunnel Laravel.

**Files**

- `mobile/eas.json`
- `mobile/.env.example`
- `deployment.md`

### 3. Make Mobile Login Errors Specific

- [x] In `mobile/app/login.tsx`, validate that login response includes a string `token`.
- [x] If token is absent, show specific API-origin error message.
- [x] Preserve backend 401/403 messages for invalid credentials and wrong role.
- [x] TypeScript passes (`npx tsc --noEmit`).

**Files**

- `mobile/app/login.tsx`

### 4. Verify Production Demo Seed State

- [x] Confirmed `AdminUserSeeder` seeds all three demo users with correct roles and passwords.
- [x] Confirmed `FoodServiceDemoSeeder` exists and seeds full operational data.
- [x] Added safe VPS command for checking demo users (tinker one-liner).
- [x] Added safe non-destructive seed commands in `deployment.md`.
- [x] Updated `DEMO_GUIDE.md` — removed dangerous `migrate:fresh` guidance.

**Files**

- `deployment.md`
- `DEMO_GUIDE.md`

### 5. Fix FSS Dashboard Missing PO Detail And Active Menu Week

- [x] Display `pending_pos[]` with `po_number`, `procurement_track`, and `waiting_on` labels.
- [x] Each PO card links to Procurement tab.
- [x] Empty state shown when no pending POs.
- [x] Active Menu Week card: cycle name, activation date, service-day count, links to Menu tab.
- [x] No-active-cycle state shows amber warning.
- [x] Inventory and budget cards absent.
- [x] `FssDashboardService::summary()` now returns `active_cycle` field.

**Files**

- `mobile/app/(tabs)/index.tsx`
- `backend/app/Services/FSS/FssDashboardService.php`

### 6. Scope FSS Diet-List Reads

- [x] `DietListCountController@index()` scopes by `fss_user_id = Auth::id()` for FSS users.
- [x] RND/Admin see broader views through the shared FSS+RND route.

**Files**

- `backend/app/Http/Controllers/FSS/DietListCountController.php`

### 7. Enforce FSS Procurement Permissions In Backend

- [x] `PurchaseOrderController::updateVendorGroup()` rejects FSS requests containing `items` or `status` with 403.
- [x] FSS data is restricted to `or_number` only after validation.
- [x] RND price-correction path unchanged and audited.
- [x] `FoodServiceOpsTest` updated to verify FSS 403 on items/status and RND correction audit.
- [x] All 65 `FoodServiceOpsTest` pass.

**Files**

- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`
- `backend/tests/Feature/FoodServiceOpsTest.php`

### 8. Improve FSS Profile

- [x] Role and Status display (read-only with status dot indicator).
- [x] Editable Contact Number field with `PATCH /api/auth/profile` payload.
- [x] Name and Email remain editable.
- [x] No new photo upload (deferred per plan).
- [x] TypeScript passes.

**Files**

- `mobile/app/profile.tsx`

### 9. Update Stale Docs

- [x] Updated `docs/modules/fss.md`: correct bottom tabs (4), dashboard widgets, procurement permissions, data scoping, profile fields.
- [x] Updated `DEMO_GUIDE.md`: safe reseed instructions, removed `migrate:fresh` guidance.

**Files**

- `DEMO_GUIDE.md`
- `docs/modules/fss.md`

### 10. Verification Matrix Before APK

- [x] Backend auth: 13/13 passed.
- [x] Backend FSS dashboard: 11/11 passed.
- [x] Backend food service ops: 65/65 passed.
- [x] Mobile TypeScript: clean (no errors).
- [ ] Local Expo Go LAN test: *(manual step)*
- [ ] Expo Go tunnel with production API: *(manual step)*
- [ ] Production API smoke: *(requires live VPS with api.nutriscope.live DNS + SSL)*

### 11. Build APK

- [x] `mobile/eas.json` preview profile confirmed: `buildType: "apk"`, `EXPO_PUBLIC_API_URL: "https://api.nutriscope.live"`.
- [ ] Run build: `npx eas build -p android --profile preview` *(requires EAS account login)*

- [ ] Build:

```powershell
cd C:\Users\jared\Documents\Nutriscope\mobile
npx eas build -p android --profile preview
```

- [ ] APK smoke:
  - login with `fss@nutriscope.local`;
  - Dashboard loads and shows pending PO reasons;
  - Dashboard shows the active menu week summary/card;
  - Prep loads today's service;
  - diet-list/accomplishment saves;
  - Menu tab opens the menu-cycle list;
  - Menu Cycle also opens from Prep;
  - Procurement loads POs;
  - OR save works;
  - receipt/proof upload works;
  - Profile shows role/status/contact;
  - logout works.

---

## Current Blockers Before APK

- No confirmed public Laravel API origin for mobile.
- EAS production env points mobile at the web domain instead of Laravel API.
- Production demo seed state is unknown.
- Mobile login needs a clear no-token/wrong-origin error.
- Dashboard hides per-PO `waiting_on` details that backend already provides.
- Dashboard does not yet show a compact active menu week summary/card.
- FSS diet-list reads need self-scope.
- Backend procurement endpoint must block FSS direct item/status mutation.
- Docs conflict with current navigation and FSS responsibility boundaries.

---

## Recommended FSS Improvements

- Add Dashboard active menu week summary/card linking to the Menu tab.
- Keep FSS mobile to execution work only: served population, diet-list/accomplishment, OR number, receipt/proof upload.
- Keep planning/admin work out of FSS mobile: no inventory tab, no supplier management, no recipe editing, no shopping-list editing, no budget editing.
- Add a small development-only API origin badge to reduce Expo demo confusion.
- Add clearer empty states for no active cycle, no meals today, no pending POs, and no reports.
- Add a one-page mobile demo runbook after code fixes land.
